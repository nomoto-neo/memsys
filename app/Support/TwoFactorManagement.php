<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ログイン中の本人による、2段階認証（認証アプリ）の管理。バックアップコードの表示と作り直し、
 * 登録の解除。コントローラーにuseして使う。設計は docs/two-factor-spec.md。
 *
 * 方式が認証アプリ（TwoFactorMethod::Totp）のときだけ意味がある。ほかの方式のサイトでは、
 * ルートを書かなくてよい。画面の入口は、方式が認証アプリのときだけ出す。
 * ログインの2段階目（コードの入力と、未登録のときのQRコードの登録）は、TwoFactorLoginにある。
 *
 * ■ コントローラーに書くもの
 * - 定数 TWO_FACTOR_GUARD               ログイン中の本人を取るガード
 * - 定数 TWO_FACTOR_BACKUP_CODES_VIEW   バックアップコードを1回だけ見せる画面のビュー。
 *                                       中で_two_factor_backup_codesを@includeする
 * - 定数 TWO_FACTOR_MANAGE_VIEW         管理の画面のビュー。twoFactorIndex()を使うときだけ。
 *                                       中で_two_factor_manageを@includeする
 * - twoFactorManageUrl($owner)          管理の画面のURL。操作の後の戻り先
 * - twoFactorHomeUrl()                  バックアップコードを見た後の移動先
 *
 * ■ このトレイトが受け持つルート
 * - twoFactorIndex()          管理の画面。会員はこれを使う。スタッフは詳細の画面に出すので使わない
 * - showNewBackupCodes()      発行した直後のバックアップコードを、1回だけ見せる（twoFactor.backupCodes）
 * - regenerateBackupCodes()   バックアップコードの作り直し（twoFactor.regenerateBackupCodes）
 * - selfReset()               登録の解除。バックアップコードでも確かめられる（twoFactor.selfReset）
 * どれもログインが要る画面なので、authのグループの中に書く。
 */
trait TwoFactorManagement
{
    /** 本人の2段階認証の管理の画面 */
    public function twoFactorIndex(): View
    {
        return view(self::TWO_FACTOR_MANAGE_VIEW, [
            'twoFactorAccount' => Auth::guard(self::TWO_FACTOR_GUARD)->user(),
        ]);
    }

    /**
     * 発行した直後のバックアップコードを、一度だけ表示する画面。
     * セッションから取り出すと同時に消えるので、再読み込みや直接URLを開いたときは、
     * 見た後の移動先へ回す。
     */
    public function showNewBackupCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('newBackupCodes');

        // もう見せた後
        if (! is_array($codes)) {
            return redirect($this->twoFactorHomeUrl());
        }

