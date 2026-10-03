<?php

namespace App\Http\Controllers;

use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\PasskeyLogin;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// ログイン・ログアウトという「セッションの作成・破棄」を担当するコントローラー。
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

    // ログイン処理
    public function store(Request $request): RedirectResponse
    {
        // バリデーションチェック
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['email']);

        if ($throttle->isBlocked()) {
            throw ValidationException::withMessages([
                'email' => $throttle->blockedMessage('ログイン'),
            ]);
        }

        // ログイン保持チェック
        $remember = $request->boolean('remember');

        // ★attempt()ではなくvalidate()を使っている★
        //
        // Auth::attempt()は「ID・パスワードの確認」と「実際にログイン状態に
        // する」を1回で行ってしまうが、ここではその間に「メールで送る
        // 確認コードの入力」を挟みたい。そこで、確認だけ行いログインは
        // しないvalidate()を使う（管理ログインのTOTPと全く同じ考え方。
        // Admin\AuthSessionController::store()参照）。
        //
        // validate()は内部でretrieveByCredentials()・hasValidCredentials()
        // を呼ぶだけで、login()・setUser()・updateSession()は呼ばない
        // （Illuminate\Auth\SessionGuardのソースで確認済み）。そのため
        // このメソッドの中では、まだAuth::check()はfalseのまま。
        if (! Auth::guard('web')->validate($credentials)) {
            $throttle->hit();

            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $throttle->clear();

        $member = Auth::guard('web')->getLastAttempted();

        // この端末が「記憶する」済みなら、確認コードの入力を省略して
        // そのままログインを完了させる。省略できるのは飽くまで
        // 「同じ端末からの、ID・パスワードが合っているログイン」の場合のみ
        // ——ID・パスワードの確認自体は毎回必ず行っている点に注意
        // （信頼済み端末だからといってパスワード確認ごと省略するわけではない）。
        if (TrustedDeviceManager::forMember()->isTrusted($member, $request)) {
            Auth::login($member, $remember);
            $request->session()->regenerate();

            // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
            return redirect(LoginRedirect::forMember());
        }

        // パスワード確認済み・2段階目未完了、という状態をセッションに
        // 一時保存し、確認コード入力画面へ。実際のAuth::login()は
        // LoginVerificationController::verify()まで持ち越す。
        $request->session()->put(LoginVerificationController::PENDING_SESSION_KEY, $member->id);
        $request->session()->put(LoginVerificationController::REMEMBER_SESSION_KEY, $remember);

        if (! (new MemberVerificationCode())->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return redirect()->route('login')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('login.verify');
    }

    // パスキーでログインした後の移動先。ログインID・パスワードでのログインと同じく、
    // ログインが必要な画面から来た場合はその画面へ戻す（App\Support\LoginRedirect）。
    private function passkeyRedirectUrl(): string
    {
        return LoginRedirect::forMember();
    }

    // ログアウト処理
    public function destroy(Request $request): RedirectResponse
    {
        // ログアウト
        Auth::logout();

        // セッションを破棄してセッションIDを再作成
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
