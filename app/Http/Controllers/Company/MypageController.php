<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Rules\KatakanaRule;
use App\Rules\PhoneNumberRule;
use App\Support\FormFlow;
use App\Support\PasskeyManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 企業会員のマイページ。ログイン中の担当者が、自分の企業の情報の表示と編集、パスキーの管理を行う。
 *
 * 担当者に権限の区別は無いので、どの担当者も企業の情報を変えられる。誰が変えたかは、
 * 操作ログ（App\Support\OperationRecorder）に残る。企業の情報の編集は、確認画面を挟まずに保存する。
 * 担当者自身の情報の変更はProfileController、パスワードの変更はAuthPasswordControllerが受け持つ。
 */
class MypageController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 企業の情報の検証・保存はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() saveFieldNames() inputFromModel() を用意する。
    use FormFlow;

    // パスキーの一覧・登録・削除（passkeyIndex()など。App\Support\PasskeyManagement参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのcompany.mypage.passkeysのルートを消す。
    use PasskeyManagement;

    // ---- パスキー（PasskeyManagement）の設定 ----

    // ログイン中の担当者を取るガード。
    private const PASSKEY_GUARD = 'company';

    // パスキーの一覧画面のルート名（登録・削除などのルート名は、この後ろに.confirmなどを付ける）。
    private const PASSKEY_ROUTE = 'company.mypage.passkeys';

    // パスキーの一覧画面のビュー。
    private const PASSKEY_VIEW = 'company.mypage.passkeys';

    // 登録の前の本人確認（メールの確認コード）の試行制限（LoginThrottle）のカウンターの名前。
    private const PASSKEY_THROTTLE_SCOPE = 'company-passkey-code';

    // ---- 企業の情報の項目の定義 ----

    // 企業の情報の検証ルール。企業IDと状態は、企業の側からは変えられないので書かない。
    private function rules(?Company $company = null): array
    {
        return [
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
        ];
    }

    // 保存する項目（t_companiesのカラム）。
    private function saveFieldNames(array $validated, Company $company): array
    {
        return ['name', 'kana', 'representative', 'zip', 'prefecture', 'address', 'tel', 'url'];
    }

    // モデルの今の値から、編集画面に渡す$inputを組み立てる。
    private function inputFromModel(Company $company): array
    {
        return [
            'name' => $company->name,
            'kana' => $company->kana,
            'representative' => $company->representative,
            'zip' => $company->zip,
            'prefecture' => $company->prefecture,
            'address' => $company->address,
            'tel' => $company->tel,
            'url' => $company->url,
        ];
    }

    // ---- マイページ・企業の情報の編集 ----

    // マイページの表示（GET /company/mypage）。
    // ログイン中の担当者は、このコントローラーではいつも企業会員のガードから取る。
    public function index(): View
    {
        $user = Auth::guard(CompanyUser::memberGuard())->user();

        return view('company.mypage.index', [
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    // 企業の情報の編集フォームの表示（GET /company/mypage/edit）
    public function edit(): View
    {
        $company = Auth::guard(CompanyUser::memberGuard())->user()->company;

        // 入力欄の値。old()があればそちらを優先し、無ければ企業の今の値を使う（FormFlow::formInput()）
        return view('company.mypage.edit', [
            'company' => $company,
            'input' => $this->formInput($company, old()),
            'required' => $this->requiredFields($company),
        ]);
    }

    // 企業の情報の更新（PATCH /company/mypage/update）。
    // 確認画面を挟まないので、saveData()をそのまま呼ぶ。検証に失敗すれば、編集画面へ戻る。
    public function update(Request $request): RedirectResponse
    {
        $company = Auth::guard(CompanyUser::memberGuard())->user()->company;

        $this->saveData($company, $request);

        return redirect()->route('company.mypage')->with('status', '企業の情報を更新しました。');
    }
}
