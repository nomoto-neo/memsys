<?php

namespace App\Http\Controllers;

use App\Enums\NoticeMail;
use App\Models\Member;
use App\Rules\KatakanaRule;
use App\Rules\PhoneNumberRule;
use App\Support\LoginThrottle;
use App\Support\MemberActivityLog;
use App\Support\MemberVerificationCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 会員登録。入力、確認、確認コードの入力、登録完了の順に進む。
 *
 * 確認画面で確認コードを送り、そのコードが入力できた時点で、初めて会員を作る。
 * そのメールアドレスを本当に受け取れる本人だと確かめるため。
 * コードの入力を待つ間、入力内容はセッションに仮置きし、パスワードはハッシュ値で持つ。
 */
class AuthRegisteredMemberController extends Controller
{
    /** コード入力待ちの入力内容（パスワードはハッシュ化済み）を仮置きするセッションキー。 */
    private const PENDING_SESSION_KEY = 'member.regist.pending';

    private const PURPOSE = MemberVerificationCode::PURPOSE_REGISTER;

    /**
     * 1つのメールアドレスへ確認コードを送れる回数の上限と、数える期間。登録フォームは
     * 誰でも使えるので、他人のアドレスに確認コードを大量に送りつけられるのを防ぐ。
     * IPアドレスごとの制限はルートのthrottleで掛けているが、IPを変えて同じアドレスを
     * 狙われるのに備えて、アドレスごとにも数える。
     */
    private const MAIL_LIMIT_PER_ADDRESS = 5;

    private const MAIL_LIMIT_DECAY_SECONDS = 3600;

    /**
     * 確認コードの試行制限（LoginThrottle）のカウンターの名前。会員がまだ存在しない
     * （idが無い）ので、アカウントは登録しようとしているメールアドレスで区別する。
     */
    private const THROTTLE_SCOPE = 'member-register-code';

    /**
     * 会員登録フォームの検証ルール。password_confirmationは、confirmedルールでpasswordと
     * 照合するので、ここには書かない。
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255', new KatakanaRule()],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // まだ誰も使っていないメールアドレスであること
                Rule::unique(Member::class, 'email'),
            ],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            'birthdate' => ['nullable', 'date'],
            'prefecture' => [
                'nullable', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('prefectures')),
            ],
            // お知らせメールを受け取るかどうか
            'notice_mail' => [
                'required', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('notice_mail')),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** 入力フォームの表示 */
    public function create(): View
    {
        // 入力欄の値。画面でold()を直接呼ばず、管理画面と同じくコントローラーで組み立てる。
        // お知らせメールは、「受け取る」を選んだ状態で出す
        $input = old() + ['notice_mail' => NoticeMail::Receive->value];

        return view('auth.regist', [
            'input' => $input,
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    /** 確認画面の表示（保存はしない） */
    public function confirm(Request $request): View
    {
        $request->validate($this->rules());

        // 確認画面の表示とhiddenに使う値。送られてきた全項目から、Laravelが使う制御用の値
        // （CSRFトークン・メソッドの指定）を除いたもの（password_confirmationも持ち回る）
        $input = $request->except(['_token', '_method']);

        return view('auth.regist-confirm', [
            'input' => $input,
        ]);
    }

    /** 確認画面の「戻る」。パスワードは入力し直してもらう */
    public function back(Request $request): RedirectResponse
    {
        return redirect()->route('regist.create')
            ->withInput($request->except('password', 'password_confirmation'));
    }

    /**
     * 確認画面の「確認コードを送信する」（POST /regist/send）。
     *
     * 届いた内容をもう一度検証してから、セッションに仮置きして確認コードを送る。会員はまだ作らない。
     * 送れなかったときも、コードの入力画面へ進め、そこの「再送する」でやり直せるようにする。
     */
    public function send(Request $request): RedirectResponse
    {
        // 確認画面のhiddenから届いた内容を、もう一度すべて検証する。失敗したら、
        // 入力欄のある入力画面へ戻す
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('regist.create')
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $validated = $validator->validated();

        // 入力内容をセッションに仮置きする（パスワードは平文で持たず、ハッシュ値にする）
        $pending = Arr::except($validated, ['password']);
        $pending['password_hash'] = Hash::make($validated['password']);

        $request->session()->put(self::PENDING_SESSION_KEY, $pending);

        // 確認コードを送り、コード入力画面へ（送れなかったときも、画面の「再送する」でやり直せる）
        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('regist.verify')->with('error', $error);
        }

        return redirect()->route('regist.verify');
    }

    /** 確認コードの入力画面（GET /regist/verify）。 */
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('regist.create');
        }

        return view('auth.regist-verify', [
            'email' => $pending['email'],
        ]);
    }

