<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * ログイン中の会員が自分のメールアドレスを変えるときに、確認コードの入力を挟む。
 * FormFlowで本人の情報を保存しているコントローラーにuseして使う。
 *
 * メールアドレスが変わる保存だけ、入力内容をセッションに仮置きして、新しいアドレスに確認コードを送る。
 * コードが入力できた時点で、仮置きした内容をまとめて保存する。打ち間違えたアドレスに変えて
 * ログインできなくなることと、ログイン中の画面を使った他人が自分のアドレスに書き換えることを防ぐため。
 * メールアドレスが変わらない保存は、確認を挟まずにそのまま保存する。
 * 保存はFormFlowのsaveValidated()を通るので、操作ログとafterSave()のお知らせのメールは、
 * 確認を挟まない保存と同じになる。
 *
 * ■ 使い方
 * 1. コントローラーにuse EmailChange;を書き、次の定数を用意する
 *      private const EMAIL_CHANGE_GUARD = 'web';                      // ログイン中の本人を取るガード
 *      private const EMAIL_CHANGE_ROUTE = 'mypage.email';             // 確認コードの入力画面のルート名
 *      private const EMAIL_CHANGE_VIEW = 'mypage.email-verify';       // 確認コードの入力画面のビュー
 *      private const EMAIL_CHANGE_EDIT_ROUTE = 'mypage.edit';         // 入力画面のルート名
 *      private const EMAIL_CHANGE_DONE_ROUTE = 'mypage';              // 保存の後の移動先のルート名
 *      private const EMAIL_CHANGE_DONE_MESSAGE = '...';               // 保存の後に出すメッセージ
 *      private const EMAIL_CHANGE_THROTTLE_SCOPE = '...';             // 確認コードの試行制限
 * 2. update()で、saveData()の前にholdForEmailChange()を呼び、戻り値があればそれを返す
 * 3. routes/web.phpに、EMAIL_CHANGE_ROUTEと、その後ろに.confirm・.resend・.backを付けた名前で
 *    ルートを書く。.resendにはthrottleを付ける
 */
trait EmailChange
{
    /**
     * 1人の会員が確認コードを送れる回数の上限と、数える期間。新しいアドレスは自由に入力できるので、
     * 他人のアドレスに確認コードを大量に送りつけられるのを防ぐ。
     */
    private const EMAIL_CHANGE_MAIL_LIMIT = 5;

    private const EMAIL_CHANGE_MAIL_DECAY_SECONDS = 3600;

    /**
     * メールアドレスが変わる保存なら、入力内容を仮置きして確認コードを送り、コードの入力画面への
     * リダイレクトを返す。変わらない保存ならnullを返すので、呼ぶ側はそのままsaveData()へ進む。
     * 検証に失敗したときは、saveData()と同じく入力画面へ戻る。
     */
    private function holdForEmailChange(Request $request, MemberAccount $member): ?RedirectResponse
    {
        $validated = $this->validatedInput($request, $member);

        // メールアドレスが変わらないなら、確認は挟まない
        if (($validated['email'] ?? null) === $member->notificationEmail()) {
            return null;
        }

        // 入力内容をセッションに仮置きする。誰の入力かも一緒に控える
        $pending = [
            'member_id' => $member->getKey(),
            'email' => $validated['email'],
            'input' => $request->except(['_token', '_method']),
        ];

        $request->session()->put($this->emailChangeSessionKey($member), $pending);

        // 確認コードを送り、コードの入力画面へ（送れなかったときも、画面の「再送する」でやり直せる）
        $error = $this->sendEmailChangeCode($request, $member, $pending);

        if ($error !== null) {
            return redirect()->route(self::EMAIL_CHANGE_ROUTE)->with('error', $error);
        }

        return redirect()->route(self::EMAIL_CHANGE_ROUTE);
    }

    /** 確認コードの入力画面。 */
    public function emailChangeForm(Request $request): View|RedirectResponse
    {
        $pending = $this->emailChangePending($request, $this->emailChangeMember());

        // 仮置きが無ければ、入力画面へ
        if ($pending === null) {
            return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE);
        }

