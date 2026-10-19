<?php

namespace App\Services;

use App\Enums\AppOperationLogAction;
use App\Enums\NewsletterSource;
use App\Enums\NoticeMail;
use App\Models\Member;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Neobit\Core\Operations\OperationRecorder;

/**
 * お知らせメール（メルマガ）を受け取るかどうかの、会員と会員ではない人をまたいだ扱い。
 *
 * ■ 持つ場所
 * 会員は会員のテーブル（t_members.notice_mail）、会員ではない人はt_newsletter_subscribersで持つ。
 * 同じアドレスの「受け取るか」を、どの時点でも1か所だけで持つため、行を作ったり消したりするのは
 * 次の場面だけにする。
 * - メルマガの申し込み：会員のアドレスなら会員の側を「受け取る」にし、そうでなければ行を作る
 * - 会員ではない人の配信停止と、管理画面での停止：行を「受け取らない」にする
 * - 会員登録：同じアドレスの行を消す。登録の画面で選んだ「受け取るか」を会員の側で持つ
 * - 退会：「受け取る」だった会員は、「受け取らない」の行を残す。会員の情報が消えた後に、
 *   同じアドレスが宛先のCSVに入っても送らないため。退会は、同意の取り下げとみなす
 *
 * ■ 一斉メール
 * 会員の側か行の側で「受け取らない」のアドレスは、宛先のCSVでエラーにする（stoppedEmails()）。
 * 宛先のCSVの元になる「受け取る」の人の一覧は、recipientRows()が両方から作る。
 */
final class NewsletterSubscription
{
    /**
     * メルマガの申し込みを確定する。確認コードが通った後に呼ぶ。
     * そのアドレスの会員がいれば会員の側を「受け取る」にし、いなければ行を「受け取る」にする。
     * どちらになったかは返さない。呼ぶ側が、会員かどうかで画面を変えないようにするため。
     */
    public static function subscribe(string $name, string $email, ?string $ip): void
    {
        DB::transaction(function () use ($name, $email, $ip) {
            $member = Member::where('email', $email)->first();

            // 会員のアドレスなら、会員の側で持つ
            if ($member !== null) {
                $changed = ! $member->receivesNoticeMail();
                $member->update(['notice_mail' => NoticeMail::Receive->value]);

                OperationRecorder::record(AppOperationLogAction::NewsletterSubscribe, $member, changedFields: $changed ? ['notice_mail'] : []);

                return;
            }

            // 会員ではないアドレスは、行を作るか、前の行を「受け取る」に戻す
            $subscriber = NewsletterSubscriber::updateOrCreate(['email' => $email], [
                'name' => $name,
                'notice_mail' => NoticeMail::Receive->value,
                'source' => NewsletterSource::Form->value,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'ip' => $ip,
            ]);

            OperationRecorder::record(AppOperationLogAction::NewsletterSubscribe, $subscriber);
        });
    }

    /**
     * 会員ではないアドレスを「受け取らない」にする。行が無ければ「受け取らない」の行を作る。
     * すでに「受け取らない」の行は変えない。最初に止めた日時と経路を残すため。
     */
    public static function stop(string $email, NewsletterSource $source, ?string $ip): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);

        if ($subscriber->exists && ! $subscriber->receivesNoticeMail()) {
            return $subscriber;
        }

        $subscriber->fill([
            'notice_mail' => NoticeMail::Stop->value,
            'source' => $source->value,
            'unsubscribed_at' => now(),
            'ip' => $ip,
        ])->save();

        return $subscriber;
    }

    /**
     * 会員登録の後に呼ぶ。同じアドレスの行を消し、そこからは会員の側で持つ。
     * 受け取るかは、行の値ではなく、登録の画面で本人が選んだものにする。いちばん新しい本人の意思のため。
     */
    public static function forgetForMember(Member $member): void
    {
        NewsletterSubscriber::where('email', $member->email)->delete();
    }

    /** 退会の前に呼ぶ。「受け取る」だった会員は、「受け取らない」の行を残す */
    public static function recordWithdrawal(Member $member, ?string $ip): void
    {
        if ($member->receivesNoticeMail()) {
            self::stop($member->email, NewsletterSource::Withdrawal, $ip);
        }
    }

    /**
     * 渡したアドレスのうち、お知らせメールを受け取らないもの。会員の側か行の側で「受け取らない」なら入る。
     * 小文字にしたアドレス => trueで返す。
     *
     * @param  list<string>  $emails
     * @return array<string, true>
     */
    public static function stoppedEmails(array $emails): array
    {
        $members = Member::whereIn('email', $emails)
            ->where('notice_mail', NoticeMail::Stop->value)
            ->pluck('email');
        $subscribers = NewsletterSubscriber::whereIn('email', $emails)
            ->where('notice_mail', NoticeMail::Stop->value)
            ->pluck('email');

        $stopped = [];
        foreach ($members->concat($subscribers) as $email) {
            $stopped[mb_strtolower($email)] = true;
        }

        return $stopped;
    }

    /**
     * お知らせメールを受け取る人全員の「氏名, メールアドレス」。一斉メールの宛先のCSVと同じ形。
     * 会員を先に、会員ではない人を後に並べる。同じアドレスは1行にまとめる。
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function recipientRows(): array
    {
        $rows = [];

        $members = Member::where('notice_mail', NoticeMail::Receive->value)->orderBy('id')->get(['name', 'email']);
        $subscribers = NewsletterSubscriber::where('notice_mail', NoticeMail::Receive->value)->orderBy('id')->get(['name', 'email']);

        foreach ($members->concat($subscribers) as $person) {
            $rows[mb_strtolower($person->email)] ??= [(string) $person->name, $person->email];
        }

        return array_values($rows);
    }
}
