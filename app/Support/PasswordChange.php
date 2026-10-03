<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * パスワードを変えたときの後始末。会員とスタッフのパスワードを変える処理は、どれも
 * パスワードを保存した直後にresetAndNotify()を呼ぶ。
 *
 * ■ ほかのログインの手段を無効にする
 * 信頼済みの端末とパスキーをすべて無効にする。乗っ取りを疑ってパスワードを変えたときに、
 * 他人が登録した端末やパスキーから入れる状態を残さないため。理由を問わず同じにしているので、
 * 「パスワードを変えたらほかのログインの手段はすべてリセットされる」と説明できる。
 * スタッフの認証アプリの登録は消さない。秘密鍵は本人のスマートフォンにあり、
 * パスワードとは別のものだから。ほかの端末のログインはルートのauth.sessionが解除する。
 *
 * ■ お知らせのメール
 * 本人以外が変えたときに気付けるよう、登録されているメールアドレスへお知らせを送る。
 * スタッフはメールアドレスが任意なので、空なら送らない。メールは保存が確定した後に送り、
 * 送れなくてもパスワードの変更は取り消さずにログにだけ残す。
 */
class PasswordChange
{
    /**
     * @param  Member|Staff  $owner  パスワードを変えたアカウント
     * @param  Staff|null  $changedBy  管理画面から変えたスタッフ。本人がマイページなどから
     *                                 変えたときはnull。お知らせのメールの文面に使う
     * @return int  削除したパスキーの件数。画面のメッセージに使う
     */
    public static function resetAndNotify(Member|Staff $owner, ?Staff $changedBy = null): int
    {
        // 信頼済みの端末とパスキーを無効にする
        $manager = $owner instanceof Staff ? TrustedDeviceManager::forStaff() : TrustedDeviceManager::forMember();
        $manager->forgetAll($owner);

        $deletedPasskeys = $owner->passkeys()->delete();

        // お知らせのメールは保存が確定した後に送る。トランザクションの外ならその場で送る
        DB::afterCommit(fn () => self::sendMail($owner, $changedBy, $deletedPasskeys > 0));

        return $deletedPasskeys;
    }

    // パスワードが変わったことのお知らせのメール
    private static function sendMail(Member|Staff $owner, ?Staff $changedBy, bool $passkeysDeleted): void
    {
        // メールアドレスが無ければ送らない
        if (empty($owner->email)) {
            return;
        }

        // 本人が自分で変えたのではなく、管理画面のスタッフが変えたか
        $byOther = $changedBy !== null && ! ($owner instanceof Staff && $changedBy->id === $owner->id);

        $variables = [
            'from_mail' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to_mail' => $owner->email,
            'name' => $owner->name,
            'changed_at' => now()->format('Y年n月j日 H:i'),
            'changed_by_other' => $byOther,
            'passkeys_deleted' => $passkeysDeleted,
        ];

        // スタッフと会員でテンプレートと案内のURLを変える
        if ($owner instanceof Staff) {
            $template = 'staff_password_changed';
            $variables += [
                'login_id' => $owner->login_id,
                'changed_by_name' => $byOther ? $changedBy->name : '',
                'login_url' => route('admin.login'),
            ];
        } else {
            $template = 'member_password_changed';
            $variables += [
                'reset_url' => route('password.forgot'),
                'contact_url' => route('contact.create'),
            ];
        }

        try {
            Mail::send(new TemplatedMail($template, $variables));
        } catch (\Throwable $e) {
            Log::error('PasswordChange: パスワード変更のお知らせメールの送信に失敗しました。', [
                'type' => $owner->getMorphClass(),
                'id' => $owner->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
