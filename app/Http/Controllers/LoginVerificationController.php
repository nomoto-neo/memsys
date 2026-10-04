<?php

namespace App\Http\Controllers;

use App\Enums\OperationLogAction;
use App\Support\OperationRecorder;
use App\Models\Member;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 会員ログインの2段階目。メールで送った確認コードを照合し、通ったら本ログインにする。
 *
 * 2段階目の途中は、まだログインしていないのでAuth::check()では見分けられない。
 * そのため、ルートはguestにもauthにも入れず、このコントローラーがセッションの値で守る。
 */
class LoginVerificationController extends Controller
{
    // パスワード確認済みの会員IDを一時的に持たせるセッションキー。
    public const PENDING_SESSION_KEY = 'member.login.pending_member_id';

    // 「ログイン状態を保持する」の値を、2段階目が終わるまで持ち越すセッションキー
    public const REMEMBER_SESSION_KEY = 'member.login.remember';

    // 確認コードの試行制限（LoginThrottle）のカウンターの名前。アカウントは会員idで区別する。
    private const THROTTLE_SCOPE = 'member-verify-code';

    // 確認コードの入力画面
    public function show(Request $request): View|RedirectResponse
    {
        $member = $this->pendingMember($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($member === null) {
            return redirect()->route('login');
        }

        return view('auth.login-verify');
    }

    // 確認コードの照合と、本ログイン
    public function verify(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($member === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・会員id単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $member->id);

        if ($throttle->isBlocked()) {
            return redirect()->route('login.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードを発行した会員と、パスワードを確認した会員が同じかも
        // 念のため確かめる（通常は必ず同じ）
        $verifiedMember = (new MemberVerificationCode())
            ->verify($request, MemberVerificationCode::PURPOSE_LOGIN, $validated['code']);

        if ($verifiedMember === null || $verifiedMember->id !== $member->id) {
            $throttle->hit();

            // 操作ログ。パスワードは通っているので、誰の失敗かが分かる
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['step' => '2段階目'], operator: $member);

            return redirect()->route('login.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 「このデバイスを記憶する」にチェックがあれば、次回から確認コードを省く
        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::forMember()->remember($member);
        }

        $this->completeLogin($request, $member);

        // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
        // （App\Support\LoginRedirect）
        return redirect(LoginRedirect::forMember());
    }

    // 確認コードの再送信。メールが届かない・見失った場合の救済。
    // 連続送信は、routes/web.phpのthrottleで防いでいる。
    public function resend(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($member === null) {
            return redirect()->route('login');
        }

        if (! (new MemberVerificationCode())->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return redirect()->route('login.verify')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('login.verify')->with('status', '確認コードを再送しました。');
    }

    // 2段階目まで通った時点で、初めて本ログインにする。セッション固定攻撃への対策の
    // regenerate()も、このときに行う（Admin\TwoFactorChallengeController::completeLogin()と同じ）。
    private function completeLogin(Request $request, Member $member): void
    {
        // 1段階目で控えた「ログイン状態を保持する」を取り出して使う
        $remember = (bool) $request->session()->pull(self::REMEMBER_SESSION_KEY, false);

        Auth::login($member, $remember);

        $request->session()->forget(self::PENDING_SESSION_KEY);
        $request->session()->regenerate();
    }

    // パスワード確認済みで、2段階目を待っている会員（いなければnull）
    private function pendingMember(Request $request): ?Member
    {
        $id = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        return Member::find($id);
    }
}
