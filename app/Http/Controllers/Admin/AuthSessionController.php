<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LoginThrottle;
use App\Support\PasskeyLogin;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthSessionController extends Controller
{
    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。パスキーで
    // 通ったときは、2段階目（TOTP）も求めない（App\Support\PasskeyLogin参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのadmin.login.passkeyのルートを消す。
    use PasskeyLogin;

    // ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
    // アカウントはログインIDで区別する。
    private const THROTTLE_SCOPE = 'admin-login';

    // パスキーでログインさせるガード（App\Support\PasskeyLogin参照）。
    private const PASSKEY_GUARD = 'admin';

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・ログインID単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['login_id']);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.login')
                ->withErrors(['login_id' => $throttle->blockedMessage('ログイン')])
                ->withInput($request->except('password'));
        }

        // 会員側（AuthSessionController）と同じ「ログイン状態を保持する」
        // チェックボックス。$request->boolean('remember')は、チェックボックスが
        // 送られてこなかった（未チェック）ときはfalseになる（$request->input()と
        // 違い、キー自体が無くてもエラーにならず安全にfalse扱いにしてくれる）。
        $remember = $request->boolean('remember');

        // ★attempt()ではなくvalidate()を使っている★
        // attempt()は「credentialsが正しければその場でログイン状態にする
        // （セッション再生成まで含めて）」というメソッド。ここでは
        // パスワードの正誤だけを確認し、2段階目（TOTP）まで通過して
        // 初めて本ログインとしたいので、あえて「正誤の判定だけ行い、
        // ログインはしない」validate()を使っている
        // （Illuminate\Auth\SessionGuard::validate()のソースで、
        // login()もsetUser()もupdateSession()も呼ばないことを確認済み）。
        if (! Auth::guard('admin')->validate($credentials)) {
            $throttle->hit();

            return redirect()->route('admin.login')
                ->withErrors(['login_id' => 'ログインIDまたはパスワードが正しくありません。'])
                ->withInput($request->except('password'));
        }

        $throttle->clear();

        // validate()が成功すると、内部でlastAttemptedに解決済みのユーザーを
        // 保持しているので、getLastAttempted()で取り出せる
        // （SessionGuard::validate()のソースで確認済み）。
        $staff = Auth::guard('admin')->getLastAttempted();

        // 2段階認証を登録済みで、この端末を「信頼する」済みなら、TOTPの入力を
        // 省略してそのままログインを完了させる。省略できるのはTOTPだけで、
        // ログインID・パスワードの確認は上で毎回行っている。
        // 未登録（QRコードの登録がまだ）の場合は、信頼済みかどうかに関係なく
        // 登録画面へ進ませる。
        if ($staff->hasTwoFactorConfirmed() && TrustedDeviceManager::forStaff()->isTrusted($staff, $request)) {
            Auth::guard('admin')->login($staff, $remember);
            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        // 「パスワードは合っているが2段階目（TOTP）が未完了」という
        // 中間状態をセッションに記録する。実際のAuth::login()は
        // TwoFactorChallengeController::completeLogin()側で、2段階目が
        // 通過してから行う。
        $request->session()->put(TwoFactorChallengeController::PENDING_SESSION_KEY, $staff->id);
        $request->session()->put(TwoFactorChallengeController::REMEMBER_SESSION_KEY, $remember);

        return redirect()->route('admin.twoFactor.show');
    }

    // パスキーでログインした後の移動先。ログインID・パスワードでのログインと同じく
    // 管理画面TOPへ（会員側と違い、元の画面へは戻さない）。
    private function passkeyRedirectUrl(): string
    {
        return route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
