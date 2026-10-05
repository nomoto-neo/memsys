<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Support\FormFlow;
use App\Support\OperationRecorder;
use App\Support\PasswordChange;
use App\Support\TrustedDeviceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * 管理画面の、企業会員の担当者の確認・編集・削除。入口は、企業の詳細画面の担当者の一覧。
 *
 * 担当者を足すのは、その企業の担当者の役目なので、ここに登録の画面は無い。
 * 退職した人のアカウントを止める、メールアドレスが空の人に代わりに入れる、
 * パスワードを本人の代わりに変える、といった運営の対応に使う。
 * 担当者IDは変えない。最後の1人の担当者も削除できる。
 * URLの{user}は、{company}の担当者だけを受け取る（routes/web.phpのscopeBindings()）。
 */
class CompanyUserController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 入力→確認→保存と削除の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら afterSave() などを用意する。
    use FormFlow;

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール。メールアドレスは、ほかの担当者と重なっていてもよい
    // （企業の代表アドレスを、複数の担当者が使っていることがあるため）。
    private function rules(?CompanyUser $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    // 保存する項目（t_company_usersのカラム）。ここに書いた項目だけを保存する。
    // パスワードは入力値をそのまま保存しないので、additionalFields()で扱う。
    private function saveFieldNames(array $validated, CompanyUser $user): array
    {
        return ['name', 'email'];
    }

    // saveFieldNames()に加えて保存する項目（項目名 => 値）。入力値をそのまま使わないものをここに書く。
    private function additionalFields(array $validated, CompanyUser $user): array
    {
        // パスワードが空欄なら、今のまま変えない
        if (empty($validated['password'])) {
            return [];
        }

        // パスワードは入力があったときだけハッシュ変換して更新
        return ['password' => Hash::make($validated['password'])];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う）。
    private function inputFromModel(CompanyUser $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    // 保存の直後の処理。
    private function afterSave(CompanyUser $user, array $validated): void
    {
        if ($user->wasChanged('password')) {
            // パスワードが変わったら、信頼済み端末とパスキーを無効にし、担当者へ
            // お知らせのメールを送る（App\Support\PasswordChange参照）。
            PasswordChange::resetAndNotify($user, changedBy: Auth::guard('admin')->user());
        }
    }

    // 削除の直前の処理。信頼済み端末とパスキーは、担当者の行と一緒に消す
    private function beforeDelete(CompanyUser $user): void
    {
        TrustedDeviceManager::forMember($user)->forgetAll($user);
        $user->passkeys()->delete();
    }

    // ---- 詳細・編集・削除 ----

    // 詳細画面の表示
    public function show(Company $company, CompanyUser $user): View
    {
        // 個人情報を持つコーナーなので、詳細を開いたことを操作ログに残す
        OperationRecorder::record(OperationLogAction::View, $user);

        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.company_users.show', [
            'company' => $company,
            'user' => $user,
            'input' => $this->formInput($user),
        ]);
    }

    // 編集フォームの表示
    public function edit(Company $company, CompanyUser $user): View
    {
        // old() があればそちらを優先（パスワードは再表示しないので外す）
        $input = $this->formInput($user, Arr::except(old(), ['password', 'password_confirmation']));

        return view('admin.company_users.edit', [
            'company' => $company,
            'user' => $user,
            'input' => $input,
            'required' => $this->requiredFields($user),
        ]);
    }

    // 確認画面の表示
    public function confirmUpdate(Request $request, Company $company, CompanyUser $user): View
    {
        // password_confirmationはrules()に無いので、hiddenで持ち回れるように足しておく
        $input = $this->confirmInput($request, $user)
            + ['password_confirmation' => (string) $request->input('password_confirmation')];

        return view('admin.company_users.confirm', [
            'company' => $company,
            'user' => $user,
            'input' => $input,
        ]);
    }

    // 確認画面からの「戻る」
    public function backToEdit(Request $request, Company $company, CompanyUser $user): RedirectResponse
    {
        return redirect()->route('admin.companies.users.edit', [$company, $user])
            ->withInput($request->except('_token'));
    }

    // 更新の実行。終わったら、企業の詳細画面（担当者の一覧）へ戻る
    public function update(Request $request, Company $company, CompanyUser $user): RedirectResponse
    {
        $this->saveData($user, $request);

        return redirect()->route('admin.companies.show', $company)
            ->with('status', '担当者の情報を更新しました。');
    }

    // 削除の実行。担当者の行を消す。ログイン中だった担当者は、次の操作でログアウトになる
    public function destroy(Company $company, CompanyUser $user): RedirectResponse
    {
        $this->deleteData($user);

        return redirect()->route('admin.companies.show', $company)
            ->with('status', '担当者を削除しました。');
    }
}
