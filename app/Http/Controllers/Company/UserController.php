<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginProfileNotice;
use Neobit\Core\Crud\FormFlow;
use Neobit\Core\Models\TeamInvitation;
use Neobit\Core\Team\TeamInvitationManager;

/**
 * 企業会員のマイページの、担当者の管理。同じ企業の担当者の一覧・招待・編集・削除。
 *
 * 担当者に権限の区別は無いので、どの担当者も、招待と、ほかの担当者の編集・削除ができる。
 * 誰が行ったかは、操作ログ（Neobit\Core\Operations\OperationRecorder）に残る。
 * - 担当者は、招待のメールで足す（Neobit\Core\Team\TeamInvitationManager）。ここで担当者IDや
 *   パスワードを決めて作ることはしない
 * - ほかの担当者の編集は、氏名とメールアドレスだけ。担当者IDとパスワードは変えない
 * - 自分自身は削除できない。担当者が1人もいない企業を、企業の側の操作では作らないため
 * - 自分の情報の変更は、ProfileControllerが受け持つ
 * 扱うのは、ログイン中の担当者と同じ企業の担当者と招待だけ。ほかの企業のものは404にする。
 */
class UserController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // ほかの担当者の情報の検証・保存と、削除はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() saveFieldNames() inputFromModel() と、afterSave() beforeDelete() を用意する。
    use FormFlow;

    // ---- 担当者の情報の項目の定義 ----

    /**
     * 担当者の情報の検証ルール。メールアドレスは、同じ企業のほかの担当者と重ならないこと
     * （CompanyUser::uniqueEmailInCompany()）。
     */
    private function rules(?CompanyUser $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', CompanyUser::uniqueEmailInCompany($this->me()->company_id, $user)],
        ];
    }

    /** 招待のフォームの検証ルール。すでに担当者が使っているアドレスには、招待を送れない */
    private function inviteRules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', CompanyUser::uniqueEmailInCompany($this->me()->company_id)],
        ];
    }

    /** 保存する項目（t_company_usersのカラム）。担当者IDとパスワードは、ここでは変えない */
    private function saveFieldNames(array $validated, CompanyUser $user): array
    {
        return ['name', 'email'];
    }

    /** モデルの今の値から、編集画面に渡す$inputを組み立てる。 */
    private function inputFromModel(CompanyUser $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * 保存の直後の処理。情報が変わったことを、変えられた担当者へメールで知らせる。
     * 本人が変えたのではないので、本人が気付けるようにする（Neobit\Core\Auth\LoginProfileNotice参照）。
     */
    private function afterSave(CompanyUser $user, array $validated, array $changedFields): void
    {
        LoginProfileNotice::send($user, $changedFields);
    }

    /**
     * 削除の直前の処理。ログインのための記録（認証アプリの登録・バックアップコード・信頼済み端末・
     * パスキー）は、担当者の行と一緒に消す
     */
    private function beforeDelete(CompanyUser $user): void
    {
        $user->resetTwoFactor();
    }

    // ---- 担当者の一覧 ----

    /** 担当者の一覧（GET /company/mypage/users）。招待中の人も出す */
    public function index(): View
    {
        $me = $this->me();

        return view('company.users.index', [
            'me' => $me,
            'users' => $me->company->users()->orderBy('id')->get(),
            'invitations' => TeamInvitationManager::pending($me->company),
        ]);
    }

    // ---- 招待 ----

    /** 招待のフォームの表示（GET /company/mypage/users/invite） */
    public function inviteForm(): View
    {
        return view('company.users.invite', [
            'input' => old(),
            'required' => required_fields($this->inviteRules()),
            // 案内文に出す、リンクの期限の日数
            'validDays' => TeamInvitationManager::VALID_DAYS,
        ]);
    }

    /** 招待のメールを送る（POST /company/mypage/users/invite） */
    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->inviteRules());

        TeamInvitationManager::invite($this->me()->company, $validated['email']);

        return redirect()->route('company.users.index')
            ->with('status', '招待のメールを送りました。');
    }

    /**
     * 招待のメールを送り直す（POST /company/mypage/users/invitations/{invitation}/resend）。
     * リンクと期限が新しくなり、前のメールのリンクは使えなくなる。招待した本人だけができる
     */
    public function resendInvitation(TeamInvitation $invitation): RedirectResponse
    {
        $this->abortUnlessOwnInvitation($invitation);

        // 送り直せるのは、招待した本人だけ。ほかの担当者は、新しく招待する
        abort_unless($invitation->isSentBy($this->me()), 404);

        TeamInvitationManager::resend($invitation);

        return redirect()->route('company.users.index')
            ->with('status', '招待のメールを送り直しました。');
    }

    /**
     * 招待を取り消す（DELETE /company/mypage/users/invitations/{invitation}）。
     * 招待した本人でなくても、同じ企業の担当者なら取り消せる
     */
    public function cancelInvitation(TeamInvitation $invitation): RedirectResponse
    {
        $this->abortUnlessOwnInvitation($invitation);

        TeamInvitationManager::cancel($invitation);

        return redirect()->route('company.users.index')
            ->with('status', '招待を取り消しました。');
    }

    // ---- ほかの担当者の編集・削除 ----

    /** 編集フォームの表示（GET /company/mypage/users/{user}/edit） */
    public function edit(CompanyUser $user): View|RedirectResponse
    {
        $this->abortUnlessOwnCompany($user->company_id);

        // 自分の情報は、専用の画面で変える
        if ($user->is($this->me())) {
            return redirect()->route('company.mypage.profile');
        }

        // 入力欄の値。old()があればそちらを優先し、無ければ担当者の今の値を使う（FormFlow::formInput()）
        return view('company.users.edit', [
            'user' => $user,
            'input' => $this->formInput($user, old()),
            'required' => $this->requiredFields($user),
        ]);
    }

    /**
     * 担当者の情報の更新（PATCH /company/mypage/users/{user}）。
     * 確認画面を挟まないので、saveData()をそのまま呼ぶ。検証に失敗すれば、編集画面へ戻る。
     */
    public function update(Request $request, CompanyUser $user): RedirectResponse
    {
        $this->abortUnlessOwnCompany($user->company_id);

        // 自分の情報は、専用の画面で変える
        if ($user->is($this->me())) {
            return redirect()->route('company.mypage.profile');
        }

        $this->saveData($user, $request);

        return redirect()->route('company.users.index')->with('status', '担当者の情報を更新しました。');
    }

    /** 担当者の削除（DELETE /company/mypage/users/{user}）。自分自身は削除できない */
    public function destroy(CompanyUser $user): RedirectResponse
    {
        $this->abortUnlessOwnCompany($user->company_id);

        if ($user->is($this->me())) {
            return redirect()->route('company.users.index')
                ->with('error', '自分自身は削除できません。');
        }

        $this->deleteData($user);

        return redirect()->route('company.users.index')->with('status', '担当者を削除しました。');
    }

    // ---- 共通 ----

    /** ログイン中の担当者。このコントローラーではいつも企業会員のガードから取る */
    private function me(): CompanyUser
    {
        return Auth::guard(CompanyUser::loginGuard())->user();
    }

    /**
     * URLで指定された担当者が、ログイン中の担当者と同じ企業の人でなければ404にする。
     * ほかの企業の担当者がいるかどうかを、知らせないため
     */
    private function abortUnlessOwnCompany(int $companyId): void
    {
        abort_unless($companyId === $this->me()->company_id, 404);
    }

    /** URLで指定された招待が、ログイン中の担当者の企業への招待でなければ404にする */
    private function abortUnlessOwnInvitation(TeamInvitation $invitation): void
    {
        abort_unless($this->me()->company->is($invitation->team), 404);
    }
}
