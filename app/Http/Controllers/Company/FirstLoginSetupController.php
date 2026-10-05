<?php

namespace App\Http\Controllers\Company;

use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Support\LoginRedirect;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\OperationRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 既存のシステムから移した企業の、初回のログインでの登録。入力、確認コードの入力の順に進む。
 *
 * 移した企業には、氏名とメールアドレスが空の「最初の担当者」が1人だけいる。メールアドレスが
 * 空だと2段階目の確認コードを送れないので、ログインの1段階目が通った後、2段階目の代わりに
 * ここへ回す（Company\AuthSessionController::store()）。
 * ここで、氏名・メールアドレス・新しい担当者ID・新しいパスワードを決めてもらう。
 *
 * ■ 企業の情報の照合
 * 一緒に、企業のデータにある情報を1つ入力させて照合する。照合する列は、設定の
 * config('members.company.identity_check_column')。見本のサイトでは電話番号。
 * 既存のシステムのIDとパスワードは、漏れている前提で扱うため。IDとパスワードを知っているだけの
 * 他人が、自分のメールアドレスを登録して、アカウントを自分のものにすることを防ぐ。
 * 照合の失敗は、試行制限（LoginThrottle）で数える。
 * 照合する列の値が空の企業は、本人では登録できない。スタッフが管理画面からメールアドレスを入れる。
 *
 * ■ パスワード
 * 今までのパスワードは、全社で使い回していたものなので、ここで必ず変えてもらう。
 *
 * ■ 途中の状態
 * 1段階目が通った担当者のidをセッションに置き、登録が済むまではログインさせない。
 * 途中でやめたときは、次のログインでもう一度ここへ回る。
 * 新しく始めるサイトでは、メールアドレスが空の担当者がいないので、この画面は使われない。
 * このコントローラーと、routes/web.phpのcompany.login.setupのルートを消してよい。
 */
class FirstLoginSetupController extends Controller
{
    // 1段階目が通って、登録を待っている担当者のidを置くセッションキー。
    // Company\AuthSessionController::store()が置く
    public const USER_SESSION_KEY = 'company.login.setup.user_id';

    // コード入力待ちの入力内容（パスワードはハッシュ化済み）を仮置きするセッションキー。
    private const PENDING_SESSION_KEY = 'company.login.setup.pending';

    private const PURPOSE = MemberVerificationCode::PURPOSE_FIRST_LOGIN;

    // 企業の情報の照合の試行制限（LoginThrottle）のカウンターの名前。アカウントは担当者のidで区別する。
    private const IDENTITY_THROTTLE_SCOPE = 'company-setup-identity';

    // 確認コードの試行制限（LoginThrottle）のカウンターの名前。
    private const CODE_THROTTLE_SCOPE = 'company-setup-code';

    // 1つのメールアドレスへ確認コードを送れる回数の上限と、数える期間。メールアドレスを
    // 自由に入力できる画面なので、他人のアドレスに確認コードを大量に送りつけられるのを防ぐ。
    private const MAIL_LIMIT_PER_ADDRESS = 5;

    private const MAIL_LIMIT_DECAY_SECONDS = 3600;

    // 登録フォームの検証ルール。担当者IDは、その企業の中で重ならないこと。今の担当者IDのままでもよい。
    // identityは、企業の情報と照合する値。password_confirmationは、confirmedルールで
    // passwordと照合するので、ここには書かない。
    private function rules(CompanyUser $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'login_id' => [
                'required', 'string', 'max:50', 'regex:'.CompanyUser::LOGIN_ID_PATTERN,
                Rule::unique(CompanyUser::class, 'login_id')->where('company_id', $user->company_id)->ignore($user->id),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'identity' => ['required', 'string', 'max:255'],
        ];
    }

    // 登録フォームの表示（GET /company/login/setup）
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        // 1段階目を済ませていなければ、ログイン画面へ戻す
        if ($user === null) {
            return redirect()->route('company.login');
        }