    /** 確認コードの照合と、会員の登録（POST /regist/verify）。 */
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('regist.create');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $pending['email']);

        if ($throttle->isBlocked()) {
            return redirect()->route('regist.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、仮置きした入力のメールアドレスが同じかも確かめる
        // （別のアドレスで送り直すと、仮置きもコードも置き換わるので、通常は同じ）
        $email = (new MemberVerificationCode(Member::class))->verifyForAddress($request, self::PURPOSE, $validated['code']);

        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route('regist.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 確認画面からコードの入力までの間に、同じメールアドレスで別の登録が済んでいたら、
        // 入力画面へ戻す（保存のときの一意制約の違反も、最後の砦として同じ扱いにする）
        if (Member::where('email', $pending['email'])->exists()) {
            return $this->emailTaken($request, $pending);
        }

        // 会員を作る
        try {
            $member = Member::create([
                'name' => $pending['name'],
                'kana' => $pending['kana'] ?? null,
                'email' => $pending['email'],
                'phone' => $pending['phone'] ?? null,
                'birthdate' => $pending['birthdate'] ?? null,
                'prefecture' => $pending['prefecture'] ?? null,
                'notice_mail' => $pending['notice_mail'],
                'password' => $pending['password_hash'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->emailTaken($request, $pending);
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        // 登録の記録をログに残す（個人情報は含めない）
        MemberActivityLog::registered($member, $request);

        // そのままログインさせる（登録の直後に、もう一度ログインし直させない）
        Auth::login($member);
        $request->session()->regenerate();

        return redirect()->route('mypage')->with('status', '会員登録が完了しました。');
    }

    /** 確認コードの再送信（POST /regist/verify/resend）。 */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('regist.create');
        }

        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('regist.verify')->with('error', $error);
        }

        return redirect()->route('regist.verify')->with('status', '確認コードを再送しました。');
    }

    /**
     * コード入力画面の「入力内容を修正する」（POST /regist/verify/back）。
     * 仮置きした内容を入力画面に戻す。パスワードは確認画面の「戻る」と同じく
     * 入力し直してもらう（仮置きしているのはハッシュ値なので、そもそも戻せない）。
     */
    public function backFromVerify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request) ?? [];

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('regist.create')
            ->withInput(Arr::except($pending, ['password_hash']));
    }

    /** 確認コードを送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す。 */
    private function sendCode(Request $request, array $pending): ?string
    {
        // 1つのメールアドレスへ送った回数を数える（大文字・小文字は同じアドレスとして数える）
        $key = 'member-register-mail:'.mb_strtolower($pending['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAIL_LIMIT_PER_ADDRESS)) {
            return 'このメールアドレスへの確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::MAIL_LIMIT_DECAY_SECONDS);

        if (! (new MemberVerificationCode(Member::class))->issueForAddress($request, $pending['email'], $pending['name'], self::PURPOSE)) {
            return '確認コードの送信に失敗しました。時間をおいて「確認コードを再送する」からお試しください。';
        }

        return null;
    }

    /**
     * 登録しようとしたメールアドレスが、既に別の会員に使われていた場合。
     * 仮置きを消して、入力内容（パスワード以外）を持って入力画面へ戻す。
     */
    private function emailTaken(Request $request, array $pending): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('regist.create')
            ->withInput(Arr::except($pending, ['password_hash']))
            ->with('error', '入力いただいたメールアドレスは、確認コードの送信後に別の方に登録されたようです。お手数ですが、もう一度お試しください。');
    }

    /** セッションに仮置きした入力内容（無ければnull） */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }
}
