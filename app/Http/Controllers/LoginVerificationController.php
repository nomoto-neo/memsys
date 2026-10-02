<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 会員ログインの2段階目（メールで送る確認コード）。
 *
 * AuthSessionController::store()でID・パスワードの確認まで済んだ
 * （が、信頼済み端末でなければまだAuth::login()はしていない）状態を
 * 受けて、メールで送った確認コードの入力を求め、通過して初めて
 * 実際にログインさせる。管理ログインの2段階目
 * （Admin\TwoFactorChallengeController）と全く同じ構造で、
 * 「パスワード確認済み・2段階目が未完了」という中間状態はAuth::check()
 * では判定できないため、routes/web.php上でguestにもauthにも属させず、
 * このコントローラー自身がセッションのPENDING_SESSION_KEYの有無で
 * 保護している。
 */
class LoginVerificationController extends Controller
{
    /** パスワード確認済みの会員IDを一時的に持たせるセッションキー。 */
    public const PENDING_SESSION_KEY = 'member.login.pending_member_id';

    /** 「ログイン状態を保持する」チェックボックスの値を、2段階目が
     *  終わるまで一時的に持ち越すためのセッションキー。 */
    public const REMEMBER_SESSION_KEY = 'member.login.remember';

    /** 確認コードの試行制限（LoginThrottle）のカウンターの名前。アカウントは会員idで区別する。 */
    private const THROTTLE_SCOPE = 'member-verify-code';

    public function show(Request $request): View|RedirectResponse
    {
        $member = $this->pendingMember($request);

        if ($member === null) {
            return redirect()->route('login');
        }

        return view('auth.login-verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        if ($member === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・会員id単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $member->id);

        if ($throttle->isBlocked()) {
            return redirect()->route('login.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        $verifiedMember = (new MemberVerificationCode())
            ->verify($request, MemberVerificationCode::PURPOSE_LOGIN, $validated['code']);

        // $verifiedMember->id !== $member->idは通常起こり得ない
        // （セッションのpending_member_idと、コード発行時のmember_idは
        // 常に同じ値のはず）が、念のため二重に確認している。
        if ($verifiedMember === null || $verifiedMember->id !== $member->id) {
            $throttle->hit();

            return redirect()->route('login.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::forMember()->remember($member);
        }

        $this->completeLogin($request, $member);

        return redirect()->intended(route('mypage'));
    }

    /**
     * 確認コードの再送信。メールが届かない・見失った場合の救済。
     * routes/web.php側でthrottleを付け、連続送信を防いでいる。
     */
    public function resend(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        if ($member === null) {
            return redirect()->route('login');
        }

        if (! (new MemberVerificationCode())->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return redirect()->route('login.verify')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('login.verify')->with('status', '確認コードを再送しました。');
    }

    /**
     * 2段階目まで通過した時点で、初めて実際にログイン状態にする。
     * Admin\TwoFactorChallengeController::completeLogin()と同じ考え方
     * （セッション固定攻撃対策のregenerate()も、本ログインのこの
     * タイミングで行う）。
     */
    private function completeLogin(Request $request, Member $member): void
    {
        $remember = (bool) $request->session()->pull(self::REMEMBER_SESSION_KEY, false);

        Auth::login($member, $remember);

        $request->session()->forget(self::PENDING_SESSION_KEY);
        $request->session()->regenerate();
    }

    private function pendingMember(Request $request): ?Member
    {
        $id = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        return Member::find($id);
    }
}