        return view(self::EMAIL_CHANGE_VIEW, [
            'email' => $pending['email'],
        ]);
    }

    /** 確認コードの照合と、仮置きした内容の保存。 */
    public function emailChangeConfirm(Request $request): RedirectResponse
    {
        $member = $this->emailChangeMember();
        $pending = $this->emailChangePending($request, $member);

        // 仮置きが無ければ、入力画面へ
        if ($pending === null) {
            return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE);
        }

        $code = $request->validate([
            'code' => ['required', 'string'],
        ])['code'];

        // 失敗回数による、IPごととアカウントごとの試行制限
        $throttle = new LoginThrottle(self::EMAIL_CHANGE_THROTTLE_SCOPE, $request->ip(), $member->getKey());

        if ($throttle->isBlocked()) {
            return redirect()->route(self::EMAIL_CHANGE_ROUTE)
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、仮置きした新しいアドレスが同じかも確かめる
        $email = (new MemberVerificationCode($member::class))
            ->verifyForAddress($request, MemberVerificationCode::PURPOSE_EMAIL_CHANGE, $code);

        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route(self::EMAIL_CHANGE_ROUTE)
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();
        $request->session()->forget($this->emailChangeSessionKey($member));

        // 仮置きした内容を、保存の前にもう一度検証する。コードの入力を待つ間に、同じアドレスが
        // 別の会員に使われたときなどは、入力画面へ戻す
        $validator = Validator::make($pending['input'], $this->rules($member));

        if ($validator->fails()) {
            return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE)
                ->withErrors($validator)
                ->withInput($pending['input']);
        }

        $validated = $this->prepareInput($validator->validated());

        try {
            DB::transaction(fn () => $this->saveValidated($member, $validated, $pending['input']));
        } catch (UniqueConstraintViolationException $e) {
            // 検証から保存までのごく短い間に、同じアドレスが別の会員に使われたときの最後の砦
            return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE)
                ->withInput($pending['input'])
                ->with('error', '入力いただいたメールアドレスは、別の方に登録されたようです。');
        }

        return redirect()->route(self::EMAIL_CHANGE_DONE_ROUTE)->with('status', self::EMAIL_CHANGE_DONE_MESSAGE);
    }

    /** 確認コードの再送信。 */
    public function emailChangeResend(Request $request): RedirectResponse
    {
        $member = $this->emailChangeMember();
        $pending = $this->emailChangePending($request, $member);

        // 仮置きが無ければ、入力画面へ
        if ($pending === null) {
            return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE);
        }

        $error = $this->sendEmailChangeCode($request, $member, $pending);

        if ($error !== null) {
            return redirect()->route(self::EMAIL_CHANGE_ROUTE)->with('error', $error);
        }

        return redirect()->route(self::EMAIL_CHANGE_ROUTE)->with('status', '確認コードを再送しました。');
    }

    /**
     * コードの入力画面の「入力内容を修正する」。仮置きした内容を入力画面に戻す。
     * メールアドレスを打ち間違えていたときのため。
     */
    public function emailChangeBack(Request $request): RedirectResponse
    {
        $member = $this->emailChangeMember();
        $pending = $this->emailChangePending($request, $member);

        $request->session()->forget($this->emailChangeSessionKey($member));

        return redirect()->route(self::EMAIL_CHANGE_EDIT_ROUTE)
            ->withInput($pending['input'] ?? []);
    }

    /** ログイン中の本人 */
    private function emailChangeMember(): MemberAccount
    {
        $member = Auth::guard(self::EMAIL_CHANGE_GUARD)->user();

        abort_unless($member instanceof MemberAccount, 403);

        return $member;
    }

    /** 確認コードを新しいアドレスへ送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す。 */
    private function sendEmailChangeCode(Request $request, MemberAccount $member, array $pending): ?string
    {
        // その会員が送った回数を数える
        $key = self::EMAIL_CHANGE_THROTTLE_SCOPE.':mail:'.$member->getKey();

        if (RateLimiter::tooManyAttempts($key, self::EMAIL_CHANGE_MAIL_LIMIT)) {
            return '確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::EMAIL_CHANGE_MAIL_DECAY_SECONDS);

        $sent = (new MemberVerificationCode($member::class))->issueForAddress(
            $request,
            $pending['email'],
            $member->displayName(),
            MemberVerificationCode::PURPOSE_EMAIL_CHANGE,
        );

        if (! $sent) {
            return '確認コードの送信に失敗しました。時間をおいて「確認コードを再送する」からお試しください。';
        }

        return null;
    }

    /** セッションに仮置きした入力内容。無いときと、ほかの人のものだったときはnull */
    private function emailChangePending(Request $request, MemberAccount $member): ?array
    {
        $pending = $request->session()->get($this->emailChangeSessionKey($member));

        if (! is_array($pending) || ($pending['member_id'] ?? null) !== $member->getKey()) {
            return null;
        }

        return $pending;
    }

    /** 入力内容を仮置きするセッションキー。会員の種類ごとに分ける */
    private function emailChangeSessionKey(MemberAccount $member): string
    {
        return $member::memberType().'.email_change.pending';
    }
}