        return view(self::TWO_FACTOR_BACKUP_CODES_VIEW, [
            'codes' => $codes,
            'twoFactorNextUrl' => $this->twoFactorHomeUrl(),
            // パスキーの登録の案内は、この画面では出さずに、次の画面へ持ち越す
            'holdPasskeySuggest' => PasskeySuggestion::hold($request),
        ]);
    }

    /**
     * 本人による、バックアップコードの再発行。控えを無くした・使い切ったときのためのもの。
     *
     * ログイン中でも、今の認証アプリのコードをもう一度入力してもらってから行う。
     * ログイン中のセッションを乗っ取られていたときに、被害を広げないため。
     * 古いコードはすべて無効にし、作り直したコードを1回だけ表示する。
     */
    public function regenerateBackupCodes(Request $request): RedirectResponse
    {
        $owner = $this->twoFactorOwner();

        // 作り直しは、今の認証アプリのコードだけで確かめる
        if ($failed = $this->confirmTwoFactorCode($request, $owner, 'regenerateBackupCodes', allowBackupCode: false)) {
            return $failed;
        }

        // 作り直して、1回だけ見せる画面へ
        $codes = (new BackupCodeGenerator())->generateFor($owner);

        return redirect()->route($owner::loginRoute('twoFactor.backupCodes'))
            ->with('newBackupCodes', $codes);
    }

    /**
     * 本人による、2段階認証の登録解除。スマートフォンの機種変更や、紛失のときのためのもの。
     *
     * 実行の前に、今の認証アプリのコードか、バックアップコードで本人確認する。
     * バックアップコードも受け付けるのは、認証アプリの入った端末を無くした人が、バックアップコードで
     * ログインした後に自分で解除できるようにするため。ログイン中というだけでは解除させない。
     * パスワードを知っている人が、信頼済みの端末から自分の認証アプリに付け替えるのを防ぐため。
     *
     * バックアップコード・信頼済みの端末・パスキーも、すべて無効になる（App\Support\HasTwoFactor）。
     * ログイン状態はそのままで、次のログインでQRコードの登録画面へ進む。
     */
    public function selfReset(Request $request): RedirectResponse
    {
        $owner = $this->twoFactorOwner();

        if ($failed = $this->confirmTwoFactorCode($request, $owner, 'selfResetTwoFactor', allowBackupCode: true)) {
            return $failed;
        }

        // 解除と操作ログは、どちらかだけが残らないようにまとめて行う
        DB::transaction(function () use ($owner) {
            $owner->resetTwoFactor();

            OperationRecorder::record(OperationLogAction::TwoFactorReset, $owner, operator: $owner);
        });

        return redirect($this->twoFactorManageUrl($owner))
            ->with('status', '2段階認証の登録を解除しました。次回ログイン時にQRコードから登録し直せます。');
    }

    /**
     * 作り直しと解除に共通の前提の確認。ログイン中の本人で、認証アプリの登録が済んでいること。
     * 未登録の人には意味の無い操作なので、無いものとして扱う。
     */
    private function twoFactorOwner(): LoginAccount
    {
        $owner = Auth::guard(self::TWO_FACTOR_GUARD)->user();

        abort_unless($owner instanceof LoginAccount && $owner::usesTotp() && $owner->hasTwoFactorConfirmed(), 404);

        return $owner;
    }

    /**
     * 今の認証アプリのコードで、本人かどうかを確かめる。通らなければ、管理の画面へ戻す
     * リダイレクトを返す。通ったらnullを返す。
     * 失敗は、ログインの2段階目と同じカウンターで数える（App\Support\TwoFactorChallenge::throttle()）。
     * 認証アプリのコードとバックアップコードも、同じカウンターで数える。
     *
     * @param  string  $errorBag  エラーを入れる入れ物の名前。管理の画面にフォームが2つあるので分けている
     * @param  bool  $allowBackupCode  バックアップコードでも確かめてよいか
     */
    private function confirmTwoFactorCode(Request $request, LoginAccount $owner, string $errorBag, bool $allowBackupCode): ?RedirectResponse
    {
        // バックアップコードが送られてきたら、そちらで確かめる。エラーは、使ったほうの入力欄の下に出す
        $useBackupCode = $allowBackupCode && $request->filled('backup_code');
        $field = $useBackupCode ? 'backup_code' : 'code';

        $validated = $request->validateWithBag($errorBag, [
            $field => ['required', 'string'],
        ]);

        $challenge = new TwoFactorChallenge($owner);
        $throttle = $challenge->throttle($request);

        if ($throttle->isBlocked()) {
            return redirect($this->twoFactorManageUrl($owner))
                ->withErrors([$field => $throttle->blockedMessage('確認コード')], $errorBag);
        }

        if ($useBackupCode) {
            // バックアップコードの照合。通ったコードは使用済みになる
            $ok = $challenge->verifyBackupCode($validated[$field]);
            $failedMessage = 'バックアップコードが正しくないか、既に使用済みです。';
        } else {
            $ok = $challenge->verify($request, TwoFactorChallenge::PURPOSE_LOGIN, $validated[$field]);
            $failedMessage = '確認コードが正しくありません。';
        }

        if (! $ok) {
            $throttle->hit();

            return redirect($this->twoFactorManageUrl($owner))
                ->withErrors([$field => $failedMessage], $errorBag);
        }

        $throttle->clear();

        return null;
    }

    /** 管理の画面のURL。操作の後の戻り先 */
    abstract private function twoFactorManageUrl(LoginAccount $owner): string;

    /** バックアップコードを見た後の移動先 */
    abstract private function twoFactorHomeUrl(): string;
}
