<?php

namespace App\Http\Controllers\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Rules\KatakanaRule;
use App\Rules\PhoneNumberRule;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\OperationRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 企業会員の登録。入力、確認、確認コードの入力、申請の完了の順に進む。
 *
 * 企業の情報と、最初の担当者の情報を一緒に入力する。確認コードが入力できた時点で、
 * 企業と担当者を「申請中」で作る。担当者のメールアドレスを本当に受け取れる本人だと確かめるため。
 * 申請中の企業の担当者は、まだログインできない。運営が管理画面で承認すると、ログインできるようになる
 * （App\Http\Controllers\Admin\CompanyController）。企業IDは、承認のお知らせのメールで伝える。
 * コードの入力を待つ間、入力内容はセッションに仮置きし、パスワードはハッシュ値で持つ。
 */
class RegistrationController extends Controller
{
    // コード入力待ちの入力内容（パスワードはハッシュ化済み）を仮置きするセッションキー。
    private const PENDING_SESSION_KEY = 'company.regist.pending';

    private const PURPOSE = MemberVerificationCode::PURPOSE_REGISTER;

    /**
     * 1つのメールアドレスへ確認コードを送れる回数の上限と、数える期間。登録フォームは
     * 誰でも使えるので、他人のアドレスに確認コードを大量に送りつけられるのを防ぐ。
     * IPアドレスごとの制限はルートのthrottleで掛けているが、IPを変えて同じアドレスを
     * 狙われるのに備えて、アドレスごとにも数える。
     */
    private const MAIL_LIMIT_PER_ADDRESS = 5;

    private const MAIL_LIMIT_DECAY_SECONDS = 3600;

    // 確認コードの試行制限（LoginThrottle）のカウンターの名前。担当者がまだ存在しない
    // （idが無い）ので、アカウントは登録しようとしているメールアドレスで区別する。
    private const THROTTLE_SCOPE = 'company-register-code';

