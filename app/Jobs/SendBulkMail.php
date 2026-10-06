<?php

namespace App\Jobs;

use App\Mail\TemplatedMail;
use App\Models\BulkMail;
use App\Support\MailUnsubscribe;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

/**
 * 一斉メールを1通送るジョブ。BulkMailControllerが宛先の数だけ1つのバッチに積み、
 * キューのワーカーが裏で順に送る。宛先はこのジョブの中にだけあり、DBの表には残さない。
 *
 * ■ 送る速さ
 * RateLimitedのミドルウェアで、AppServiceProviderのbulk-mailの制限を超えないようにする。
 * 超えた分は送らずにキューへ戻され、少し後にまた試される。SMTPの送信の上限を超えて
 * 止められたり、迷惑メールと判定されたりしないため。
 *
 * ■ 配信停止
 * 本文の末尾に、この宛先の配信停止のURLを付ける。付ける文はテンプレート
 * （resources/mail-templates/bulk_mail.blade.php）にある。メールソフトの「登録解除」のボタン用の
 * ヘッダーも付ける（App\Support\MailUnsubscribe）。
 *
 * ■ 送れなかったとき
 * 例外が起きたら、backoffの秒数を空けて試し直す。maxExceptionsの回数を超えたら失敗にし、
 * failed_jobsに残す。速さの制限で戻された回数は失敗に数えないよう、回数ではなく
 * retryUntil()の期限で打ち切る。
 */
class SendBulkMail implements ShouldQueue
{
    use Batchable, Queueable;

    // 例外が起きた後に試し直すまでの秒数。1回目の後は1分、2回目からは5分待つ
    public array $backoff = [60, 300];

    // 例外が起きてよい回数。これを超えたら失敗にする
    public int $maxExceptions = 3;

    public function __construct(
        public int $bulkMailId,
        public string $name,
        public string $email,
    ) {
    }

    // 速さの制限で何度戻されても、この期限までは試し続ける
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    // 送る速さの制限
    public function middleware(): array
    {
        return [new RateLimited('bulk-mail')];
    }

    // 1通を送る。件名と本文の{{$name}}は、この宛先の氏名に置き換える。
    // 配信停止のURLは、宛先ごとに違う
    public function handle(): void
    {
        // 送信が中止されていれば送らない
        if ($this->batch()?->cancelled()) {
            return;
        }

        $bulkMail = BulkMail::findOrFail($this->bulkMailId);

        // 添付ファイルがあれば付ける。表示名が無ければ保存したファイル名にする
        $attachments = [];
        if ($bulkMail->attach_path !== null) {
            $attachments[] = [
                'path' => $bulkMail->attach_path,
                'name' => $bulkMail->attach_origin ?: $bulkMail->attach,
            ];
        }

        $unsubscribeUrl = MailUnsubscribe::url($this->email);

        Mail::send(new TemplatedMail('bulk_mail', [
            'from_mail' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'email' => $this->email,
            'subject' => BulkMail::fillName($bulkMail->subject, $this->name),
            'body' => BulkMail::fillName($bulkMail->body, $this->name),
            'unsubscribe_url' => $unsubscribeUrl,
        ], $attachments, MailUnsubscribe::headers($unsubscribeUrl)));
    }
}
