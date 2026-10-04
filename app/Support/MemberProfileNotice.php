<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 会員情報が変わったことを、本人へメールで知らせる。マイページと管理画面の会員の保存が、
 * FormFlowのafterSave()から呼ぶ。
 *
 * 本人が変えたときは、変えた記録が本人の手元にも残る。本人以外が変えたときは、本人が気付ける。
 * メールには、変わったことだけを書く。どの項目が変わったかと、その値は載せない。
 * パスワードの変更は、PasswordChangeが別のメールで知らせるので、ここでは扱わない。
 * メールアドレスが変わったときは、変わる前と後の両方のアドレスに送る。他人にアドレスを
 * 書き換えられたときに、本人が気付けるのは変わる前のアドレスだけだから。
 * メールは保存が確定した後に送り、送れなくても変更は取り消さずにログにだけ残す。
 */
final class MemberProfileNotice
{
    // 変わっても知らせない列。パスワードは別のメールで知らせる
    private const SILENT_FIELDS = ['password'];

    /**
     * @param  array  $changedFields  値が変わった列の、列の名前 => 変わる前の値。FormFlowがafterSave()に渡すもの
     * @param  Staff|null  $changedBy  管理画面から変えたスタッフ。本人がマイページから変えたときはnull
     */
    public static function send(Member $member, array $changedFields, ?Staff $changedBy): void
    {
        $fields = array_diff(array_keys($changedFields), self::SILENT_FIELDS);

        // 知らせる項目が無ければ送らない。何も変わっていないときと、パスワードだけが変わったとき
        if ($fields === []) {
            return;
        }

        $variables = [
            'from_mail' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'name' => $member->name,
            'changed_at' => now()->format('Y年n月j日 H:i'),
            'changed_by_other' => $changedBy !== null,
            'reset_url' => route('password.forgot'),
            'contact_url' => route('contact.create'),
        ];

        // 宛先。メールアドレスが変わったときは、変わる前のアドレスにも送る
        $recipients = array_unique(array_filter([$member->email, $changedFields['email'] ?? null]));

        // 保存が確定した後に送る
        DB::afterCommit(function () use ($member, $variables, $recipients) {
            foreach ($recipients as $recipient) {
                try {
                    Mail::send(new TemplatedMail('member_profile_changed', ['to_mail' => $recipient] + $variables));
                } catch (\Throwable $e) {
                    Log::error('MemberProfileNotice: 会員情報変更のお知らせメールの送信に失敗しました。', [
                        'member_id' => $member->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
