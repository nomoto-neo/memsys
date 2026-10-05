<?php

namespace App\Http\Controllers;

use App\Enums\OperationLogAction;
use App\Models\Member;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\MemberLogin;
use App\Support\OperationRecorder;
use App\Support\PasskeyLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 個人会員のログイン・ログアウト。
 *
 * ログインは2段階で、メールアドレスとパスワードが合っても、すぐにはログインさせない。
 * メールで送る確認コードを入力してもらってから、本ログインにする。
 * そのため、ここでは確認だけを行うAuth::validate()を使い、Auth::attempt()は使わない。
 * パスワードが合った後の流れ（確認コードの送信・照合・記憶済みの端末）は、MemberLoginトレイトにある。
 */
class AuthSessionController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // パスワードが合った後の流れ（continueAfterPassword()）、確認コードの入力画面と照合
    // （showVerification()・verifyCode()・resendCode()）、ログアウト（logoutMember()）。
    use MemberLogin;

    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。
    // 使わないサイトでは、このuseとroutes/web.phpのlogin.passkeyのルートを消す。
    use PasskeyLogin;

    // ---- ログイン（MemberLogin）の設定 ----

    // ログインする会員のモデル。ガード・ルート・メールのテンプレートの名前は、ここから決まる。
    private const MEMBER_CLASS = Member::class;

    // 確認コードの入力画面のビュー。
    private const LOGIN_VERIFY_VIEW = 'auth.login-verify';

    // ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
    // アカウントはメールアドレスで区別する。
    private const THROTTLE_SCOPE = 'member-login';

    // ---- パスキーでのログイン（PasskeyLogin）の設定 ----

    // パスキーでログインさせるガード（App\Support\PasskeyLogin参照）。
    private const PASSKEY_GUARD = 'web';

    // ---- ログイン・ログアウト ----

    // ログインフォームの表示
    public function create(): View
    {
        return view('auth.login');
    }

    // ログイン（1段階目：メールアドレス・パスワード）
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['email']);

        if ($throttle->isBlocked()) {
            throw ValidationException::withMessages([
                'email' => $throttle->blockedMessage('ログイン'),
            ]);
        }

        // メールアドレス・パスワードの確認だけ行う（まだログインはしない）
        $guard = Auth::guard(self::MEMBER_CLASS::memberGuard());

        if (! $guard->validate($credentials)) {
            $throttle->hit();

            // 操作ログ。誰か分からないので、入力されたメールアドレスを補足に残す
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['login_id' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $throttle->clear();

        // 記憶済みの端末ならそのままログイン、そうでなければ確認コードの入力へ。
        // 「ログイン状態を保持する」のチェックも渡す（チェックが無ければfalse）
        return $this->continueAfterPassword($request, $guard->getLastAttempted(), $request->boolean('remember'));
    }

    // パスキーでログインした後の移動先。メールアドレス・パスワードでのログインと同じく、
    // ログインが必要な画面から来た場合はその画面へ戻す（App\Support\LoginRedirect）。
    private function passkeyRedirectUrl(): string
    {
        return LoginRedirect::forMember(self::MEMBER_CLASS);
    }

    // ログアウト
    public function destroy(Request $request): RedirectResponse
    {
        $this->logoutMember($request);

        return redirect('/');
    }
}
