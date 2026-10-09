<?php

namespace App\Http\Controllers\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Support\LoginIdMemory;
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
 * 企業会員のログイン・ログアウト。ログインするのは、企業に属する担当者（CompanyUser）。
 *
 * ログイン画面では、企業ID・担当者ID・パスワードの3つを入力する。担当者IDは企業の中でだけ
 * 重ならないので、企業IDで企業を決めてから、その企業の担当者を探す。
 * パスワードが合った後の流れ（確認コードの送信・照合・記憶済みの端末）は、個人会員と同じで、
 * MemberLoginトレイトにある。
 */
class AuthSessionController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // パスワードが合った後の流れ（continueAfterPassword()）、確認コードの入力画面と照合
    // （showVerification()・verifyCode()・resendCode()）、ログアウト（logoutMember()）。
    use MemberLogin;

    // パスキーでのログイン（passkeyLoginOptions()・passkeyLogin()）。
    // 使わないサイトでは、このuseとroutes/web.phpのcompany.login.passkeyのルートを消す。
    use PasskeyLogin;

    // ---- ログイン（MemberLogin）の設定 ----

    /** ログインする会員のモデル。ガード・ルート・メールのテンプレートの名前は、ここから決まる。 */
    private const MEMBER_CLASS = CompanyUser::class;

    /** 確認コードの入力画面のビュー。 */
    private const LOGIN_VERIFY_VIEW = 'company.auth.login-verify';

    /**
     * ログインの試行制限（LoginThrottle）で、このコントローラーの失敗回数を数えるカウンターの名前。
     * アカウントは、企業IDと担当者IDの組で区別する。
     */
    private const THROTTLE_SCOPE = 'company-login';

    /** 「企業IDと担当者IDを記憶する」の値を入れるCookieの名前（App\Support\LoginIdMemory）。 */
    private const LOGIN_ID_COOKIE = 'company_login_ids';

    // ---- パスキーでのログイン（PasskeyLogin）の設定 ----

    /** パスキーでログインさせるガード（App\Support\PasskeyLogin参照）。 */
    private const PASSKEY_GUARD = 'company';

    // ---- ログイン・ログアウト ----

    /** ログインフォームの表示 */
    public function create(Request $request): View
    {
        // 「企業IDと担当者IDを記憶する」で覚えた値があれば、入力済みで出す。
        // 入力エラーで戻ってきたときは、そのときの入力を優先する
        $remembered = (new LoginIdMemory(self::LOGIN_ID_COOKIE))->recall($request);

        return view('company.auth.login', [
            'input' => [
                'company_code' => old('company_code', $remembered['company_code'] ?? ''),
                'login_id' => old('login_id', $remembered['login_id'] ?? ''),
            ],
            'idsRemembered' => $remembered !== [],
        ]);
    }

    /** ログイン（1段階目：企業ID・担当者ID・パスワード） */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'company_code' => ['required', 'string'],
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 企業IDと担当者IDの組。この組で、1人の担当者に決まる
        $loginId = $credentials['company_code'].'/'.$credentials['login_id'];

        // 失敗回数による試行制限（IP単位・企業IDと担当者IDの組の単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $loginId);

        if ($throttle->isBlocked()) {
            throw ValidationException::withMessages([
                'company_code' => $throttle->blockedMessage('ログイン'),
            ]);
        }

        // 企業IDで企業を決めてから、その企業の担当者を探す
        $user = CompanyUser::query()
            ->where('login_id', $credentials['login_id'])
            ->whereIn('company_id', Company::query()->where('code', $credentials['company_code'])->select('id'))
            ->first();

        // パスワードの確認だけ行う（まだログインはしない）。古い方式のパスワードの置き換えも、
        // ここで働く（App\Support\LegacyPasswordUserProvider）
        $provider = Auth::guard(self::MEMBER_CLASS::memberGuard())->getProvider();

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $credentials['password']])) {
            $throttle->hit();

            // 操作ログ。誰か分からないので、入力された企業IDと担当者IDを補足に残す
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['login_id' => $loginId]);

            // 3つのうち、どれが違うかは伝えない。企業IDや担当者IDが実在するかを、調べられないようにする
            throw ValidationException::withMessages([
                'company_code' => '企業ID・担当者ID・パスワードのいずれかが正しくありません。',
            ]);
        }

        $throttle->clear();

        // 承認済みの企業の担当者だけが、ログインできる。パスワードが合った人にだけ、理由を伝える
        if (! $user->company->isApproved()) {
            throw ValidationException::withMessages([
                'company_code' => $user->company->status === CompanyStatus::Pending
                    ? 'ただいま、ご登録の内容を確認しております。承認のお知らせが届くまで、お待ちください。'
                    : 'この企業会員は、現在ご利用いただけません。',
            ]);
        }

        // 既存のシステムから移した企業の最初の担当者は、メールアドレスが空で確認コードを送れない。
        // 2段階目の代わりに、初回のログインでの登録へ回す（FirstLoginSetupController）。
        // 担当者IDはそこで決め直すので、ここでは記憶しない
        if ($user->needsFirstLoginSetup()) {
            $request->session()->put(FirstLoginSetupController::USER_SESSION_KEY, $user->id);

            return redirect()->route('company.login.setup');
        }

        // 「企業IDと担当者IDを記憶する」。チェックが無ければ、覚えていた値を消す
        (new LoginIdMemory(self::LOGIN_ID_COOKIE))->store($request->boolean('remember_ids'), [
            'company_code' => $credentials['company_code'],
            'login_id' => $credentials['login_id'],
        ]);

        // 記憶済みの端末ならそのままログイン、そうでなければ確認コードの入力へ。
        // 「ログイン状態を保持する」のチェックも渡す（チェックが無ければfalse）
        return $this->continueAfterPassword($request, $user, $request->boolean('remember'));
    }

    /**
     * パスキーでログインした後の移動先。ログインが必要な画面から来た場合はその画面へ戻す
     * （App\Support\LoginRedirect）。
     */
    private function passkeyRedirectUrl(): string
    {
        return LoginRedirect::forMember(self::MEMBER_CLASS);
    }

    /** ログアウト */
    public function destroy(Request $request): RedirectResponse
    {
        $this->logoutMember($request);

        return redirect()->route(self::MEMBER_CLASS::memberRoute('login'));
    }
}
