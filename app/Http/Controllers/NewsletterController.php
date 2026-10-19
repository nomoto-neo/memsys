<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\NewsletterSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginThrottle;
use Neobit\Core\Auth\LoginVerificationCode;
use Neobit\Core\Enums\SpamCheckResult;
use Neobit\Core\Security\SpamGuard;

/**
 * メルマガだけの申し込み。会員登録をしなくても、お知らせメール（一斉メール）を受け取れるようにする。
 * 入力、確認コードの入力、完了の順に進む。
 *
 * 確認コードは、会員登録と同じ部品（LoginVerificationCode）で送る。コードが通って初めて「受け取る」にする。
 * 他人のアドレスを勝手に登録されないようにし、本人が受け取ると決めた記録にもするため。
 * 確定の中身（会員の側か、メルマガの行の側か）は、App\Services\NewsletterSubscriptionが決める。
 * 完了の画面は、どちらになっても同じにする。そのアドレスの会員がいるかを、外から確かめられないようにするため。
 */
class NewsletterController extends Controller
{
    /** コードの入力を待つ間、氏名とメールアドレスを仮置きするセッションのキー */
    private const PENDING_SESSION_KEY = 'newsletter.pending';

    /** 確認コードの使い道。セッションのキーが使い道ごとに分かれるので、ほかの画面のコードと混ざらない */
    private const PURPOSE = 'newsletter';

    /** 確認コードのメールの件名と本文に差し込む、使い道の名前 */
    private const PURPOSE_LABEL = 'メルマガの登録';

    /**
     * 1つのメールアドレスへ確認コードを送れる回数の上限と、数える期間。誰でも使えるフォームなので、
     * 他人のアドレスに確認コードを大量に送りつけられるのを防ぐ。IPアドレスごとの制限はルートのthrottleで掛ける。
     */
    private const MAIL_LIMIT_PER_ADDRESS = 5;

    private const MAIL_LIMIT_DECAY_SECONDS = 3600;

    /** 確認コードの試行制限（LoginThrottle）のカウンターの名前 */
    private const THROTTLE_SCOPE = 'newsletter-code';

    /** スパム対策（SpamGuard）で、入力画面の表示から送信までにかかるはずのいちばん短い秒数 */
    private const SPAM_GUARD_MIN_SECONDS = 3;

    /** 入力の検証ルール。氏名は一斉メールの件名に差し込むことがあるので、メールの見出しを崩す改行は許さない */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /** 入力画面 */
    public function create(): View
    {
        return view('newsletter.create', [
            'input' => old(),
            'required' => required_fields($this->rules()),
        ]);
    }

    /**
     * 入力画面の「確認コードを送る」。スパムかを確かめ、入力を検証して仮置きし、確認コードを送る。
     * 送れなかったときも、コードの入力画面へ進め、そこの「再送する」でやり直せるようにする。
     */
    public function send(Request $request): RedirectResponse
    {
        $spam = SpamGuard::check($request, minSeconds: self::SPAM_GUARD_MIN_SECONDS);

        // 機械からの送信。完了したように見せて、何もしない（気付かれて対策されないように）
        if ($spam === SpamCheckResult::Bot) {
            return redirect()->route('newsletter.done');
        }

        // 人がたまたま失敗することもあるので、入力を残したまま入力画面に戻す
        if ($spam === SpamCheckResult::Failed) {
            return redirect()->route('newsletter.create')
                ->withInput($request->only('name', 'email'))
                ->with('error', 'ロボットによる送信ではないことを確認できませんでした。お手数ですが、もう一度「確認コードを送る」を押してください。');
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('newsletter.create')
                ->withErrors($validator)
                ->withInput($request->only('name', 'email'));
        }

        $pending = $validator->validated();
        $request->session()->put(self::PENDING_SESSION_KEY, $pending);

        // 確認コードを送り、コードの入力画面へ（送れなかったときも、画面の「再送する」でやり直せる）
        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('newsletter.verify')->with('error', $error);
        }

        return redirect()->route('newsletter.verify');
    }

    /** 確認コードの入力画面 */
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（入力画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('newsletter.create');
        }

        return view('newsletter.verify', [
            'email' => $pending['email'],
        ]);
    }

    /** 確認コードの照合と、申し込みの確定 */
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（入力画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('newsletter.create');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。Neobit\Core\Auth\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $pending['email']);

        if ($throttle->isBlocked()) {
            return redirect()->route('newsletter.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、仮置きしたメールアドレスが同じかも確かめる
        $email = (new LoginVerificationCode(Member::class))->verifyForAddress($request, self::PURPOSE, $validated['code']);

        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route('newsletter.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        NewsletterSubscription::subscribe($pending['name'], $pending['email'], $request->ip());

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('newsletter.done');
    }

    /** 確認コードの再送 */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（入力画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('newsletter.create');
        }

        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('newsletter.verify')->with('error', $error);
        }

        return redirect()->route('newsletter.verify')->with('status', '確認コードを再送しました。');
    }

    /** コードの入力画面の「入力内容を修正する」。仮置きした内容を入力画面に戻す */
    public function backFromVerify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request) ?? [];

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('newsletter.create')->withInput($pending);
    }

    /** 完了の画面。会員のアドレスだったかどうかに関係なく、同じ文を出す */
    public function done(): View
    {
        return view('newsletter.done');
    }

    /** 確認コードを送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す */
    private function sendCode(Request $request, array $pending): ?string
    {
        // 1つのメールアドレスへ送った回数を数える（大文字・小文字は同じアドレスとして数える）
        $key = 'newsletter-mail:'.mb_strtolower($pending['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAIL_LIMIT_PER_ADDRESS)) {
            return 'このメールアドレスへの確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::MAIL_LIMIT_DECAY_SECONDS);

        $sent = (new LoginVerificationCode(Member::class))
            ->issueForAddress($request, $pending['email'], $pending['name'], self::PURPOSE, purposeLabel: self::PURPOSE_LABEL);

        return $sent ? null : '確認コードの送信に失敗しました。時間をおいて「確認コードを再送する」からお試しください。';
    }

    /** セッションに仮置きした氏名とメールアドレス（無ければnull） */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }
}