    // 企業の登録フォームの検証ルール。企業の情報と、最初の担当者の情報。
    // 担当者の氏名は、企業名（name）と重ならないようuser_nameで受ける。
    // password_confirmationは、confirmedルールでpasswordと照合するので、ここには書かない。
    private function rules(): array
    {
        return [
            // 企業の情報
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255', new KatakanaRule()],
            'representative' => ['nullable', 'string', 'max:255'],
            // ハイフンは、あっても無くてもよい
            'zip' => ['nullable', 'string', 'regex:/^[0-9]{3}-?[0-9]{4}$/'],
            'prefecture' => [
                'nullable', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('prefectures')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'tel' => ['required', 'string', new PhoneNumberRule()],
            'url' => ['nullable', 'string', 'url', 'max:255'],

            // 最初の担当者の情報。担当者IDは企業の中でだけ重ならなければよいので、
            // 新しい企業では重なりを確かめない
            'login_id' => ['required', 'string', 'max:50', 'regex:'.CompanyUser::LOGIN_ID_PATTERN],
            'user_name' => ['required', 'string', 'max:255'],
            // メールアドレスは、ほかの担当者と重なっていてもよい
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // 入力フォームの表示
    public function create(): View
    {
        // 入力欄の値。画面でold()を直接呼ばず、管理画面と同じくコントローラーで組み立てる
        $input = old();

        return view('company.auth.regist', [
            'input' => $input,
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    // 確認画面の表示（保存はしない）
    public function confirm(Request $request): View
    {
        $request->validate($this->rules());

        // 確認画面の表示とhiddenに使う値。送られてきた全項目から、Laravelが使う制御用の値
        // （CSRFトークン・メソッドの指定）を除いたもの（password_confirmationも持ち回る）
        $input = $request->except(['_token', '_method']);

        return view('company.auth.regist-confirm', [
            'input' => $input,
        ]);
    }

    // 確認画面の「戻る」。パスワードは入力し直してもらう
    public function back(Request $request): RedirectResponse
    {
        return redirect()->route('company.regist.create')
            ->withInput($request->except('password', 'password_confirmation'));
    }

    /**
     * 確認画面の「確認コードを送信する」（POST /company/regist/send）。
     *
     * 届いた内容をもう一度検証してから、セッションに仮置きして確認コードを送る。企業と担当者はまだ作らない。
     * 送れなかったときも、コードの入力画面へ進め、そこの「再送する」でやり直せるようにする。
     */
    public function send(Request $request): RedirectResponse
    {
        // 確認画面のhiddenから届いた内容を、もう一度すべて検証する。失敗したら、
        // 入力欄のある入力画面へ戻す
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('company.regist.create')
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
            return redirect()->route('company.regist.verify')->with('error', $error);
        }

        return redirect()->route('company.regist.verify');
    }

    // 確認コードの入力画面（GET /company/regist/verify）。
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('company.regist.create');
        }

        return view('company.auth.regist-verify', [
            'email' => $pending['email'],
        ]);
    }

    // 確認コードの照合と、企業と担当者の登録（POST /company/regist/verify）。
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('company.regist.create');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・メールアドレス単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $pending['email']);

        if ($throttle->isBlocked()) {
            return redirect()->route('company.regist.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、仮置きした入力のメールアドレスが同じかも確かめる
        // （別のアドレスで送り直すと、仮置きもコードも置き換わるので、通常は同じ）
        $email = (new MemberVerificationCode(CompanyUser::class))->verifyForAddress($request, self::PURPOSE, $validated['code']);

        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route('company.regist.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 企業と最初の担当者を、申請中で作る。企業IDは、企業を作った直後にidと同じ番号が入る
        // （App\Models\Company）。操作ログは、作った担当者を操作した人にして残す
        [$company, $user] = DB::transaction(function () use ($pending) {
            $company = Company::create([
                'name' => $pending['name'],
                'kana' => $pending['kana'] ?? null,
                'representative' => $pending['representative'] ?? null,
                'zip' => $pending['zip'] ?? null,
                'prefecture' => $pending['prefecture'] ?? null,
                'address' => $pending['address'] ?? null,
                'tel' => $pending['tel'],
                'url' => $pending['url'] ?? null,
                'status' => CompanyStatus::Pending,
            ]);

            $user = CompanyUser::create([
                'company_id' => $company->id,
                'login_id' => $pending['login_id'],
                'name' => $pending['user_name'],
                'email' => $pending['email'],
                'password' => $pending['password_hash'],
            ]);

            OperationRecorder::record(OperationLogAction::Create, $company, operator: $user);

            return [$company, $user];
        });

        $request->session()->forget(self::PENDING_SESSION_KEY);

        // 申請があったことを、運営へメールで知らせる。送れなくても申請は取り消さず、ログにだけ残す。
        // 申請中の企業は、管理画面の一覧でも探せる
        try {
            Mail::send(new TemplatedMail('company_registration_staff', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'staff_mail' => config('members.company.registration_staff_email'),
                'company_code' => $company->code,
                'company_name' => $company->name,
                'user_name' => $user->name,
                'applied_at' => now()->format('Y年n月j日 H:i'),
                'admin_url' => route('admin.companies.show', $company),
            ]));
        } catch (\Throwable $e) {
            Log::error('Company\RegistrationController: 企業の登録の申請のお知らせメールの送信に失敗しました。', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);
        }

        // 承認されるまではログインできないので、ログインはさせず、承認待ちの案内へ
        return redirect()->route('company.regist.thanks');
    }

    // 確認コードの再送信（POST /company/regist/verify/resend）。
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        // 仮置きが無ければ（確認画面を通っていない・期限切れ）、入力画面へ
        if ($pending === null) {
            return redirect()->route('company.regist.create');
        }

        $error = $this->sendCode($request, $pending);

        if ($error !== null) {
            return redirect()->route('company.regist.verify')->with('error', $error);
        }

        return redirect()->route('company.regist.verify')->with('status', '確認コードを再送しました。');
    }

    /**
     * コード入力画面の「入力内容を修正する」（POST /company/regist/verify/back）。
     * 仮置きした内容を入力画面に戻す。パスワードは確認画面の「戻る」と同じく
     * 入力し直してもらう（仮置きしているのはハッシュ値なので、そもそも戻せない）。
     */
    public function backFromVerify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request) ?? [];

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('company.regist.create')
            ->withInput(Arr::except($pending, ['password_hash']));
    }

    // 申請の完了の画面（GET /company/regist/thanks）。承認をお待ちください、と案内する
    public function thanks(): View
    {
        return view('company.auth.regist-thanks');
    }

    // 確認コードを送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す。
    private function sendCode(Request $request, array $pending): ?string
    {
        // 1つのメールアドレスへ送った回数を数える（大文字・小文字は同じアドレスとして数える）
        $key = 'company-register-mail:'.mb_strtolower($pending['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAIL_LIMIT_PER_ADDRESS)) {
            return 'このメールアドレスへの確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::MAIL_LIMIT_DECAY_SECONDS);

        // 宛名は、ログインの確認コードと同じく、企業名と担当者の氏名
        $name = trim($pending['name'].' '.$pending['user_name']);

        if (! (new MemberVerificationCode(CompanyUser::class))->issueForAddress($request, $pending['email'], $name, self::PURPOSE)) {
            return '確認コードの送信に失敗しました。時間をおいて「確認コードを再送する」からお試しください。';
        }

        return null;
    }

    // セッションに仮置きした入力内容（無ければnull）
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }
}
