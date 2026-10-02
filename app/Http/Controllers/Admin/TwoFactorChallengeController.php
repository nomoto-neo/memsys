<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\BackupCodeGenerator;
use App\Support\LoginThrottle;
use App\Support\TrustedDeviceManager;
use App\Support\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 管理ログインの2段階目（TOTP）。AuthSessionController::store()でパスワードの
 * 確認まで済んだ（が、まだAuth::login()はしていない）状態を受けて、
 * ここで「QRコードを読み取ってもらう（未登録の場合）」または「6桁の
 * コードを入力してもらう（登録済みの場合）」を行い、成功して初めて
 * 実際にログインさせる。
 *
 * 「パスワード確認済み・2段階目が未完了」という中間状態は、
 * Auth::check()では判定できない（まだ本ログインしていないため）。
 * そのためこの2つのアクションだけは、routes/web.php上でguest:adminにも
 * auth:adminにも属さない独立したルートにしてあり、代わりにセッションの
 * PENDING_SESSION_KEYの有無をこのコントローラー自身でチェックしている
 * （/contactのconfirm_tokenと同じ考え方）。
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * パスワード確認済みのスタッフIDを一時的に持たせるセッションキー。
     * AuthSessionController::store()から見えるよう、public constにしている。
     */
    public const PENDING_SESSION_KEY = 'admin.2fa.pending_staff_id';

    /**
     * 「ログイン状態を保持する」チェックボックスの値を、2段階目が
     * 終わるまで一時的に持ち越すためのセッションキー。
     */
    public const REMEMBER_SESSION_KEY = 'admin.2fa.remember';

    /**
     * 初回登録中（まだtotp_confirmed_atが確定していない間）の秘密鍵を
     * 一時的に持たせるセッションキー。画面を再読み込みしてもQRコードが
     * 変わらないようにするためのもの。
     */
    private const PENDING_SECRET_SESSION_KEY = 'admin.2fa.pending_secret';

    /**
     * TOTPコード・バックアップコードの試行制限（LoginThrottle）のカウンターの名前。
     * アカウントはスタッフidで区別する。ログインの2段階目だけでなく、ログイン後の
     * バックアップコード再発行・2段階認証の登録解除・パスキーの登録の前に行う
     * TOTPコードの再確認もこのカウンターで数える。どの画面から入力しても、
     * 当てようとしているのは同じスタッフの同じ秘密鍵なので、失敗回数も1本の
     * カウンターで数える。パスキーの登録（StaffControllerのPASSKEY_THROTTLE_SCOPE）
     * から参照するので、public constにしている。
     */
    public const THROTTLE_SCOPE = 'admin-two-factor';

    public function show(Request $request): View|RedirectResponse
    {
        $staff = $this->pendingStaff($request);

        if ($staff === null) {
            return redirect()->route('admin.login');
        }

        if (! $staff->hasTwoFactorConfirmed()) {
            return $this->showSetup($request, $staff);
        }

        return view('admin.auth.two-factor-challenge', [
            'staff' => $staff,
        ]);
    }

    /**
     * まだ登録が済んでいないスタッフに、QRコードを見せる画面。
     */
    private function showSetup(Request $request, Staff $staff): View
    {
        $secret = $request->session()->get(self::PENDING_SECRET_SESSION_KEY);

        if (! is_string($secret)) {
            $secret = (new TwoFactorAuthenticator())->generateSecret();
            $request->session()->put(self::PENDING_SECRET_SESSION_KEY, $secret);
        }

        // 認証アプリ上で「どのアカウントの鍵か」を見分ける表示名には、
        // 変更のたびに空になり得るemailではなく、必須項目であるlogin_idを使う。
        $qrCodeSvgDataUri = (new TwoFactorAuthenticator())->qrCodeSvgDataUri($secret, $staff->login_id);

        return view('admin.auth.two-factor-setup', [
            'staff' => $staff,
            'secret' => $secret,
            'qrCodeSvgDataUri' => $qrCodeSvgDataUri,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $staff = $this->pendingStaff($request);

        if ($staff === null) {
            return redirect()->route('admin.login');
        }

        if ($staff->hasTwoFactorConfirmed()) {
            return $this->verifyChallenge($request, $staff);
        }

        return $this->verifySetup($request, $staff);
    }

    /**
     * 初回登録の確認。QRコードを読み取った認証アプリが実際に正しい
     * コードを出せているかどうかを、1回コードを入力させて確かめる。
     * 成功したら秘密鍵を本登録し、バックアップコードを発行する。
     */
    private function verifySetup(Request $request, Staff $staff): RedirectResponse
    {
        $secret = $request->session()->get(self::PENDING_SECRET_SESSION_KEY);

        if (! is_string($secret)) {
            // セッション切れ等でsecretが失われている場合は、setup画面からやり直す。
            return redirect()->route('admin.twoFactor.show');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.twoFactor.show')
                ->withErrors(['code' => $throttle->blockedMessage('2段階認証')]);
        }

        if (! (new TwoFactorAuthenticator())->verifyCode($secret, $validated['code'])) {
            $throttle->hit();

            return redirect()->route('admin.twoFactor.show')
                ->withErrors(['code' => '確認コードが正しくありません。認証アプリに表示されている6桁の数字を入力してください。']);
        }

        $throttle->clear();

        // 登録の確定とバックアップコードの発行は、どちらかだけが残らないようにまとめて行う
        $backupCodes = DB::transaction(function () use ($staff, $secret) {
            $staff->totp_secret = $secret;
            $staff->totp_confirmed_at = now();
            $staff->save();

            return (new BackupCodeGenerator())->generateFor($staff);
        });

        $request->session()->forget(self::PENDING_SECRET_SESSION_KEY);

        $this->completeLogin($request, $staff);

        // バックアップコードは、この直後の1回だけ表示できればよい
        // （DBには元の文字列を残していないため、後から見せることはできない）。
        // セッションのflashデータとして次のリクエスト（backupCodes画面）
        // へ渡し、showNewBackupCodes()側でpull()して使い切る。
        return redirect()->route('admin.twoFactor.backupCodes')
            ->with('newBackupCodes', $backupCodes);
    }

    /**
     * 登録済みスタッフの、通常ログイン時の2段階目。TOTPコードか
     * バックアップコードのどちらかで検証する
     * （画面には2つの入力欄・2つのフォームがあり、どちらから送信されたかを
     * backup_codeフィールドの有無で判別する）。
     */
    private function verifyChallenge(Request $request, Staff $staff): RedirectResponse
    {
        $useBackupCode = $request->filled('backup_code');

        // エラーは、送信されてきた方のフォームの入力欄の下に出す。
        $errorField = $useBackupCode ? 'backup_code' : 'code';

        if (! $useBackupCode) {
            $validated = $request->validate([
                'code' => ['required', 'string'],
            ]);
        }

        // TOTPコードとバックアップコードのどちらで試しても、同じスタッフの
        // 同じカウンターで数える（片方ずつ上限まで試せる、ということが無いように）。
        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.twoFactor.show')
                ->withErrors([$errorField => $throttle->blockedMessage('2段階認証')]);
        }

        $ok = $useBackupCode
            ? (new BackupCodeGenerator())->verifyAndConsume($staff, (string) $request->input('backup_code'))
            : (new TwoFactorAuthenticator())->verifyCode($staff->totp_secret, $validated['code']);

        if (! $ok) {
            $throttle->hit();

            return redirect()->route('admin.twoFactor.show')
                ->withErrors([$errorField => $useBackupCode
                    ? 'バックアップコードが正しくないか、既に使用済みです。'
                    : '確認コードが正しくありません。']);
        }

        $throttle->clear();

        // 「この端末を信頼する」にチェックがあれば、次回から30日間、この端末では
        // TOTPの入力を省略する（AuthSessionController::store()で判定する）。
        // TOTPコード・バックアップコードのどちらのフォームにもチェックボックスがある。
        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::forStaff()->remember($staff);
        }

        $this->completeLogin($request, $staff);

        return redirect()->route('admin.dashboard');
    }

    /**
     * 初回登録直後、バックアップコードを一度だけ表示する画面。
     * セッションから取り出す（pull）と同時に消えるので、リロードや
     * 直接URLを叩いた場合は表示できず、ダッシュボードへ戻す。
     */
    public function showNewBackupCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('newBackupCodes');

        if (! is_array($codes)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor-backup-codes', [
            'codes' => $codes,
        ]);
    }

    /**
     * 本人による、バックアップコードの再発行。認証アプリ自体は
     * 使えている（からこそフルログイン済みでこの操作にたどり着けている）が、
     * 控えていたバックアップコードを紛失した・使い切った、という
     * ケースのための自己サービス機能。初回登録時とは異なり、既に
     * ログイン済みの状態から呼ばれるので、今の6桁コードをもう一度
     * 入力させてから実行する（ログイン中のセッションを乗っ取られていた
     * 場合に、それ以上の被害を広げないための、もう一段の本人確認）。
     *
     * 古いコードは全て無効化して10個作り直す
     * （BackupCodeGenerator::generateFor()の仕様。初回登録時と同じ処理）。
     * 表示は初回登録時と同じ「1回だけ見せる」画面をそのまま使い回す。
     */
    public function regenerateBackupCodes(Request $request): RedirectResponse
    {
        $staff = $this->authenticatedStaffWithTwoFactor();

        $validated = $request->validateWithBag('regenerateBackupCodes', [
            'code' => ['required', 'string'],
        ]);

        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')], 'regenerateBackupCodes');
        }

        if (! (new TwoFactorAuthenticator())->verifyCode($staff->totp_secret, $validated['code'])) {
            $throttle->hit();

            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => '確認コードが正しくありません。'], 'regenerateBackupCodes');
        }

        $throttle->clear();

        $codes = (new BackupCodeGenerator())->generateFor($staff);

        return redirect()->route('admin.twoFactor.backupCodes')
            ->with('newBackupCodes', $codes);
    }

    /**
     * 本人による、2段階認証の登録解除。スマートフォンの機種変更などで、
     * 「今はまだ古い端末の認証アプリが使える」うちに、あらかじめ
     * 紐づけを解除しておきたい場合のための自己サービス機能
     * （StaffController::resetTwoFactor()の管理者版と、DBに対して行う
     * 処理の内容自体は同じ）。実行前に今の6桁コードで本人確認する。
     *
     * バックアップコードと、「この端末を信頼する」で信頼済みにした端末、
     * 登録済みのパスキーもすべて無効にする。登録し直すまでは、どの端末からでも
     * QRコードの登録画面を通ることになる。
     *
     * パスキーも消すのは、パスキーでのログインは2段階目（TOTP）を求めないため。
     * パスキーを残すと、TOTPを登録し直さないまま使い続けられ、パスキーを
     * 追加するときの本人確認（TOTP）ができない状態になる。「スタッフのパスキーは
     * TOTPを登録済みの間だけ存在する」という形に揃えている。
     *
     * 解除してもログイン状態そのものは維持される（Auth::guard('admin')の
     * セッションと、DB上のtotp_secretは別物なので、ここでlogout()は
     * 呼ばない）。次回ログイン時にStaff::hasTwoFactorConfirmed()が
     * falseになるので、AuthSessionController::store()からの通常の
     * ログインフローが自動的にQRコード登録画面へ振り分ける。
     *
     * 既にTOTPが使えなくなっている場合（＝このコードを入力する画面
     * 自体にたどり着けない場合）は、この機能では対応できない。その
     * 場合はバックアップコードでログインしてから使うか、それも
     * 尽きていれば管理者リセット（StaffController::resetTwoFactor()）に
     * 頼ることになる——自己サービス化は「まだ大丈夫なうちの保険の
     * 作り直し」であって、「詰んだ後に助ける」ものではない、という
     * 考え方はバックアップコードの再発行と同じ。
     */
    public function selfReset(Request $request): RedirectResponse
    {
        $staff = $this->authenticatedStaffWithTwoFactor();

        $validated = $request->validateWithBag('selfResetTwoFactor', [
            'code' => ['required', 'string'],
        ]);

        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')], 'selfResetTwoFactor');
        }

        if (! (new TwoFactorAuthenticator())->verifyCode($staff->totp_secret, $validated['code'])) {
            $throttle->hit();

            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => '確認コードが正しくありません。'], 'selfResetTwoFactor');
        }

        $throttle->clear();

        DB::transaction(function () use ($staff) {
            $staff->totp_secret = null;
            $staff->totp_confirmed_at = null;
            $staff->save();
            $staff->backupCodes()->delete();
            TrustedDeviceManager::forStaff()->forgetAll($staff);
            $staff->passkeys()->delete();
        });

        return redirect()->route('admin.staff.show', $staff)
            ->with('status', '2段階認証の登録を解除しました。次回ログイン時にQRコードから登録し直せます。');
    }

    /**
     * regenerateBackupCodes()・selfReset()共通の前提チェック。
     * どちらもroutes/web.php側でauth:adminミドルウェアの内側（＝既に
     * Auth::guard('admin')->login()済み）にしか置いていない前提だが、
     * 「2段階認証が未登録のスタッフには意味を持たない操作」という
     * 業務上の前提も、念のためここで二重にチェックしている。
     *
     * Auth::guard('admin')->user()の戻り値は型としてはAuthenticatable|nullだが、
     * config/auth.phpのadminsプロバイダはStaffモデルしか返さないため、
     * abort_if()でnullを除外した後はStaffとして扱ってよい。
     */
    private function authenticatedStaffWithTwoFactor(): Staff
    {
        $staff = Auth::guard('admin')->user();

        abort_if($staff === null || ! $staff->hasTwoFactorConfirmed(), 404);

        return $staff;
    }

    /**
     * TOTPコード・バックアップコードの照合に使う試行制限。
     * ログインの2段階目・バックアップコード再発行・登録解除のどこから
     * 入力しても、同じスタッフの同じカウンターで数える（THROTTLE_SCOPE参照）。
     */
    private function throttle(Request $request, Staff $staff): LoginThrottle
    {
        return new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $staff->id);
    }

    /**
     * 2段階目まで通過した時点で、初めて実際にログイン状態にする。
     * パスワード確認の時点（AuthSessionController::store()）はまだ
     * 「本ログイン」ではないので、セッション固定化攻撃対策の
     * regenerate()も、本ログインのこのタイミングで行う。
     */
    private function completeLogin(Request $request, Staff $staff): void
    {
        $remember = (bool) $request->session()->pull(self::REMEMBER_SESSION_KEY, false);

        Auth::guard('admin')->login($staff, $remember);

        $request->session()->forget(self::PENDING_SESSION_KEY);
        $request->session()->regenerate();
    }

    private function pendingStaff(Request $request): ?Staff
    {
        $id = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        return Staff::find($id);
    }
}
