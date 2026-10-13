<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginRedirect;
use Neobit\Core\Auth\LoginThrottle;
use Neobit\Core\Auth\PasskeyLogin;
use Neobit\Core\Auth\TwoFactorLogin;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Operations\OperationRecorder;

/**
 * 管理画面のログイン・ログアウト。
 *
 * ログインは2段階で、ログインIDとパスワードが合っても、すぐにはログインさせない。
 * 2段階目の方式（Staff::TWO_FACTOR_METHOD）で確かめてから、本ログインにする。
 * そのため、ここでは確認だけを行うAuth::validate()を使い、Auth::attempt()は使わない。
 * パスワードが合った後の流れ（2段階目・信頼済みの端末・ログインの確定）は、TwoFactorLoginトレイトにある。
 */
class AuthSessionController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // パスワードが合った後の流れ（continueAfterPassword()）、2段階目の画面と照合
    // （showVerification()・verifyCode()・resendCode()）、ログアウト（logoutAccount()）。
    use TwoFactorLogin;

    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。パスキーで
    // 通ったときは、2段階目も求めない（Neobit\Core\Auth\PasskeyLogin参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのadmin.login.passkeyのルートを消す。
    use PasskeyLogin;

    // ---- ログイン（TwoFactorLogin）の設定 ----

    /** ログインする人のモデル。ガード・ルート・2段階目の方式は、ここから決まる。 */
    private const LOGIN_ACCOUNT = Staff::class;

    /** 2段階目の画面のビュー。 */
    private const TWO_FACTOR_VIEW = 'admin.auth.login-verify';

    // ---- パスキーでのログイン（PasskeyLogin）の設定 ----

    /** パスキーでログインさせるガード（Neobit\Core\Auth\PasskeyLogin参照）。 */
    private const PASSKEY_GUARD = 'admin';

    /**
     * ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
     * アカウントはログインIDで区別する。
     */
    private const THROTTLE_SCOPE = 'admin-login';

    /** ログインフォームの表示 */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /** ログイン（1段階目：ログインID・パスワード） */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・ログインID単位。Neobit\Core\Auth\LoginThrottle）
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

            // 操作ログ。誰か分からないので、入力されたログインIDを補足に、ログインの種類を対象に残す
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['login_id' => $credentials['login_id']], targetClass: self::LOGIN_ACCOUNT);

            return redirect()->route('admin.login')
                ->withErrors(['login_id' => 'ログインIDまたはパスワードが正しくありません。'])
                ->withInput($request->except('password'));
        }

        $throttle->clear();

        // 2段階目が要らなければそのままログイン、要るなら2段階目の画面へ。
        // 確かめたスタッフは、validate()が内部に持っている
        return $this->continueAfterPassword($request, Auth::guard('admin')->getLastAttempted(), $remember);
    }

    /**
     * ログインが確定した後の移動先。ログインが必要な画面から来た場合はその画面へ、
     * そうでなければ管理画面TOPへ（Neobit\Core\Auth\LoginRedirect）。
     */
    private function loginRedirectUrl(): string
    {
        return LoginRedirect::for(self::LOGIN_ACCOUNT, route('admin.dashboard'));
    }

    /** パスキーでログインした後の移動先。ログインID・パスワードでのログインと同じ */
    private function passkeyRedirectUrl(): string
    {
        return $this->loginRedirectUrl();
    }

    /** ログアウト */
    public function destroy(Request $request): RedirectResponse
    {
        // 管理画面のログインだけを終わらせる。同じブラウザの会員のログインは残す
        $this->logoutAccount($request);

        return redirect()->route('admin.login');
    }
}
