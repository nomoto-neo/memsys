<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyStatus;
use App\Enums\CsvEncoding;
use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Rules\PhoneNumberRule;
use App\Support\CsvDownload;
use App\Support\FormFlow;
use App\Support\OperationRecorder;
use App\Support\SearchableList;
use App\Support\TrustedDeviceManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 管理画面の企業会員の管理。企業の一覧・詳細・編集と、申請の承認・却下、利用の停止・再開。
 *
 * 企業は、企業の側が自分で登録する（App\Http\Controllers\Company\RegistrationController）。
 * 登録された企業は「申請中」で、ここで承認すると担当者がログインできるようになる。
 * 企業IDと状態は、編集の画面では変えない。状態は、承認・却下・停止・再開のボタンで変える。
 */
class CompanyController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら additionalFields() などを用意する。
    use FormFlow;

    // CSVダウンロードの共通処理はCsvDownloadトレイトが提供する。
    // クラス側は csvColumns() と、必要なら csvCustomColumn() を用意する。
    use CsvDownload;

    // ---- 一覧・検索（SearchableList）の設定 ----

    // 一覧画面のルート名。セッションキー名の識別子としても使用。
    // 更新・却下の後の戻り先（?back付きの一覧）にも使う。
    private const INDEX_ROUTE = 'admin.companies.index';

    // フリーワード検索の検索対象とするカラムの一覧。
    private const FREE_WORD_COLUMNS = ['name', 'kana', 'representative', 'staff_memo'];

    // 1ページに表示する件数。
    private const PER_PAGE = 20;

    // 一覧の並び順の選択肢。
    private const ORDER_OPTIONS = [
        'created_desc' => [
            'label' => '登録日が新しい順',
            'orderBy' => [
                ['created_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'created_asc' => [
            'label' => '登録日が古い順',
            'orderBy' => [
                ['created_at', 'asc'],
                ['id', 'asc'],
            ],
        ],
        'updated_desc' => [
            'label' => '更新日が新しい順',
            'orderBy' => [
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
    ];

    // ---- 項目の文字数 ----

    // 管理メモに書ける文字数。
    private const STAFF_MEMO_MAX_LENGTH = 2000;

    // 却下の理由に書ける文字数。お知らせのメールに載せる
    private const REJECT_REASON_MAX_LENGTH = 1000;

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール。企業IDと状態は、編集の画面では変えないので書かない。
    private function rules(?Company $company): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255'],
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
            // 管理メモ。企業の側には見せない、スタッフ用の欄
            'staff_memo' => ['nullable', 'string', 'max:'.self::STAFF_MEMO_MAX_LENGTH],
        ];
    }

    // 却下の理由の検証ルール。詳細画面の却下のフォームで使う
    private function rejectRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:'.self::REJECT_REASON_MAX_LENGTH],
        ];
    }

    // 保存する項目（t_companiesのカラム）。ここに書いた項目だけを保存する。
    // 最終更新者（staff_id）は入力値をそのまま保存しないので、additionalFields()で扱う。
    private function saveFieldNames(array $validated, Company $company): array
    {
        return ['name', 'kana', 'representative', 'zip', 'prefecture', 'address', 'tel', 'url', 'staff_memo'];
    }

    // saveFieldNames()に加えて保存する項目（項目名 => 値）。入力値をそのまま使わないものをここに書く。
    private function additionalFields(array $validated, Company $company): array
    {
        return [
            // 最後に更新した操作者（スタッフ）
            'staff_id' => Auth::guard('admin')->id(),
        ];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う）。
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
            'staff_memo' => $company->staff_memo,
        ];
    }

    // ---- 一覧・検索 ----

    // 一覧・検索
    // 検索条件の復元、絞り込み、並び替え、ページネーションは SearchableList::buildListData が行う
    public function index(Request $request): View|RedirectResponse
    {
        // 一覧データの読み込みとページング。担当者の人数も一緒に数える
        $result = $this->buildListData($request, Company::query()->withCount('users'));

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        // 一覧を表示
        return view('admin.companies.index', [
            'companies' => $result['paginated'],
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['orderKey'],
        ]);
    }

    // 検索対象項目の検証ルール（SearchableListが要求する）。
    // integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件
    private function srchRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50'],
            'tel' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable', 'string',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('company_status')),
            ],
        ];
    }

    // イレギュラーな検索条件の追加処理
    // DB項目と単純に比較できないものは先にここでwhere条件を追加し、処理済み(true)を返す。
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        return false;
    }

    // ---- CSVダウンロード ----

    // CSVダウンロード（一覧の今の検索条件・並び順で全件）
    public function csv(): StreamedResponse
    {
        return $this->downloadCsv(
            query: Company::query()->with('editorStaff')->withCount('users'),
            name: '企業会員一覧',
            encoding: CsvEncoding::Utf8Bom,
            header: true,
            escapeFormula: true,
        );
    }

    // CSVに出す項目。見出し => 値の場所（書き方はApp\Support\CsvDownload参照）
    private function csvColumns(): array
    {
        return [
            '企業ID' => 'code',
            '企業名' => 'name',
            'フリガナ' => 'kana',
            '代表者名' => 'representative',
            '郵便番号' => 'zip',
            '都道府県' => ['prefecture', code_table('prefectures')],
            '住所' => 'address',
            '電話番号' => 'tel',
            'ホームページURL' => 'url',
            '状態' => '@status',
            '担当者数' => 'users_count',
            '管理メモ' => 'staff_memo',
            '登録日時' => 'created_at|date:Y/m/d H:i',
            '更新日時' => 'updated_at|date:Y/m/d H:i:s',
            '最終更新者' => 'editorStaff.name',
        ];
    }

    // csvColumns()で「@名前」と書いた項目の値
    private function csvCustomColumn(string $key, Company $company): mixed
    {
        return match ($key) {
            // 状態の名前
            'status' => $company->status->label(),
        };
    }

    // ---- 詳細・編集 ----

    // 詳細画面の表示。その企業の担当者の一覧も出す
    public function show(Company $company): View
    {
        // 担当者の個人情報を出す画面なので、詳細を開いたことを操作ログに残す
        OperationRecorder::record(OperationLogAction::View, $company);

        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.companies.show', [
            'company' => $company,
            'input' => $this->formInput($company),
            'users' => $company->users()->orderBy('id')->get(),
            'rejectRequired' => required_fields($this->rejectRules()),
        ]);
    }

    // 編集フォームの表示
    public function edit(Company $company): View
    {
        return view('admin.companies.edit', [
            'company' => $company,
            'input' => $this->formInput($company, old()),
            'required' => $this->requiredFields($company),
        ]);
    }

    // 確認画面の表示
    public function confirmUpdate(Request $request, Company $company): View
    {
        return view('admin.companies.confirm', [
            'company' => $company,
            'input' => $this->confirmInput($request, $company),
        ]);
    }

    // 確認画面からの「戻る」
    public function backToEdit(Request $request, Company $company): RedirectResponse
    {
        return redirect()->route('admin.companies.edit', $company)
            ->withInput($request->except('_token'));
    }

    // 更新の実行
    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->saveData($company, $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '企業の情報を更新しました。');
    }

    // ---- 承認・却下・停止・再開 ----

    // 申請中の企業を承認する（PATCH /admin/companies/{company}/approve）。
    // 担当者がログインできるようになる。承認したことと企業IDを、担当者へメールで知らせる
    public function approve(Company $company): RedirectResponse
    {
        if (! $this->changeStatus($company, from: CompanyStatus::Pending, to: CompanyStatus::Approved)) {
            return $this->statusAlreadyChanged($company);
        }

        // 申請中の企業の担当者は、登録のときの1人。メールは状態の保存が確定した後に送り、
        // 送れなくても承認は取り消さずにログにだけ残す
        foreach ($company->users as $user) {
            $this->sendMail('company_approved', $user, [
                'company_code' => $company->code,
                'login_id' => $user->login_id,
                'login_url' => route(CompanyUser::memberRoute('login')),
            ]);
        }

        return redirect()->route('admin.companies.show', $company)
            ->with('status', '企業を承認しました。担当者へ、承認のお知らせを送りました。');
    }

    /**
     * 申請中の企業を却下する（DELETE /admin/companies/{company}/reject）。
     * 理由を担当者へメールで知らせ、企業と担当者の行を消す。申請中の企業は一度もログイン
     * していないので、残しておくデータが無い。同じ内容で申し込み直せるようにもなる。
     */
    public function reject(Request $request, Company $company): RedirectResponse
    {
        // 却下のフォームは詳細画面のモーダルの中にあるので、エラーは名前を分けて持つ
        $validated = $request->validateWithBag('reject', $this->rejectRules());

        if ($company->status !== CompanyStatus::Pending) {
            return $this->statusAlreadyChanged($company);
        }

        // 行を消す前に、お知らせの宛先を控える
        $users = $company->users()->with('company')->get();

        // 担当者と、その信頼済みの端末・パスキーを消してから、企業を消す
        DB::transaction(function () use ($company, $users) {
            foreach ($users as $user) {
                TrustedDeviceManager::forMember($user)->forgetAll($user);
                $user->passkeys()->delete();
                $user->delete();
            }

            $company->delete();

            OperationRecorder::record(OperationLogAction::Delete, $company);
        });

        // メールは削除が確定した後に送る
        foreach ($users as $user) {
            $this->sendMail('company_rejected', $user, [
                'reason' => $validated['reason'],
                'contact_url' => route('contact.create'),
            ]);
        }

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '申請を却下しました。担当者へ、お知らせを送りました。');
    }

    // 承認済みの企業を止める（PATCH /admin/companies/{company}/suspend）。担当者はログインできなくなり、
    // ログイン中の担当者も次の操作から使えなくなる（App\Http\Middleware\EnsureCompanyIsApproved）
    public function suspend(Company $company): RedirectResponse
    {
        if (! $this->changeStatus($company, from: CompanyStatus::Approved, to: CompanyStatus::Suspended)) {
            return $this->statusAlreadyChanged($company);
        }

        return redirect()->route('admin.companies.show', $company)
            ->with('status', '企業の利用を停止しました。');
    }

    // 止めた企業を、承認済みに戻す（PATCH /admin/companies/{company}/resume）
    public function resume(Company $company): RedirectResponse
    {
        if (! $this->changeStatus($company, from: CompanyStatus::Suspended, to: CompanyStatus::Approved)) {
            return $this->statusAlreadyChanged($company);
        }

        return redirect()->route('admin.companies.show', $company)
            ->with('status', '企業の利用を再開しました。');
    }

    /**
     * 企業の状態を変える。今の状態が$fromのときだけ変え、変えたらtrueを返す。
     * 画面を開いている間に、ほかのスタッフが先に状態を変えていることがあるため。
     * 操作ログには、変わった列（status）と、変わった後の状態の名前を残す。
     */
    private function changeStatus(Company $company, CompanyStatus $from, CompanyStatus $to): bool
    {
        if ($company->status !== $from) {
            return false;
        }

        DB::transaction(function () use ($company, $to) {
            $company->update([
                'status' => $to,
                'staff_id' => Auth::guard('admin')->id(),
            ]);

            OperationRecorder::record(OperationLogAction::Update, $company, ['status'], ['status' => $to->label()]);
        });

        return true;
    }

    // 状態が、押したボタンの前提と違っていたとき。詳細画面へ戻して、今の状態を見てもらう
    private function statusAlreadyChanged(Company $company): RedirectResponse
    {
        return redirect()->route('admin.companies.show', $company)
            ->with('error', 'この企業の状態は、すでに変わっています。今の状態を確かめてください。');
    }

    // 担当者へ、承認・却下のお知らせのメールを送る。メールアドレスが無ければ送らない。
    // 送れなかったときは、ログにだけ残す
    private function sendMail(string $template, CompanyUser $user, array $variables): void
    {
        if (empty($user->notificationEmail())) {
            return;
        }

        try {
            Mail::send(new TemplatedMail($template, $variables + [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_mail' => $user->notificationEmail(),
                'name' => $user->displayName(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Admin\CompanyController: 企業会員へのお知らせメールの送信に失敗しました。', [
                'template' => $template,
                'company_id' => $user->company_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
