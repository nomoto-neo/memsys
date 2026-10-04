<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\OperationRecorder;
use App\Support\PasskeyLogin;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 管理画面のログイン・ログアウト。
 *
 * ログインは2段階で、ログインIDとパスワードが合っても、すぐにはログインさせない。
 * 認証アプリのコードを入力してもらい、TwoFactorChallengeControllerで本ログインにする。
 * そのため、ここでは確認だけを行うAuth::validate()を使い、Auth::attempt()は使わない。
 * 「この端末を信頼する」を選んだ端末では、認証アプリのコードを省く。
 */
class AuthSessionController extends Controller
{
    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。パスキーで
    // 通ったときは、2段階目（TOTP）も求めない（App\Support\PasskeyLogin参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのadmin.login.passkeyのルートを消す。
    use PasskeyLogin;

    // パスキーでログインさせるガード（App\Support\PasskeyLogin参照）。
    private const PASSKEY_GUARD = 'admin';

    // ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
    // アカウントはログインIDで区別する。
    private const THROTTLE_SCOPE = 'admin-login';

    // ログインフォームの表示
    public function create(): View
    {
        return view('admin.auth.login');
    }

    // ログイン（1段階目：ログインID・パスワード）
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・ログインID単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['login_id']);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.login')
                ->withErrors(['login_id' => $throttle->blockedMessage('ログイン')])
                ->withInput($request->except('password'));
        }

        // 「ログイン状態を保持する」のチェック（チェックが無ければfalse）
        $remember = $request->boolean('remember');

        // ログインID・パスワードの確認だけ行う（まだログインはしない）
        if (! Auth::guard('admin')->validate($credentials)) {
            $throttle->hit();

            // 操作ログ。誰か分からないので、入力されたログインIDを補足に残す
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['login_id' => $credentials['login_id']]);

            return redirect()->route('admin.login')
                ->withErrors(['login_id' => 'ログインIDまたはパスワードが正しくありません。'])
                ->withInput($request->except('password'));
        }

        $throttle->clear();

        // validate()で確かめたスタッフ（validate()が内部に持っている）
        $staff = Auth::guard('admin')->getLastAttempted();

        // 2段階認証を登録済みで、信頼済みの端末なら、TOTPを省いてログインを完了する
        if ($staff->hasTwoFactorConfirmed() && TrustedDeviceManager::forStaff()->isTrusted($staff, $request)) {
            Auth::guard('admin')->login($staff, $remember);
            $request->session()->regenerate();

            // ログインが必要な画面から来た場合はその画面へ、そうでなければ管理画面TOPへ
            return redirect(LoginRedirect::forStaff());
        }

        // それ以外は、「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ
        // （本ログインはTwoFactorChallengeController::completeLogin()）
        $request->session()->put(TwoFactorChallengeController::PENDING_SESSION_KEY, $staff->id);
        $request->session()->put(TwoFactorChallengeController::REMEMBER_SESSION_KEY, $remember);

        return redirect()->route('admin.twoFactor.show');
    }

    // パスキーでログインした後の移動先。ログインID・パスワードでのログインと同じく、
    // ログインが必要な画面から来た場合はその画面へ戻す（App\Support\LoginRedirect）。
    private function passkeyRedirectUrl(): string
    {
        return LoginRedirect::forStaff();
    }

    // ログアウト
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        // セッションを破棄し、CSRFトークンも作り直す
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
