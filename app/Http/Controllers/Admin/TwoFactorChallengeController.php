<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OperationLogAction;
use App\Support\OperationRecorder;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\BackupCodeGenerator;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\TrustedDeviceManager;
use App\Support\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 管理ログインの2段階目と、ログイン後の2段階認証の管理。
 *
 * パスワードの確認が済んだスタッフに、未登録ならQRコードを読み取ってもらい、登録済みなら
 * 認証アプリのコードかバックアップコードを入力してもらう。通ったら本ログインにする。
 * 2段階目の途中はAuth::check()では見分けられないので、ルートはguestにもauthにも入れず、
 * このコントローラーがセッションの値で守る。
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
     * 認証アプリのコードとバックアップコードの、試行制限のカウンターの名前。アカウントは
     * スタッフidで区別する。ログインの2段階目だけでなく、ログイン後の再確認でも同じ
     * カウンターで数える。どこから入力しても、当てようとしているのは同じ秘密鍵だから。
     * パスキーの登録（StaffController）からも使うので、publicにしている。
     */
    public const THROTTLE_SCOPE = 'admin-two-factor';

    /** 2段階目の画面（未登録ならQRコードの登録、登録済みならコードの入力） */
    public function show(Request $request): View|RedirectResponse
    {
        $staff = $this->pendingStaff($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($staff === null) {
            return redirect()->route('admin.login');
        }

        // 2段階認証が未登録なら、QRコードの登録画面
        if (! $staff->hasTwoFactorConfirmed()) {
            return $this->showSetup($request, $staff);
        }

        return view('admin.auth.two-factor-challenge', [
            'staff' => $staff,
        ]);
    }

    /** まだ登録が済んでいないスタッフに、QRコードを見せる画面。 */
    private function showSetup(Request $request, Staff $staff): View
    {
        // 秘密鍵は、初めて開いたときに作ってセッションに置く（再読み込みしても変わらないように）
        $secret = $request->session()->get(self::PENDING_SECRET_SESSION_KEY);

        if (! is_string($secret)) {
            $secret = (new TwoFactorAuthenticator())->generateSecret();
            $request->session()->put(self::PENDING_SECRET_SESSION_KEY, $secret);
        }

        // 認証アプリに出るアカウント名は、空になりうるemailではなく、必須のlogin_idにする
        $qrCodeSvgDataUri = (new TwoFactorAuthenticator())->qrCodeSvgDataUri($secret, $staff->login_id);

        return view('admin.auth.two-factor-setup', [
            'staff' => $staff,
            'secret' => $secret,
            'qrCodeSvgDataUri' => $qrCodeSvgDataUri,
        ]);
    }

    /** 2段階目の照合（未登録なら初回登録の確認、登録済みならコードの照合） */
    public function verify(Request $request): RedirectResponse
    {
        $staff = $this->pendingStaff($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
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

        // セッション切れなどで秘密鍵が無くなっていたら、QRコードの登録画面からやり直す
        if (! is_string($secret)) {
            return redirect()->route('admin.twoFactor.show');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（THROTTLE_SCOPE参照）
        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.twoFactor.show')
                ->withErrors(['code' => $throttle->blockedMessage('2段階認証')]);
        }

        // 認証アプリが正しいコードを出せているか、1回入力してもらって確かめる
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

        // バックアップコードは、この直後の1回だけ見せる（DBには元の文字列を残していないので、
        // 後からは見せられない）。次のリクエストへセッションで渡し、showNewBackupCodes()で使い切る
        return redirect()->route('admin.twoFactor.backupCodes')
            ->with('newBackupCodes', $backupCodes);
    }

    /**
     * 登録済みのスタッフの、ログインの2段階目。認証アプリのコードか、バックアップコードで
     * 確かめる。画面には2つのフォームがあり、backup_codeが送られてきたかで見分ける。
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

        // 失敗回数による試行制限。TOTPコードとバックアップコードのどちらで試しても、
        // 同じカウンターで数える（片方ずつ上限まで試せる、ということが無いように）
        $throttle = $this->throttle($request, $staff);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.twoFactor.show')
                ->withErrors([$errorField => $throttle->blockedMessage('2段階認証')]);
        }

        if ($useBackupCode) {
            // バックアップコードの照合（通ったコードは使用済みにする）
            $ok = (new BackupCodeGenerator())->verifyAndConsume($staff, (string) $request->input('backup_code'));
            $failedMessage = 'バックアップコードが正しくないか、既に使用済みです。';
        } else {
            // 認証アプリの6桁のコードの照合
            $ok = (new TwoFactorAuthenticator())->verifyCode($staff->totp_secret, $validated['code']);
            $failedMessage = '確認コードが正しくありません。';
        }

        if (! $ok) {
            $throttle->hit();

            // 操作ログ。パスワードは通っているので、誰の失敗かが分かる
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['step' => '2段階目'], operator: $staff);

            return redirect()->route('admin.twoFactor.show')
                ->withErrors([$errorField => $failedMessage]);
        }

        $throttle->clear();

        // 「この端末を信頼する」にチェックがあれば、次回から30日間、この端末ではTOTPを省く
        // （判定はAuthSessionController::store()。どちらのフォームにもチェックボックスがある）
        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::forStaff()->remember($staff);
        }

        $this->completeLogin($request, $staff);

        // ログインが必要な画面から来た場合はその画面へ、そうでなければ管理画面TOPへ
        // （App\Support\LoginRedirect）
        return redirect(LoginRedirect::forStaff());
    }

    /**
     * 発行した直後のバックアップコードを、一度だけ表示する画面。
     * セッションから取り出すと同時に消えるので、再読み込みや直接URLを開いたときは、
     * ダッシュボードへ戻す。
     */
    public function showNewBackupCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('newBackupCodes');

        // もう見せた後なら、ダッシュボードへ
        if (! is_array($codes)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor-backup-codes', [
            'codes' => $codes,
        ]);
    }

    /**
     * 本人による、バックアップコードの再発行。控えを無くした・使い切ったときのためのもの。
     *
     * ログイン中でも、今の認証アプリのコードをもう一度入力してもらってから行う。
     * ログイン中のセッションを乗っ取られていたときに、被害を広げないため。
     * 古いコードはすべて無効にし、作り直したコードを1回だけ表示する。
     */
    public function regenerateBackupCodes(Request $request): RedirectResponse
    {
        $staff = $this->authenticatedStaffWithTwoFactor();

        $validated = $request->validateWithBag('regenerateBackupCodes', [
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（THROTTLE_SCOPE参照）
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

        // 作り直して、1回だけ見せる画面へ
        $codes = (new BackupCodeGenerator())->generateFor($staff);

        return redirect()->route('admin.twoFactor.backupCodes')
            ->with('newBackupCodes', $codes);
    }

    /**
     * 本人による、2段階認証の登録解除。スマートフォンの機種変更などで、古い端末の
     * 認証アプリがまだ使えるうちに解除しておくためのもの。実行の前に、今のコードで本人確認する。
     *
     * バックアップコード・信頼済みの端末・パスキーも、すべて無効にする。パスキーでの
     * ログインは2段階目を求めないので、残すとTOTPを登録し直さないまま使えてしまうため。
     * ログイン状態はそのままで、次のログインでQRコードの登録画面へ進む。
     */
    public function selfReset(Request $request): RedirectResponse
    {
        $staff = $this->authenticatedStaffWithTwoFactor();

        $validated = $request->validateWithBag('selfResetTwoFactor', [
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（THROTTLE_SCOPE参照）
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
            // TOTPの登録を消す
            $staff->totp_secret = null;
            $staff->totp_confirmed_at = null;
            $staff->save();

            // バックアップコード・信頼済み端末・パスキーも失効させる
            $staff->backupCodes()->delete();
            TrustedDeviceManager::forStaff()->forgetAll($staff);
            $staff->passkeys()->delete();
        });

        return redirect()->route('admin.staff.show', $staff)
            ->with('status', '2段階認証の登録を解除しました。次回ログイン時にQRコードから登録し直せます。');
    }

    /**
     * regenerateBackupCodes()・selfReset()に共通の前提の確認。どちらもauth:adminの内側の
     * ルートなのでログイン済みだが、2段階認証が未登録のスタッフには意味の無い操作なので、
     * 念のためここでも確かめる。adminガードはStaffしか返さないので、nullを除けばStaffとして扱える。
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
     * 2段階目まで通った時点で、初めて本ログインにする。セッション固定攻撃への対策の
     * regenerate()も、このときに行う（パスワードを確かめた時点はまだ本ログインではないため）。
     */
    private function completeLogin(Request $request, Staff $staff): void
    {
        // 1段階目で控えた「ログイン状態を保持する」を取り出して使う
        $remember = (bool) $request->session()->pull(self::REMEMBER_SESSION_KEY, false);

        Auth::guard('admin')->login($staff, $remember);

        $request->session()->forget(self::PENDING_SESSION_KEY);
        $request->session()->regenerate();
    }

    /** パスワード確認済みで、2段階目を待っているスタッフ（いなければnull） */
    private function pendingStaff(Request $request): ?Staff
    {
        $id = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        return Staff::find($id);
    }
}
