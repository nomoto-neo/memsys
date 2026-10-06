<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Support\EmailChange;
use App\Support\FormFlow;
use App\Support\MemberProfileNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 企業会員の担当者による、自分の情報（氏名・メールアドレス）の変更。
 *
 * 確認画面を挟まずに保存する。メールアドレスが変わるときだけ、新しいアドレスに送った
 * 確認コードの入力を挟む。対象は常にログイン中の本人で、担当者IDはここでは変えない。
 * 企業の情報の変更はMypageController、パスワードの変更はAuthPasswordControllerが受け持つ。
 */
class ProfileController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 検証・保存はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() saveFieldNames() inputFromModel() を用意する。
    use FormFlow;

    // メールアドレスが変わる保存に、確認コードの入力を挟む（emailChangeForm()など。App\Support\EmailChange参照）。
    // クラス側は EMAIL_CHANGE_ の定数を用意し、update()でholdForEmailChange()を呼ぶ。
    use EmailChange;

    // ---- メールアドレスの変更の確認（EmailChange）の設定 ----

    // ログイン中の担当者を取るガード。
    private const EMAIL_CHANGE_GUARD = 'company';

    // 確認コードの入力画面のルート名（照合・再送などのルート名は、この後ろに.confirmなどを付ける）。
    private const EMAIL_CHANGE_ROUTE = 'company.mypage.profile.email';

    // 確認コードの入力画面のビュー。
    private const EMAIL_CHANGE_VIEW = 'company.mypage.profile-email-verify';

    // 入力画面のルート名。
    private const EMAIL_CHANGE_EDIT_ROUTE = 'company.mypage.profile';

    // 保存の後の移動先のルート名と、そこに出すメッセージ。
    private const EMAIL_CHANGE_DONE_ROUTE = 'company.mypage';

    private const EMAIL_CHANGE_DONE_MESSAGE = 'あなたの情報を更新しました。';

    // 確認コードの試行制限（LoginThrottle）と、送信の回数の制限のカウンターの名前。
    private const EMAIL_CHANGE_THROTTLE_SCOPE = 'company-email-change-code';

    // ---- 担当者の情報の項目の定義 ----

    // 担当者の情報の検証ルール。メールアドレスは、ほかの担当者と重なっていてもよい
    // （企業の代表アドレスを、複数の担当者が使っていることがあるため）。
    private function rules(?CompanyUser $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    // 保存する項目（t_company_usersのカラム）。
    // パスワードはこのフォームでは扱わない（変更はAuthPasswordControllerの専用フォーム）。
    private function saveFieldNames(array $validated, CompanyUser $user): array
    {
        return ['name', 'email'];
    }

    // モデルの今の値から、編集画面に渡す$inputを組み立てる。
    private function inputFromModel(CompanyUser $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    // 保存の直後の処理。担当者の情報が変わったことを、本人へメールで知らせる
    // （App\Support\MemberProfileNotice参照）。
    private function afterSave(CompanyUser $user, array $validated, array $changedFields): void
    {
        MemberProfileNotice::send($user, $changedFields);
    }

    // ---- 自分の情報の編集 ----

    // 編集フォームの表示（GET /company/mypage/profile）。
    // ログイン中の担当者は、このコントローラーではいつも企業会員のガードから取る。
    public function edit(): View
    {
        $user = Auth::guard(CompanyUser::memberGuard())->user();

        // 入力欄の値。old()があればそちらを優先し、無ければ担当者の今の値を使う（FormFlow::formInput()）
        return view('company.mypage.profile', [
            'user' => $user,
            'input' => $this->formInput($user, old()),
            'required' => $this->requiredFields($user),
        ]);
    }

    // 担当者の情報の更新（PATCH /company/mypage/profile）。
    // 確認画面を挟まないので、saveData()をそのまま呼ぶ。検証に失敗すれば、編集画面へ戻る。
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard(CompanyUser::memberGuard())->user();

        // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む。
        // 保存は、コードが入力できた時点で行う（App\Support\EmailChange）
        $toVerify = $this->holdForEmailChange($request, $user);

        if ($toVerify !== null) {
            return $toVerify;
        }

        $this->saveData($user, $request);

        return redirect()->route('company.mypage')->with('status', self::EMAIL_CHANGE_DONE_MESSAGE);
    }
}
