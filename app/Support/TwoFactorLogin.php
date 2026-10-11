<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use App\Enums\TwoFactorMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ログインの、パスワードを確かめた後の流れ。個人会員・企業の担当者・スタッフで同じなので、
 * ログインのコントローラーがこのトレイトを使う。設計は docs/two-factor-spec.md。
 *
 * ■ 流れ
 * パスワードが合っても、すぐにはログインさせない。その人の2段階目の方式（モデルの定数
 * TWO_FACTOR_METHOD）で確かめてから、本ログインにする。
 * 1. 方式が「なし」なら、そのままログインを確定する
 * 2. 信頼済みの端末なら、そのままログインを確定する
 * 3. それ以外は、2段階目の画面へ進める。メールの方式なら確認コードを送る。認証アプリの方式で
 *    まだ登録していない人は、同じ画面でQRコードを読み取って登録する
 * 4. 2段階目が通ったら、ログインを確定する
 * ログインの確定は、どの道を通ってもestablishLogin()の1か所で行う。
 *
 * ■ コントローラーに書くもの
 * - 定数 LOGIN_ACCOUNT      ログインする人のモデル。例：Member::class
 * - 定数 TWO_FACTOR_VIEW    2段階目の画面のビュー。中で_two_factorを@includeする
 * - loginRedirectUrl()      ログインが確定した後の移動先
 * - ログイン画面の表示と、パスワードを確かめるところ。入力する項目が種類ごとに違うため。
 *   パスワードが合ったら、continueAfterPassword()を呼ぶ
 * - ログアウト。logoutAccount()を呼んで、移動先を返す
 *
 * ■ このトレイトが受け持つルート
 * - showVerification()  2段階目の画面（ルートの名前は login.verify）
 * - verifyCode()        照合と、本ログイン（login.verify.confirm）
 * - resendCode()        確認コードの再送信。メールの方式だけ（login.verify.resend）
 * ルートの名前の頭は、モデルが決める（App\Support\LoginAccount::loginRoute()）。
 *
 * ■ 2段階目の途中
 * まだログインしていないので、Auth::check()では見分けられない。2段階目のルートはguestにもauthにも
 * 入れず、このトレイトがセッションの値で守る。セッションのキーは種類の名前ごとに分ける。
 * 同じブラウザで会員と管理画面の両方を使っても、片方のパスワードを通した状態で、もう片方の
 * 2段階目を通せないようにするため。
 */
trait TwoFactorLogin
{
    /** パスワード確認済みの人のidを、2段階目が終わるまで持つセッションのキー */
    private function pendingSessionKey(): string
    {
        return self::LOGIN_ACCOUNT::loginType().'.login.pending_id';
    }

    /** 「ログイン状態を保持する」の値を、2段階目が終わるまで持ち越すセッションのキー */
    private function rememberSessionKey(): string
    {
        return self::LOGIN_ACCOUNT::loginType().'.login.remember';
    }

    /**
     * 認証アプリの登録の途中の秘密鍵を持つセッションのキー。画面を読み直しても
     * QRコードが変わらないようにするためのもの
     */
    private function pendingSecretSessionKey(): string
    {
        return self::LOGIN_ACCOUNT::loginType().'.login.pending_secret';
    }

    /** この種類のルートへのリダイレクト。$nameは頭を付ける前の名前 */
    private function redirectToLoginRoute(string $name): RedirectResponse
    {
        return redirect()->route(self::LOGIN_ACCOUNT::loginRoute($name));
    }

