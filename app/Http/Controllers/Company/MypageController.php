<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginAccount;
use Neobit\Core\Auth\PasskeyManagement;
use Neobit\Core\Auth\TwoFactorManagement;
use Neobit\Core\Crud\FormFlow;
use Neobit\Core\Rules\KatakanaRule;
use Neobit\Core\Rules\PhoneNumberRule;

/**
 * 企業会員のマイページ。ログイン中の担当者が、自分の企業の情報の表示と編集、パスキーの管理を行う。
 *
 * 担当者に権限の区別は無いので、どの担当者も企業の情報を変えられる。誰が変えたかは、
 * 操作ログ（Neobit\Core\Operations\OperationRecorder）に残る。企業の情報の編集は、確認画面を挟まずに保存する。
 * 担当者自身の情報の変更はProfileController、パスワードの変更はAuthPasswordControllerが受け持つ。
 */
class MypageController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 企業の情報の検証・保存はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() inputFromModel() saveValues() を用意する。
    use FormFlow;

    // パスキーの一覧・登録・削除（passkeyIndex()など。Neobit\Core\Auth\PasskeyManagement参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのcompany.mypage.passkeysのルートを消す。
    use PasskeyManagement;

    // ログイン中の本人による、バックアップコードの発行と、認証アプリの登録の解除（twoFactorIndex()など。
    // Neobit\Core\Auth\TwoFactorManagement参照）。
    use TwoFactorManagement;

    // ---- 2段階認証の管理（TwoFactorManagement）の設定 ----

    /** ログイン中の本人を取るガード。 */
    private const TWO_FACTOR_GUARD = 'company';

    /** 管理の画面のビュー。 */
    private const TWO_FACTOR_MANAGE_VIEW = 'company.mypage.two-factor';

    /** バックアップコードを1回だけ見せる画面のビュー。 */
    private const TWO_FACTOR_BACKUP_CODES_VIEW = 'company.mypage.two-factor-backup-codes';

    // ---- パスキー（PasskeyManagement）の設定 ----

    /** ログイン中の担当者を取るガード。 */
    private const PASSKEY_GUARD = 'company';

    /** パスキーの一覧画面のルート名（登録・削除などのルート名は、この後ろに.confirmなどを付ける）。 */
    private const PASSKEY_ROUTE = 'company.mypage.passkeys';

    /** パスキーの一覧画面のビュー。 */
    private const PASSKEY_VIEW = 'company.mypage.passkeys';

    // ---- 企業の情報の項目の定義 ----

    /** 企業の情報の検証ルール。企業IDと状態は、企業の側からは変えられないので書かない。 */
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

    /** モデルの今の値から、編集画面に渡す$inputを組み立てる。 */
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

    /**
     * マイページの表示（GET /company/mypage）。
     * ログイン中の担当者は、このコントローラーではいつも企業会員のガードから取る。
     */
    public function index(): View
    {
        $user = Auth::guard(CompanyUser::loginGuard())->user();

        return view('company.mypage.index', [
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    /** 企業の情報の編集フォームの表示（GET /company/mypage/edit） */
    public function edit(): View
    {
        $company = Auth::guard(CompanyUser::loginGuard())->user()->company;

        // 入力欄の値。old()があればそちらを優先し、無ければ企業の今の値を使う（FormFlow::formInput()）
        return view('company.mypage.edit', [
            'company' => $company,
            'input' => $this->formInput($company, old()),
            'required' => $this->requiredFields($company),
        ]);
    }

    /**
     * 企業の情報の更新（PATCH /company/mypage/update）。
     * 確認画面を挟まないので、saveData()をそのまま呼ぶ。検証に失敗すれば、編集画面へ戻る。
     */
    public function update(Request $request): RedirectResponse
    {
        $company = Auth::guard(CompanyUser::loginGuard())->user()->company;

        $this->saveData($company, $request);

        return redirect()->route('company.mypage')->with('status', '企業の情報を更新しました。');
    }

    // ---- 保存・削除の処理（FormFlow） ----

    /** 保存する値（t_companiesの列 => 値）。ここで返した列だけを保存する。 */
    private function saveValues(array $validated, Company $company): array
    {
        // 入力値をそのまま保存する列。送られてこなかった列は null になる
        $saveFieldNames = ['name', 'kana', 'representative', 'zip', 'prefecture', 'address', 'tel', 'url'];

        return $this->inputValues($validated, $saveFieldNames);
    }

    // ---- 2段階認証の管理（TwoFactorManagement） ----

    /** 管理の画面のURL。操作の後の戻り先 */
    private function twoFactorManageUrl(LoginAccount $owner): string
    {
        return route('company.mypage.twoFactor');
    }

    /** バックアップコードを見た後の移動先 */
    private function twoFactorHomeUrl(): string
    {
        return route('company.mypage');
    }
}