        return view('company.auth.login-setup', [
            'user' => $user,
            // 照合する値が企業のデータに無ければ、本人では登録できない。フォームを出さずに案内だけ出す
            'canSetup' => $this->identityOf($user) !== '',
            // パスワードと照合の値は再表示しない。担当者IDは、今の値を初期値にする
            'input' => Arr::except(old(), ['password', 'password_confirmation', 'identity']) + ['login_id' => $user->login_id],
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules($user), ['password_confirmation']),
        ]);
    }

    /**
     * 登録フォームの送信（POST /company/login/setup）。
     * 入力を検証し、企業の情報と照合してから、セッションに仮置きして確認コードを送る。まだ保存はしない。
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        // 1段階目を済ませていなければ、ログイン画面へ戻す
        if ($user === null) {
            return redirect()->route('company.login');
        }

        $validated = $request->validate($this->rules($user));

        // 今までのパスワードは全社で使い回していたものなので、同じものは使わせない
        if (Hash::check($validated['password'], (string) $user->password)) {
            return $this->backToForm($request, ['password' => '今までのパスワードとは別のパスワードを決めてください。']);
        }

        // 企業の情報の照合。失敗回数による試行制限（IP単位・担当者id単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::IDENTITY_THROTTLE_SCOPE, $request->ip(), $user->id);

        if ($throttle->isBlocked()) {
            return $this->backToForm($request, ['identity' => $throttle->blockedMessage('照合')]);
        }

        $expected = $this->identityOf($user);

        if ($expected === '' || $this->normalizeIdentity($validated['identity']) !== $expected) {
            $throttle->hit();

            // 操作ログ。1段階目は通っているので、誰の失敗かが分かる
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['step' => '初回の登録の照合'], operator: $user);

            return $this->backToForm($request, ['identity' => 'ご登録の内容と一致しません。']);
        }

        $throttle->clear();

        // 入力内容をセッションに仮置きする（パスワードは平文で持たず、ハッシュ値にする。
        // 照合の値は、もう使わないので持たない）
        $pending = Arr::only($validated, ['name', 'email', 'login_id']);
        $pending['password_hash'] = Hash::make($validated['password']);

        $request->session()->put(self::PENDING_SESSION_KEY, $pending);

        // 確認コードを送り、コード入力画面へ（送れなかったときも、画面の「再送する」でやり直せる）
        $error = $this->sendCode($request, $user, $pending);

        if ($error !== null) {
            return redirect()->route('company.login.setup.verify')->with('error', $error);
        }

        return redirect()->route('company.login.setup.verify');
    }

    // 確認コードの入力画面（GET /company/login/setup/verify）
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        // 1段階目か、登録フォームを済ませていなければ、登録フォームへ戻す（そこからログイン画面へ回る）
        if ($this->pendingUser($request) === null || $pending === null) {
            return redirect()->route('company.login.setup');
        }

        return view('company.auth.login-setup-verify', [
            'email' => $pending['email'],
        ]);
    }

    // 確認コードの照合と、担当者の情報の保存、本ログイン（POST /company/login/setup/verify）
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $pending = $this->pending($request);

        // 1段階目か、登録フォームを済ませていなければ、登録フォームへ戻す（そこからログイン画面へ回る）
        if ($user === null || $pending === null) {
            return redirect()->route('company.login.setup');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・担当者id単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::CODE_THROTTLE_SCOPE, $request->ip(), $user->id);

        if ($throttle->isBlocked()) {
            return redirect()->route('company.login.setup.verify')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、仮置きした入力のメールアドレスが同じかも確かめる
        $email = (new MemberVerificationCode(CompanyUser::class))->verifyForAddress($request, self::PURPOSE, $validated['code']);

        if ($email === null || $email !== $pending['email']) {
            $throttle->hit();

            return redirect()->route('company.login.setup.verify')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 担当者の情報を保存する。古い方式のパスワードが残っていれば、もう要らないので消す。
        // 検証の後で同じ担当者IDが使われたときは、一意制約の違反になるので、入力し直してもらう
        try {
            DB::transaction(function () use ($user, $pending) {
                $user->forceFill([
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'login_id' => $pending['login_id'],
                    'password' => $pending['password_hash'],
                    'legacy_password' => null,
                ]);

                $changed = OperationRecorder::loggableFields($user, array_keys($user->getDirty()));

                $user->save();

                OperationRecorder::record(OperationLogAction::Update, $user, $changed, operator: $user);
            });
        } catch (UniqueConstraintViolationException $e) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return redirect()->route('company.login.setup')
                ->withInput(Arr::except($pending, ['password_hash']))
                ->withErrors(['login_id' => 'この担当者IDは、すでに使われています。']);
        }

        // 確認コードまで通った時点で、初めて本ログインにする。セッション固定攻撃への対策の
        // regenerate()も、このときに行う
        $request->session()->forget([self::USER_SESSION_KEY, self::PENDING_SESSION_KEY]);

        Auth::guard(CompanyUser::memberGuard())->login($user);
        $request->session()->regenerate();

        // ログインが必要な画面から来た場合はその画面へ、そうでなければマイページへ
        return redirect(LoginRedirect::forMember(CompanyUser::class))
            ->with('status', '登録が完了しました。次回からは、新しい担当者IDとパスワードでログインしてください。');
    }

    // 確認コードの再送信（POST /company/login/setup/verify/resend）
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $pending = $this->pending($request);

        if ($user === null || $pending === null) {
            return redirect()->route('company.login.setup');
        }

        $error = $this->sendCode($request, $user, $pending);

        if ($error !== null) {
            return redirect()->route('company.login.setup.verify')->with('error', $error);
        }

        return redirect()->route('company.login.setup.verify')->with('status', '確認コードを再送しました。');
    }

    /**
     * コード入力画面の「入力内容を修正する」（POST /company/login/setup/verify/back）。
     * 仮置きした内容を登録フォームに戻す。パスワードと照合の値は、入力し直してもらう。
     */
    public function backFromVerify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request) ?? [];

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('company.login.setup')
            ->withInput(Arr::except($pending, ['password_hash']));
    }

    // 確認コードを送る。送れなかった場合は画面に出すメッセージを、送れた場合はnullを返す。
    private function sendCode(Request $request, CompanyUser $user, array $pending): ?string
    {
        // 1つのメールアドレスへ送った回数を数える（大文字・小文字は同じアドレスとして数える）
        $key = 'company-setup-mail:'.mb_strtolower($pending['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAIL_LIMIT_PER_ADDRESS)) {
            return 'このメールアドレスへの確認コードの送信回数が上限に達しました。しばらく時間をおいてから、もう一度お試しください。';
        }

        RateLimiter::hit($key, self::MAIL_LIMIT_DECAY_SECONDS);

        // 宛名は、ログインの確認コードと同じく、企業名と担当者の氏名
        $name = trim($user->company->name.' '.$pending['name']);

        if (! (new MemberVerificationCode(CompanyUser::class))->issueForAddress($request, $pending['email'], $name, self::PURPOSE)) {
            return '確認コードの送信に失敗しました。時間をおいて「確認コードを再送する」からお試しください。';
        }

        return null;
    }

    // 入力エラーで登録フォームへ戻す。パスワードと照合の値は、入力し直してもらう
    private function backToForm(Request $request, array $errors): RedirectResponse
    {
        return redirect()->route('company.login.setup')
            ->withInput($request->except('password', 'password_confirmation', 'identity'))
            ->withErrors($errors);
    }

    // 企業のデータにある、照合する値。比べられる形にそろえたもの。値が無ければ空の文字
    private function identityOf(CompanyUser $user): string
    {
        $column = config('members.company.identity_check_column');

        return $this->normalizeIdentity((string) $user->company->getAttribute($column));
    }

    // 照合する値を、比べられる形にそろえる。全角の英数字を半角にし、空白とハイフンを除き、
    // 英字は小文字にする。電話番号や郵便番号を、書き方の違いで落とさないため
    private function normalizeIdentity(string $value): string
    {
        $value = mb_convert_kana($value, 'as');

        return mb_strtolower((string) preg_replace('/[\s\-‐－ー―]/u', '', $value));
    }

    // 1段階目が通って、登録を待っている担当者（いなければnull）。
    // 登録が済んでいる担当者と、承認済みでない企業の担当者は、ここでは扱わない
    private function pendingUser(Request $request): ?CompanyUser
    {
        $id = $request->session()->get(self::USER_SESSION_KEY);
        $user = $id !== null ? CompanyUser::find($id) : null;

        if ($user === null || ! $user->needsFirstLoginSetup() || ! $user->company->isApproved()) {
            return null;
        }

        return $user;
    }

    // セッションに仮置きした入力内容（無ければnull）
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }
}
