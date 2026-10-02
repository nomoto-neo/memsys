<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Rules\PhoneNumberRule;
use App\Support\CsvDownload;
use App\Support\CsvImport;
use App\Support\CsvImportSettings;
use App\Support\FormFlow;
use App\Support\SearchableList;
use App\Support\PasswordChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら afterSave() などを用意する。
    use FormFlow;

    // CSVダウンロードの共通処理はCsvDownloadトレイトが提供する。
    // クラス側は csvColumns() と、必要なら csvCustomColumn() を用意する。
    use CsvDownload;

    // CSV取り込みの共通処理はCsvImportトレイトが提供する（画面・確認・実行の入口もトレイト側）。
    // クラス側は csvImportSettings() を用意する。項目の定義はダウンロードと共通の csvColumns()。
    use CsvImport;

    // ---- 一覧・検索（SearchableList）の設定 ----

    // 一覧画面のルート名。セッションキー名の識別子としても使用。
    // 登録・更新・削除の後の戻り先（?back付きの一覧）にも使う。
    private const INDEX_ROUTE = 'admin.members.index';

    // フリーワード検索の検索対象とするカラムの一覧。
    private const FREE_WORD_COLUMNS = ['name', 'kana'];

    // 1ページに表示する件数。
    private const PER_PAGE = 20;

    // 一覧の並び順の選択肢。
    private const ORDER_OPTIONS = [
        'updated_desc' => [
            'label' => '更新日が新しい順',
            'orderBy' => [
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'updated_asc' => [
            'label' => '更新日が古い順',
            'orderBy' => [
                ['updated_at', 'asc'],
                ['id', 'asc'],
            ],
        ],
    ];

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール
    // $memberは既存会員のインスタンス
    private function rules(?Member $member): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255',
                // 自idを除外してユニークであること
                Rule::unique(Member::class, 'email')->ignore($member?->id)],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            'birthdate' => ['nullable', 'date'],
            'prefecture' => ['nullable', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('prefectures'))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    // 保存する項目（t_membersのカラム）。ここに書いた項目だけを保存する。
    // パスワードと最終更新者（staff_id）は入力値をそのまま保存しないので、additionalFields()で扱う。
    // ここから外した項目は、更新ではDBの今の値がそのまま残る（NULLにするのとは違う）。
    private function saveFieldNames(array $validated, Member $member): array
    {
        return ['name', 'kana', 'email', 'phone', 'birthdate', 'prefecture'];
    }

    // saveFieldNames()に加えて保存する項目（項目名 => 値）。入力値をそのまま使わないものをここに書く。
    private function additionalFields(array $validated, Member $member): array
    {
        $additional = [
            // 最後に更新した操作者（スタッフ）
            'staff_id' => Auth::guard('admin')->id(),
        ];

        if (! empty($validated['password'])) {
            // パスワードは入力があったときだけハッシュ変換して更新
            $additional['password'] = Hash::make($validated['password']);
        }

        return $additional;
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う）。
    private function inputFromModel(Member $member): array
    {
        return [
            'name' => $member->name,
            'kana' => $member->kana,
            'email' => $member->email,
            'phone' => $member->phone,
            'birthdate' => optional($member->birthdate)->format('Y-m-d'),
            'prefecture' => $member->prefecture,
        ];
    }

    // 保存の直後の処理。
    private function afterSave(Member $member, array $validated): void
    {
        if ($member->wasChanged('password')) {
            // パスワードが変わったら、信頼済み端末とパスキーを無効にし、会員へ
            // お知らせのメールを送る（App\Support\PasswordChange参照）。
            PasswordChange::resetAndNotify($member, changedBy: Auth::guard('admin')->user());
        }
    }

    // ---- 一覧・検索 ----

    // 一覧・検索
    // 検索条件の復元、絞り込み、並び替え、ページネーションは SearchableList::buildListData が行う
    public function index(Request $request): View|RedirectResponse
    {
        // 一覧データの読み込みとページング
        $result = $this->buildListData($request, Member::query());

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        // 一覧を表示
        return view('admin.members.index', [
            'members' => $result['paginated'],
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
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'prefecture' => ['nullable', 'array'],
            'prefecture.*' => ['integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('prefectures'))],
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
            query: Member::query()->with('editorStaff'),
            name: '会員一覧',
            encoding: CsvEncoding::Utf8Bom,
            header: true,
            escapeFormula: true,
        );
    }

    // CSVに出す項目。見出し => 値の場所（書き方はApp\Support\CsvDownload参照）
    private function csvColumns(): array
    {
        $prefectures = code_table('prefectures');

        return [
            '会員ID' => 'id',
            'お名前' => 'name',
            'フリガナ' => 'kana',
            'メールアドレス' => 'email',
            '電話番号' => 'phone',
            '生年月日' => 'birthdate|date:Y/m/d',
            '年齢' => '@age',
            '都道府県' => ['prefecture', $prefectures],
            '登録日時' => 'created_at|date:Y/m/d H:i',
            // 取り込みのとき、ダウンロードした後に画面から変更された行を見分けるのに使う
            '更新日時' => 'updated_at|date:Y/m/d H:i:s',
            '最終更新者' => 'editorStaff.name',
            // 都道府県ごとの人数を集計しやすいよう、47都道府県の列に横展開する
            '都道府県:*' => ['prefecture', $prefectures],
        ];
    }

    // csvColumns()で「@名前」と書いた項目の値
    private function csvCustomColumn(string $key, Member $member): mixed
    {
        return match ($key) {
            // 生年月日から、今日時点の満年齢
            'age' => $member->birthdate?->age,
        };
    }

    // ---- CSV取り込み ----

    // CSV取り込みの設定（書き方はApp\Support\CsvImportSettings参照）。
    // 会員の登録は本人が行うので、取り込みは更新だけ（追加はしない）。
    private function csvImportSettings(): CsvImportSettings
    {
        return new CsvImportSettings(
            query: Member::query(),
            name: '会員一覧',
            labelColumn: 'お名前',
            route: 'admin.members.csv-import',
            mode: CsvImportMode::Save,
            encoding: null,
            header: true,
            escapeFormula: true,
            allowInsert: false,
            maxRows: null,
        );
    }

    // ---- 詳細・編集 ----

    // 詳細画面の表示
    public function show(Member $member): View
    {
        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.members.show', [
            'member' => $member,
            'input' => $this->formInput($member),
        ]);
    }

    // 編集フォームの表示
    public function edit(Member $member): View
    {
        // old() があればそちらを優先（パスワードは再表示しないので外す）
        $input = $this->formInput($member, Arr::except(old(), ['password', 'password_confirmation']));

        return view('admin.members.edit', [
            'member' => $member,
            'input' => $input,
            'required' => $this->requiredFields($member),
        ]);
    }

    // 確認画面の表示
    public function confirmUpdate(Request $request, Member $member): View
    {
        // password_confirmationはrules()に無いので、hiddenで持ち回れるように足しておく
        $input = $this->confirmInput($request, $member)
            + ['password_confirmation' => (string) $request->input('password_confirmation')];

        return view('admin.members.confirm', [
            'member' => $member,
            'input' => $input,
        ]);
    }

    // 確認画面からの「戻る」
    public function backToEdit(Request $request, Member $member): RedirectResponse
    {
        return redirect()->route('admin.members.edit', $member)
            ->withInput($request->except('_token'));
    }

    // 更新の実行
    public function update(Request $request, Member $member): RedirectResponse
    {
        $this->saveData($member, $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '会員情報を更新しました。');
    }
}
