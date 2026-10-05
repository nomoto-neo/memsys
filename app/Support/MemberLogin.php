<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 会員のログインの、パスワードを確かめた後の流れ。個人会員と企業の担当者で同じなので、
 * ログインのコントローラーがこのトレイトを使う。
 *
 * ログインは2段階で、パスワードが合っても、すぐにはログインさせない。メールで送る確認コードを
 * 入力してもらってから、本ログインにする。「このデバイスを記憶する」を選んだ端末では、
 * 確認コードを省く。
 *
 * ■ コントローラーに書くもの
 * - 定数 MEMBER_CLASS        ログインする会員のモデル。例：Member::class
 * - 定数 LOGIN_VERIFY_VIEW   確認コードの入力画面のビュー
 * - ログイン画面の表示と、パスワードを確かめるところ。入力する項目が種類ごとに違うため。
 *   パスワードが合ったら、continueAfterPassword()を呼ぶ
 * - ログアウト。logoutMember()を呼んで、移動先を返す
 *
 * ■ このトレイトが受け持つルート
 * - showVerification()  確認コードの入力画面（ルートの名前は login.verify）
 * - verifyCode()        確認コードの照合と、本ログイン
 * - resendCode()        確認コードの再送信
 * ルートの名前は、会員の種類の名前から作る（App\Support\MemberAccount）。
 *
 * ■ 2段階目の途中
 * まだログインしていないので、Auth::check()では見分けられない。2段階目のルートはguestにもauthにも
 * 入れず、このトレイトがセッションの値で守る。
 */
trait MemberLogin
{
    // パスワード確認済みの会員のidを、2段階目が終わるまで持つセッションのキー
    private function pendingSessionKey(): string
    {
        return self::MEMBER_CLASS::memberType().'.login.pending_member_id';
    }

    // 「ログイン状態を保持する」の値を、2段階目が終わるまで持ち越すセッションのキー
    private function rememberSessionKey(): string
    {
        return self::MEMBER_CLASS::memberType().'.login.remember';
    }

    // この種類の会員のルートへのリダイレクト。$nameは頭を付ける前の名前
    private function redirectToMemberRoute(string $name): RedirectResponse
    {
        return redirect()->route(self::MEMBER_CLASS::memberRoute($name));
    }

    /**
     * パスワードが合った後の流れ。記憶済みの端末ならそのままログインし、そうでなければ
     * 確認コードを送って入力画面へ進める。
     *
     * @param  bool  $remember  「ログイン状態を保持する」のチェック
     */
    private function continueAfterPassword(Request $request, MemberAccount $member, bool $remember): RedirectResponse
    {
        // 記憶済みの端末なら、確認コードを省いてログインを完了する
        if (TrustedDeviceManager::forMember($member)->isTrusted($member, $request)) {
            Auth::guard($member::memberGuard())->login($member, $remember);
            $request->session()->regenerate();

            // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
            return redirect(LoginRedirect::forMember($member::class));
        }

        // それ以外は、「パスワード確認済み・2段階目が未完了」をセッションに置き、
        // 確認コードを送って入力画面へ
        $request->session()->put($this->pendingSessionKey(), $member->getKey());
        $request->session()->put($this->rememberSessionKey(), $remember);

        if (! (new MemberVerificationCode(self::MEMBER_CLASS))->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return $this->redirectToMemberRoute('login')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->redirectToMemberRoute('login.verify');
    }

    // 確認コードの入力画面
    public function showVerification(Request $request): View|RedirectResponse
    {
        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($this->pendingMember($request) === null) {
            return $this->redirectToMemberRoute('login');
        }

        return view(self::LOGIN_VERIFY_VIEW);
    }

    // 確認コードの照合と、本ログイン
    public function verifyCode(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($member === null) {
            return $this->redirectToMemberRoute('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・会員id単位。App\Support\LoginThrottle）。
        // カウンターの名前は、会員の種類ごとに分ける
        $throttle = new LoginThrottle($member::memberType().'-verify-code', $request->ip(), $member->getKey());

        if ($throttle->isBlocked()) {
            return $this->redirectToMemberRoute('login.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードを発行した会員と、パスワードを確認した会員が同じかも
        // 念のため確かめる（通常は必ず同じ）
        $verified = (new MemberVerificationCode(self::MEMBER_CLASS))
            ->verify($request, MemberVerificationCode::PURPOSE_LOGIN, $validated['code']);

        if ($verified === null || ! $verified->is($member)) {
            $throttle->hit();

            // 操作ログ。パスワードは通っているので、誰の失敗かが分かる
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['step' => '2段階目'], operator: $member);

            return $this->redirectToMemberRoute('login.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 「このデバイスを記憶する」にチェックがあれば、次回から確認コードを省く
        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::forMember($member)->remember($member);
        }

        // 2段階目まで通った時点で、初めて本ログインにする。セッション固定攻撃への対策の
        // regenerate()も、このときに行う。1段階目で控えた「ログイン状態を保持する」を使う
        $remember = (bool) $request->session()->pull($this->rememberSessionKey(), false);

        Auth::guard($member::memberGuard())->login($member, $remember);

        $request->session()->forget($this->pendingSessionKey());
        $request->session()->regenerate();

        // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
        return redirect(LoginRedirect::forMember($member::class));
    }

    // 確認コードの再送信。メールが届かない・見失った場合の救済。
    // 連続送信は、routes/web.phpのthrottleで防ぐ。
    public function resendCode(Request $request): RedirectResponse
    {
        $member = $this->pendingMember($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($member === null) {
            return $this->redirectToMemberRoute('login');
        }

        if (! (new MemberVerificationCode(self::MEMBER_CLASS))->issue($request, $member, MemberVerificationCode::PURPOSE_LOGIN)) {
            return $this->redirectToMemberRoute('login.verify')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->redirectToMemberRoute('login.verify')->with('status', '確認コードを再送しました。');
    }

    // ログアウト。この種類のログインだけを終わらせ、同じブラウザのほかのログイン（管理画面など）は
    // 残す（App\Support\LoginSession）
    private function logoutMember(Request $request): void
    {
        LoginSession::logout($request, self::MEMBER_CLASS::memberGuard());
    }

    // パスワード確認済みで、2段階目を待っている会員（いなければnull）
    private function pendingMember(Request $request): ?MemberAccount
    {
        $id = $request->session()->get($this->pendingSessionKey());

        return $id !== null ? self::MEMBER_CLASS::find($id) : null;
    }
}
