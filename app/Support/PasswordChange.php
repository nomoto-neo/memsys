<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * パスワードを変えたときの後始末。会員・スタッフのパスワードを変える処理は、
 * どれもパスワードを保存した直後にresetAndNotify()を呼ぶ。
 * - 会員：パスワードの再設定（PasswordResetController）・マイページのパスワード変更
 *   （AuthPasswordController）・管理画面での変更（Admin\MemberController）
 * - スタッフ：本人・管理者による変更（Admin\StaffController）
 *
 * ■ 認証情報のリセット
 * パスワードを変えたら、パスワードと別に持っているログインの手段をすべて無効にする。
 * - 2段階目を省略する信頼済み端末（「このデバイスを記憶する」「この端末を信頼する」）
 * - パスキー（パスワードも2段階目も求めずにログインできるため）
 * 乗っ取りを疑ってパスワードを変えた場合に、他人が登録した端末やパスキーから
 * 入れる状態を残さないため。変えた理由を問わず同じにしているので、「パスワードを
 * 変えたら、ほかのログインの手段はすべてリセットされる」と説明できる。
 * スタッフの2段階認証（認証アプリのTOTP）は消さない。秘密鍵は本人の
 * スマートフォンの中にあり、パスワードとは別の要素だから。
 * ほかの端末のログイン中のセッションと「ログイン状態を保持する」のCookieは、
 * routes/web.phpのauth.sessionが無効にする。
 *
 * ■ お知らせのメール
 * 登録されているメールアドレスへ「パスワードが変更されました」のメールを送る。
 * 本人以外が変えた場合に、本人が気付けるようにするため。スタッフはメールアドレスが
 * 任意項目なので、空なら送らない。メールは保存のトランザクションが確定した後に
 * 送り（DB::afterCommit()。トランザクションの外で呼ばれたときは、その場で送る）、
 * 送信に失敗してもパスワードの変更は取り消さず、ログにだけ残す。
 */
class PasswordChange
{
    /**
     * @param  Member|Staff  $owner  パスワードを変えたアカウント
     * @param  Staff|null  $changedBy  管理画面から変えたスタッフ（本人がマイページなどから
     *                                 変えたときはnull）。お知らせのメールの文面に使う
     * @return int  削除したパスキーの件数（画面のメッセージに使う）
     */
    public static function resetAndNotify(Member|Staff $owner, ?Staff $changedBy = null): int
    {
        $manager = $owner instanceof Staff ? TrustedDeviceManager::forStaff() : TrustedDeviceManager::forMember();
        $manager->forgetAll($owner);

        $deletedPasskeys = $owner->passkeys()->delete();

        DB::afterCommit(fn () => self::sendMail($owner, $changedBy, $deletedPasskeys > 0));

        return $deletedPasskeys;
    }

    private static function sendMail(Member|Staff $owner, ?Staff $changedBy, bool $passkeysDeleted): void
    {
        if (empty($owner->email)) {
            return;
        }

        // 本人が自分で変えたのではなく、ほかの人（管理画面のスタッフ）が変えたか
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