    /**
     * パスワードが合った後の流れ。2段階目が要らなければそのままログインし、要るなら
     * 2段階目の画面へ進める。
     *
     * @param  bool  $remember  「ログイン状態を保持する」のチェック
     */
    private function continueAfterPassword(Request $request, LoginAccount $account, bool $remember): RedirectResponse
    {
        // 方式が「なし」なら、2段階目を求めずにログインを確定する。判断はモデルの定数だけで行う
        if ($account::twoFactorMethod() === TwoFactorMethod::None) {
            $this->establishLogin($request, $account, $remember);

            return redirect($this->loginRedirectUrl());
        }

        // 信頼済みの端末なら、2段階目を省いてログインを確定する。認証アプリをまだ登録していない人は、
        // 省かずに登録の画面へ進める
        if (! $this->needsTotpSetup($account) && TrustedDeviceManager::for($account)->isTrusted($account, $request)) {
            $this->establishLogin($request, $account, $remember);

            return redirect($this->loginRedirectUrl());
        }

        // それ以外は、「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ
        $request->session()->put($this->pendingSessionKey(), $account->getKey());
        $request->session()->put($this->rememberSessionKey(), $remember);

        // メールの方式なら、確認コードを送る
        if (! (new TwoFactorChallenge($account))->send($request, TwoFactorChallenge::PURPOSE_LOGIN)) {
            return $this->redirectToLoginRoute('login')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->redirectToLoginRoute('login.verify');
    }

    /** 2段階目の画面。認証アプリをまだ登録していない人には、QRコードの登録を出す */
    public function showVerification(Request $request): View|RedirectResponse
    {
        $account = $this->pendingAccount($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($account === null) {
            return $this->redirectToLoginRoute('login');
        }

        // _two_factor.blade.phpに渡す値。どの状態でも使うもの
        $data = [
            'twoFactorState' => $account::twoFactorMethod()->value,
            'twoFactorLoginId' => $account->loginId(),
            'twoFactorRoutes' => [
                'login' => $account::loginRoute('login'),
                'confirm' => $account::loginRoute('login.verify.confirm'),
                'resend' => $account::loginRoute('login.verify.resend'),
            ],
        ];

        // 認証アプリが未登録なら、QRコードの登録
        if ($this->needsTotpSetup($account)) {
            // 秘密鍵は、初めて開いたときに作ってセッションに置く
            $secret = $request->session()->get($this->pendingSecretSessionKey());

            if (! is_string($secret)) {
                $secret = (new TwoFactorAuthenticator())->generateSecret();
                $request->session()->put($this->pendingSecretSessionKey(), $secret);
            }

            $data = [
                'twoFactorState' => 'setup',
                'twoFactorSecret' => $secret,
                // 認証アプリに出るアカウント名は、ログインに使う値にする
                'twoFactorQrCode' => (new TwoFactorAuthenticator())->qrCodeSvgDataUri($secret, $account->loginId()),
            ] + $data;
        }

        return view(self::TWO_FACTOR_VIEW, $data);
    }

    /** 2段階目の照合と、本ログイン */
    public function verifyCode(Request $request): RedirectResponse
    {
        $account = $this->pendingAccount($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($account === null) {
            return $this->redirectToLoginRoute('login');
        }

        // 認証アプリが未登録なら、登録の確認
        if ($this->needsTotpSetup($account)) {
            return $this->verifyTotpSetup($request, $account);
        }

        $challenge = new TwoFactorChallenge($account);
        $isTotp = $challenge->method() === TwoFactorMethod::Totp;

        // 認証アプリの画面には、コードとバックアップコードの2つのフォームがある。
        // backup_codeが送られてきたかで見分け、エラーは送られてきたほうの入力欄の下に出す
        $useBackupCode = $isTotp && $request->filled('backup_code');
        $errorField = $useBackupCode ? 'backup_code' : 'code';

        if (! $useBackupCode) {
            $validated = $request->validate([
                'code' => ['required', 'string'],
            ]);
        }

        // 失敗回数による試行制限（IP単位・人単位。App\Support\TwoFactorChallenge::throttle()）
        $throttle = $challenge->throttle($request);

        if ($throttle->isBlocked()) {
            return $this->redirectToLoginRoute('login.verify')
                ->withErrors([$errorField => $throttle->blockedMessage($isTotp ? '2段階認証' : '確認コード')]);
        }

        if ($useBackupCode) {
            // バックアップコードの照合。通ったコードは使用済みになる
            $ok = $challenge->verifyBackupCode((string) $request->input('backup_code'));
            $failedMessage = 'バックアップコードが正しくないか、既に使用済みです。';
        } else {
            $ok = $challenge->verify($request, TwoFactorChallenge::PURPOSE_LOGIN, $validated['code']);

            // メールの確認コードには期限があるので、文言を分ける
            if ($isTotp) {
                $failedMessage = '確認コードが正しくありません。';
            } else {
                $failedMessage = '確認コードが正しくないか、有効期限が切れています。';
            }
        }

        if (! $ok) {
            $throttle->hit();

            // 操作ログ。パスワードは通っているので、誰の失敗かが分かる
            OperationRecorder::record(OperationLogAction::LoginFailed, detail: ['step' => '2段階目'], operator: $account);

            return $this->redirectToLoginRoute('login.verify')
                ->withErrors([$errorField => $failedMessage]);
        }

        $throttle->clear();

        // 「この端末を記憶する」にチェックがあれば、次回から2段階目を省く
        if ($request->boolean('remember_device')) {
            TrustedDeviceManager::for($account)->remember($account);
        }

        $this->establishLogin($request, $account, (bool) $request->session()->pull($this->rememberSessionKey(), false));

        return redirect($this->loginRedirectUrl());
    }

    /**
     * 認証アプリの登録の確認。QRコードを読み取った認証アプリが正しいコードを出せているかを、
     * 1回入力してもらって確かめる。通ったら秘密鍵を本登録し、バックアップコードを発行して、
     * 本ログインにする。
     */
    private function verifyTotpSetup(Request $request, LoginAccount $account): RedirectResponse
    {
        $secret = $request->session()->get($this->pendingSecretSessionKey());

        // セッション切れなどで秘密鍵が無くなっていたら、QRコードの登録からやり直す
        if (! is_string($secret)) {
            return $this->redirectToLoginRoute('login.verify');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（App\Support\TwoFactorChallenge::throttle()）
        $throttle = (new TwoFactorChallenge($account))->throttle($request);

        if ($throttle->isBlocked()) {
            return $this->redirectToLoginRoute('login.verify')
                ->withErrors(['code' => $throttle->blockedMessage('2段階認証')]);
        }

        if (! (new TwoFactorAuthenticator())->verifyCode($secret, $validated['code'])) {
            $throttle->hit();

            return $this->redirectToLoginRoute('login.verify')
                ->withErrors(['code' => '確認コードが正しくありません。認証アプリに表示されている6桁の数字を入力してください。']);
        }

        $throttle->clear();

        // 登録の確定とバックアップコードの発行は、どちらかだけが残らないようにまとめて行う
        $backupCodes = DB::transaction(function () use ($account, $secret) {
            $account->registerTwoFactor($secret);

            return (new BackupCodeGenerator())->generateFor($account);
        });

        $request->session()->forget($this->pendingSecretSessionKey());

        $this->establishLogin($request, $account, (bool) $request->session()->pull($this->rememberSessionKey(), false));

        // バックアップコードは、この直後の1回だけ見せる。DBには元の文字列を残していないので、
        // 後からは見せられない。次のリクエストへセッションで渡す
        return $this->redirectToLoginRoute('twoFactor.backupCodes')
            ->with('newBackupCodes', $backupCodes);
    }

    /**
     * 確認コードの再送信。メールが届かない・見失った場合の救済で、メールの方式だけにある。
     * 連続送信は、routes/web.phpのthrottleで防ぐ。
     */
    public function resendCode(Request $request): RedirectResponse
    {
        $account = $this->pendingAccount($request);

        // パスワードの確認を済ませていなければ、ログイン画面へ戻す
        if ($account === null) {
            return $this->redirectToLoginRoute('login');
        }

        abort_unless($account::twoFactorMethod() === TwoFactorMethod::Email, 404);

        if (! (new TwoFactorChallenge($account))->send($request, TwoFactorChallenge::PURPOSE_LOGIN)) {
            return $this->redirectToLoginRoute('login.verify')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->redirectToLoginRoute('login.verify')->with('status', '確認コードを再送しました。');
    }

    /**
     * ログインを確定する。2段階目を通ったとき、省いたとき、求めないときの、どれもここを通る。
     * セッション固定攻撃への対策のregenerate()も、このときに行う。パスワードを確かめた時点は
     * まだ本ログインではないため。
     */
    private function establishLogin(Request $request, LoginAccount $account, bool $remember): void
    {
        Auth::guard($account::loginGuard())->login($account, $remember);

        $request->session()->forget([$this->pendingSessionKey(), $this->rememberSessionKey()]);
        $request->session()->regenerate();

        // パスキーを使うサイトなら、まだ持っていない人に、次の画面で登録を1回だけ勧める
        PasskeySuggestion::afterLogin($request, $account);
    }

    /**
     * ログアウト。この種類のログインだけを終わらせ、同じブラウザのほかのログインは残す
     * （App\Support\LoginSession）
     */
    private function logoutAccount(Request $request): void
    {
        LoginSession::logout($request, self::LOGIN_ACCOUNT::loginGuard());
    }

    /** 認証アプリの方式で、まだ登録が済んでいない人か。ログインの途中で登録を済ませてもらう */
    private function needsTotpSetup(LoginAccount $account): bool
    {
        return $account::twoFactorMethod() === TwoFactorMethod::Totp && ! $account->hasTwoFactorConfirmed();
    }

    /** パスワード確認済みで、2段階目を待っている人（いなければnull） */
    private function pendingAccount(Request $request): ?LoginAccount
    {
        $id = $request->session()->get($this->pendingSessionKey());

        return $id !== null ? self::LOGIN_ACCOUNT::find($id) : null;
    }

    /** ログインが確定した後の移動先 */
    abstract private function loginRedirectUrl(): string;
}
