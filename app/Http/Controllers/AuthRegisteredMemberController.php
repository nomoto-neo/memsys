<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Member;
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
 * 会員登録。
 *
 * 入力 → 確認 → 確認コードの入力 → 登録完了、の流れ。確認画面の
 * 「確認コードを送信する」で、入力されたメールアドレスに確認コードを送り、
 * そのコードが入力できた（＝本当にそのアドレスを受け取れる本人だと
 * 確かめられた）時点で、初めて会員を作る。
 *
 * コードの入力を待つ間、入力内容はセッション（PENDING_SESSION_KEY）に
 * 仮置きする。パスワードはこの時点でハッシュ化し、平文では持たない。
 * コードの発行・照合は、ログインやパスワード再設定と同じ
 * App\Support\MemberVerificationCodeを使うが、会員がまだ存在しないので、
 * 会員ではなくメールアドレスに紐付けて発行する（issueForAddress()）。
 */
class AuthRegisteredMemberController extends Controller
{
    /** コード入力待ちの入力内容（パスワードはハッシュ化済み）を仮置きするセッションキー。 */
    private const PENDING_SESSION_KEY = 'member.regist.pending';

    private const PURPOSE = MemberVerificationCode::PURPOSE_REGISTER;

    /**
     * 1つのメールアドレスへ確認コードを送れる回数の上限と、その回数を数える期間。
     * 登録フォームは誰でも使えるので、他人のメールアドレスを入力して
     * 確認コードのメールを大量に送りつける、という使われ方を防ぐ。
     * IPアドレス単位の制限はroutes/web.phpのthrottleで掛けているが、
     * IPを変えながら同じアドレスを狙われる場合に備えて、アドレス単位でも数える。
     */
    private const MAIL_LIMIT_PER_ADDRESS = 5;

    private const MAIL_LIMIT_DECAY_SECONDS = 3600;

    /**
     * 確認コードの試行制限（LoginThrottle）のカウンターの名前。会員がまだ存在しない
     * （idが無い）ので、アカウントは登録しようとしているメールアドレスで区別する。
     */
    private const THROTTLE_SCOPE = 'member-register-code';

    /**
     * 会員登録フォームで使うバリデーションルール一式。
     *
     * password_confirmationはここには載せない。confirmedルールは
     * password_confirmationがrules()にあるかどうかに関係なく動くので、
     * バリデーション目的では不要。確認画面のhidden展開もrules()のキー一覧
     * には依存していない（confirm()参照）。
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Member::class, 'email')],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            'birthdate' => ['nullable', 'date'],
            'prefecture' => ['nullable', 'integer', Rule::in(code_keys('prefectures'))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function create(): View
    {
        // フォームに表示する値は$input（送信される項目だけの配列）。
        // 表示専用の値を混ぜないのがこのプロジェクト全体の規約（詳しくは
        // resources/views/_confirm_hiddenのコメント参照）。ビュー側でold()を
        // 直接呼ばず、値の組み立てを必ずコントローラー側に集める、という
        // 意味でもadmin側と形を揃えている。
        $input = old();

        return view('auth.regist', [
            'input' => $input,
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    public function confirm(Request $request): View
    {
        $request->validate($this->rules());

        // hidden展開の対象は「rules()に載っている項目」ではなく、POSTされた
        // 全項目からLaravel自身が使う制御用フィールド（CSRFトークン、
        // メソッド偽装用フィールド）を除いたもの。rules()に追加しなくても、
        // フォームに足した新しい項目は自動的にここに含まれるようになる。
        //
        // 表示にも、_confirm_hiddenによるhidden展開にも、この$inputを使う。
        $input = $request->except(['_token', '_method']);

        return view('auth.regist-confirm', [
            'input' => $input,
        ]);
    }

    public function back(Request $request): RedirectResponse
    {
        return redirect()->route('regist.create')
            ->withInput($request->except('password', 'password_confirmation'));
    }

    /**
     * 確認画面の「確認コードを送信する」（POST /regist/send）。
     *
     * 確認画面のhiddenから送られてきた内容を、もう一度すべて検証してから
     * セッションに仮置きし、確認コードを送る。会員はまだ作らない。
     * 送信に失敗した場合（送信回数の上限を含む）も、仮置きした内容は残して
     * コード入力画面へ進め、そこからの「確認コードを再送する」でやり直せるようにする。
     */
    public function send(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('regist.create')
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $validated = $validator->validated();

        $pending = Arr::except($validated, ['password']);
        $pending['password_hash'] = Hash::make($validated['password']);

        $request->session()->put(self::PENDING_SESSION_KEY, $pending);

        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('regist.verify')->with('error', $error);
        }

        return redirect()->route('regist.verify');
    }

    /**
     * 確認コードの入力画面（GET /regist/verify）。
     */
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('regist.create');
        }

        return view('auth.regist-verify', [
            'email' => $pending['email'],
        ]);
    }

    /**
     * 確認コードの照合と、会員の登録（POST /regist/verify）。
     */
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('regist.create');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $pending['email']);

        if ($throttle->isBlocked()) {
            return redirect()->route('regist.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        $email = (new MemberVerificationCode())->verifyForAddress($request, self::PURPOSE, $validated['code']);

        // コードの発行先と、今仮置きしている入力内容のメールアドレスが
        // 一致することまで確かめる（コードを送った後に確認画面からやり直して
        // 別のアドレスで送り直した場合は、仮置きもコードも新しいものに
        // 置き換わるので、通常は一致する）。
        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route('regist.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 確認画面の表示からコードの入力までの間に、同じメールアドレスで別の
        // 登録が済んでいた場合。事前に確かめた上で、INSERT時のunique制約違反も
        // 最後の砦として受け止める。
        if (Member::where('email', $pending['email'])->exists()) {
            return $this->emailTaken($request, $pending);
        }

        try {
            $member = Member::create([
                'name' => $pending['name'],
                'kana' => $pending['kana'] ?? null,
                'email' => $pending['email'],
                'phone' => $pending['phone'] ?? null,
                'birthdate' => $pending['birthdate'] ?? null,
                'prefecture' => $pending['prefecture'] ?? null,
                'password' => $pending['password_hash'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->emailTaken($request, $pending);
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        MemberActivityLog::registered($member, $request);

        Auth::login($member);
        $request->session()->regenerate();

        return redirect()->route('mypage')->with('status', '会員登録が完了しました。');
    }

    /**
     * 確認コードの再送信（POST /regist/verify/resend）。
     */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

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

    /**
     * 確認コードを送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す。
     */
    private function sendCode(Request $request, array $pending): ?string
    {
        $key = 'member-register-mail:'.mb_strtolower($pending['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAIL_LIMIT_PER_ADDRESS)) {
            return 'このメールアドレスへの確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::MAIL_LIMIT_DECAY_SECONDS);

        if (! (new MemberVerificationCode())->issueForAddress($request, $pending['email'], $pending['name'], self::PURPOSE)) {
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

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }
}
