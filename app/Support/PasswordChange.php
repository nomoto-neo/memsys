<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use App\Mail\TemplatedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * パスワードを変えたときの後始末。会員とスタッフのパスワードを変える処理は、どれも
 * パスワードを保存した直後にresetAndNotify()を呼ぶ。相手は、ログインする人の共通の型
 * （LoginAccount）で受け取る。
 *
 * ■ ほかのログインの手段を無効にする
 * 信頼済みの端末とパスキーをすべて無効にする。乗っ取りを疑ってパスワードを変えたときに、
 * 他人が登録した端末やパスキーから入れる状態を残さないため。理由を問わず同じにしているので、
 * 「パスワードを変えたらほかのログインの手段はすべてリセットされる」と説明できる。
 * 認証アプリの登録は消さない。秘密鍵は本人のスマートフォンにあり、
 * パスワードとは別のものだから。ほかの端末のログインはルートのauth.sessionが解除する。
 *
 * ■ お知らせのメール
 * 本人以外が変えたときに気付けるよう、登録されているメールアドレスへお知らせを送る。
 * メールアドレスが無い人には送らない。メールは保存が確定した後に送り、
 * 送れなくてもパスワードの変更は取り消さずにログにだけ残す。
 * テンプレートは、その人の種類の「種類の名前_password_changed」（LoginAccount::loginMailTemplate()）。
 * テンプレートに渡す値：
 * - name・login_id        宛名と、ログインに使う値
 * - changed_at            変えた日時
 * - changed_by_other      本人以外が変えたか。changed_by_nameは、変えた人の名前
 * - passkeys_deleted      パスキーを消したか
 * - login_url             その種類のログイン画面
 * - reset_url             その種類のパスワードの再設定の画面。そのルートが無い種類では空
 */
class PasswordChange
{
    /**
     * @param  LoginAccount  $owner  パスワードを変えたアカウント
     * @param  LoginAccount|null  $changedBy  本人の代わりに変えた人。管理画面から変えたスタッフなど。
     *                                        本人が変えたときはnull。お知らせのメールの文面に使う
     * @return int  削除したパスキーの件数。画面のメッセージに使う
     */
    public static function resetAndNotify(LoginAccount $owner, ?LoginAccount $changedBy = null): int
    {
        // 信頼済みの端末とパスキーを無効にする
        TrustedDeviceManager::for($owner)->forgetAll($owner);

        $deletedPasskeys = $owner->passkeys()->delete();

        // 既存のシステムから移した会員の、古い方式のパスワードが残っていれば消す。新しいパスワードを
        // 決めたので、もう要らない（App\Support\LegacyPasswordUserProvider）
        if ($owner->legacy_password !== null) {
            $owner->forceFill(['legacy_password' => null])->save();
        }

        // 操作ログ。本人が変えたときは、ログインの前（パスワードの再設定）でも本人を操作した人にする
        OperationRecorder::record(OperationLogAction::PasswordChange, $owner, operator: $changedBy ?? $owner);

        // お知らせのメールは保存が確定した後に送る。トランザクションの外ならその場で送る
        DB::afterCommit(fn () => self::sendMail($owner, $changedBy, $deletedPasskeys > 0));

        return $deletedPasskeys;
    }

    /** パスワードが変わったことのお知らせのメール */
    private static function sendMail(LoginAccount $owner, ?LoginAccount $changedBy, bool $passkeysDeleted): void
    {
        $email = $owner->notificationEmail();

        // メールアドレスが無ければ送らない
        if (empty($email)) {
            return;
        }

        // 本人が自分で変えたのではなく、ほかの人が変えたか
        $byOther = $changedBy !== null && ! $changedBy->is($owner);

        // パスワードの再設定の画面は、その種類にルートがあるときだけ案内する
        $resetRoute = $owner::loginRoute('password.forgot');

        $variables = [
            'from_mail' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to_mail' => $email,
            'name' => $owner->displayName(),
            'login_id' => $owner->loginId(),
            'changed_at' => now()->format('Y年n月j日 H:i'),
            'changed_by_other' => $byOther,
            'changed_by_name' => $byOther ? $changedBy->displayName() : '',
            'passkeys_deleted' => $passkeysDeleted,
            'login_url' => route($owner::loginRoute('login')),
            'reset_url' => Route::has($resetRoute) ? route($resetRoute) : '',
        ];

        try {
            Mail::send(new TemplatedMail($owner::loginMailTemplate('password_changed'), $variables));
        } catch (\Throwable $e) {
            Log::error('PasswordChange: パスワード変更のお知らせメールの送信に失敗しました。', [
                'type' => $owner->getMorphClass(),
                'id' => $owner->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
