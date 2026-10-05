<?php

namespace App\Http\Controllers;

use App\Enums\OperationLogAction;
use App\Models\Member;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\OperationRecorder;
use App\Support\MemberVerificationCode;
use App\Support\PasskeyLogin;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 会員のログイン・ログアウト。
 *
 * ログインは2段階で、メールアドレスとパスワードが合っても、すぐにはログインさせない。
 * メールで送る確認コードを入力してもらい、LoginVerificationControllerで本ログインにする。
 * そのため、ここでは確認だけを行うAuth::validate()を使い、Auth::attempt()は使わない。
 * 「このデバイスを記憶する」を選んだ端末では、確認コードを省く。
 */
class AuthSessionController extends Controller
{
    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。
    // 使わないサイトでは、このuseとroutes/web.phpのlogin.passkeyのルートを消す。
    use PasskeyLogin;

    // ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
    // アカウントはメールアドレスで区別する。
    private const THROTTLE_SCOPE = 'member-login';

    // パスキーでログインさせるガード（App\Support\PasskeyLogin参照）。
    private const PASSKEY_GUARD = 'web';

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

        // 「ログイン状態を保持する」のチェック（チェックが無ければfalse）
        $remember = $request->boolean('remember');

        // メールアドレス・パスワードの確認だけ行う（まだログインはしない）
        if (! Auth::guard('web')->validate($credentials)) {
            $throttle->hit();

            // 操作ログ。誰か分からないので、入力されたメールアドレスを補足に残す
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['login_id' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $throttle->clear();

        $member = Auth::guard('web')->getLastAttempted();

        // 記憶済みの端末なら、確認コードを省いてログインを完了する
        if (TrustedDeviceManager::forMember($member)->isTrusted($member, $request)) {
            Auth::login($member, $remember);
            $request->session()->regenerate();

            // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
            return redirect(LoginRedirect::forMember(Member::class));
        }

        // それ以外は、「パスワード確認済み・2段階目が未完了」をセッションに置き、
        // 確認コードを送って入力画面へ（本ログインはLoginVerificationController::verify()）
        $request->session()->put(LoginVerificationController::PENDING_SESSION_KEY, $member->id);
        $request->session()->put(LoginVerificationController::REMEMBER_SESSION_KEY, $remember);

        if (! (new MemberVerificationCode())->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return redirect()->route('login')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('login.verify');
    }

    // パスキーでログインした後の移動先。メールアドレス・パスワードでのログインと同じく、
    // ログインが必要な画面から来た場合はその画面へ戻す（App\Support\LoginRedirect）。
    private function passkeyRedirectUrl(): string
    {
        return LoginRedirect::forMember(Member::class);
    }

    // ログアウト
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // セッションを破棄し、CSRFトークンも作り直す
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
