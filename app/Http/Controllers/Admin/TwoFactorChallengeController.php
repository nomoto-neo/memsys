<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\BackupCodeGenerator;
use App\Support\TwoFactorChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * ログイン中のスタッフ本人による、2段階認証（認証アプリ）の管理。バックアップコードの表示と
 * 作り直し、登録の解除。
 *
 * ログインの2段階目（コードの入力と、未登録のときのQRコードの登録）は、ここには無い。
 * ログインのコントローラーが使うApp\Support\TwoFactorLoginトレイトにある。
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * 認証アプリのコードの試行制限のカウンターの名前。パスキーの登録の前の本人確認
     * （StaffController）が、ログインの2段階目などと同じカウンターで数えるために使う。
     * App\Support\TwoFactorChallenge::throttle()が作る名前と同じにしておく。
     */
    public const THROTTLE_SCOPE = 'staff-two-factor';

    /**
     * 発行した直後のバックアップコードを、一度だけ表示する画面。
     * セッションから取り出すと同時に消えるので、再読み込みや直接URLを開いたときは、
     * ダッシュボードへ戻す。
     */
    public function showNewBackupCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('newBackupCodes');

        // もう見せた後なら、ダッシュボードへ
        if (! is_array($codes)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor-backup-codes', [
            'codes' => $codes,
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
        $staff = $this->authenticatedStaffWithTwoFactor();

        if ($failed = $this->confirmByCode($request, $staff, 'regenerateBackupCodes')) {
            return $failed;
        }

        // 作り直して、1回だけ見せる画面へ
        $codes = (new BackupCodeGenerator())->generateFor($staff);

        return redirect()->route('admin.twoFactor.backupCodes')
            ->with('newBackupCodes', $codes);
    }

    /**
     * 本人による、2段階認証の登録解除。スマートフォンの機種変更などで、古い端末の
     * 認証アプリがまだ使えるうちに解除しておくためのもの。実行の前に、今のコードで本人確認する。
     *
     * バックアップコード・信頼済みの端末・パスキーも、すべて無効になる（App\Support\HasTwoFactor）。
     * ログイン状態はそのままで、次のログインでQRコードの登録画面へ進む。
     */
    public function selfReset(Request $request): RedirectResponse
    {
        $staff = $this->authenticatedStaffWithTwoFactor();

        if ($failed = $this->confirmByCode($request, $staff, 'selfResetTwoFactor')) {
            return $failed;
        }

        $staff->resetTwoFactor();

        return redirect()->route('admin.staff.show', $staff)
            ->with('status', '2段階認証の登録を解除しました。次回ログイン時にQRコードから登録し直せます。');
    }

    /**
     * regenerateBackupCodes()・selfReset()に共通の前提の確認。どちらもauth:adminの内側の
     * ルートなのでログイン済みだが、2段階認証が未登録のスタッフには意味の無い操作なので、
     * 念のためここでも確かめる。adminガードはStaffしか返さないので、nullを除けばStaffとして扱える。
     */
    private function authenticatedStaffWithTwoFactor(): Staff
    {
        $staff = Auth::guard('admin')->user();

        abort_if($staff === null || ! $staff->hasTwoFactorConfirmed(), 404);

        return $staff;
    }

    /**
     * 今の認証アプリのコードで、本人かどうかを確かめる。通らなければ、スタッフの詳細へ戻す
     * リダイレクトを返す。通ったらnullを返す。
     * 失敗は、ログインの2段階目と同じカウンターで数える（App\Support\TwoFactorChallenge::throttle()）。
     *
     * @param  string  $errorBag  エラーを入れる入れ物の名前。詳細の画面にフォームが2つあるので分けている
     */
    private function confirmByCode(Request $request, Staff $staff, string $errorBag): ?RedirectResponse
    {
        $validated = $request->validateWithBag($errorBag, [
            'code' => ['required', 'string'],
        ]);

        $challenge = new TwoFactorChallenge($staff);
        $throttle = $challenge->throttle($request);

        if ($throttle->isBlocked()) {
            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')], $errorBag);
        }

        if (! $challenge->verify($request, TwoFactorChallenge::PURPOSE_LOGIN, $validated['code'])) {
            $throttle->hit();

            return redirect()->route('admin.staff.show', $staff)
                ->withErrors(['code' => '確認コードが正しくありません。'], $errorBag);
        }

        $throttle->clear();

        return null;
    }
}
