<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;
use App\Support\AjaxFileUpload;
use App\Support\CsvDownload;
use App\Support\CsvImport;
use App\Support\CsvImportSettings;
use App\Support\FormFlow;
use App\Support\SearchableList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら afterSave() などを用意する。
    use FormFlow;

    // アップロードまわりの共通処理は AjaxFileUpload トレイトが提供する。
    use AjaxFileUpload;

    // CSVダウンロードの共通処理はCsvDownloadトレイトが提供する。
    // クラス側は csvColumns() を用意する。
    use CsvDownload;

    // CSV取り込みの共通処理はCsvImportトレイトが提供する（画面・確認・実行の入口もトレイト側）。
    // クラス側は csvImportSettings() を用意する。項目の定義はダウンロードと共通の csvColumns()。
    use CsvImport;

    // ---- 一覧・検索（SearchableList）の設定 ----

    // 一覧画面のルート名。セッションキー名の識別子としても使用。
    // 登録・更新・削除の後の戻り先（?back付きの一覧）にも使う。
    private const INDEX_ROUTE = 'admin.news.index';

    // フリーワード検索の検索対象とするカラムの一覧。
    private const FREE_WORD_COLUMNS = ['title'];

    // 1ページに表示する件数。
    private const PER_PAGE = 20;

    // 一覧の並び順の選択肢。
    private const ORDER_OPTIONS = [
        'article_date_desc' => [
            'label' => '記事日付が新しい順',
            'orderBy' => [['article_date', 'desc'], ['id', 'desc']],
        ],
        'article_date_asc' => [
            'label' => '記事日付が古い順',
            'orderBy' => [['article_date', 'asc'], ['id', 'asc']],
        ],
    ];

    // ---- アップロード（AjaxFileUpload）の設定 ----

    // AjaxFileUploadが要求する設定。フィールド名 => 横幅(px)。
    // 横幅ゼロは添付ファイルで非ゼロは画像ファイル。
    // キーの末尾が".*"の場合は複数展開されるフィールド。
    private const UPLOAD_FILES = [
        'list_image' => News::LIST_IMAGE_WIDTH,
        'attach.*' => 0,
    ];

    // WYSIWYGからアップロードされる画像の設定。フィールド名 => 横幅(px)。
    private const WYSIWYG_FIELDS = [
        'body' => News::BODY_IMAGE_WIDTH,
    ];

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'article_date' => ['required', 'date'],
            'disp_flg' => ['required', 'boolean'],
            // 公開範囲。0なら一般公開、1なら会員限定。
            'members_only' => ['required', 'boolean'],
            // 掲載期間。どちらも任意。両方あるときは、終了が開始と同じかそれより後であること
            // （終了もその分を含むので、同じ日時なら1分間だけ掲載。開始が空のときは比べずに通る）。
            'publish_start_at' => ['nullable', 'date'],
            'publish_end_at' => ['nullable', 'date', 'after_or_equal:publish_start_at'],
            'body' => ['nullable', 'string'],
            // 掲載カテゴリーは複数選択なので配列で届く。1つ以上必須。
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('t_categories', 'id')],
        ] + $this->ajaxUploadRules();
    }

    // 保存する項目（t_newsのカラム）。登録・更新ともここに書いた項目だけを保存する。
    // アップロード項目（list_image等）はcommitUploads()、カテゴリーはafterSave()で
    // 別に保存するので、ここには書かない。
    // $validatedは整形後の入力値、$newsは保存先（$news->existsがfalseなら新規登録）。
    // ここから外した項目は、更新ではDBの今の値がそのまま残る（NULLにするのとは違う）。
    private function saveFieldNames(array $validated, News $news): array
    {
        return ['title', 'body', 'article_date', 'disp_flg', 'members_only', 'publish_start_at', 'publish_end_at'];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う）。
    // アップロード項目はFormFlowが足すので、ここには書かない。
    private function inputFromModel(News $news): array
    {
        return [
            'title' => $news->title,
            'body' => $news->body,
            'article_date' => optional($news->article_date)->format('Y-m-d'),
            'disp_flg' => $news->disp_flg ? '1' : '0',
            'members_only' => $news->members_only ? '1' : '0',
            // 日時の入力欄（type="datetime-local"）の値の形
            'publish_start_at' => optional($news->publish_start_at)->format('Y-m-d\TH:i'),
            'publish_end_at' => optional($news->publish_end_at)->format('Y-m-d\TH:i'),
            'category_ids' => $news->categories()->pluck('t_categories.id')->all(),
        ];
    }

    // 新規登録フォームの初期値。
    private function defaultInput(): array
    {
        return [
            'disp_flg' => '0',
            'members_only' => '0',
            'category_ids' => [],
        ];
    }

    // 検証の後、確認画面の表示・保存の前に行う整形。
    private function prepareInput(array $validated): array
    {
        // 許可していないHTMLタグや属性を取り除く
        $validated['body'] = safe_html($validated['body'] ?? null);

        return $validated;
    }

    // 保存の直後に行う、関連テーブルの更新。
    private function afterSave(News $news, array $validated): void
    {
        $news->categories()->sync($validated['category_ids']);
    }

    // 削除の直前に行う、関連テーブルの削除。
    private function beforeDelete(News $news): void
    {
        $news->categories()->detach();
    }

    // すべてのカテゴリーを表示順で取得（プルダウンやチェックボックスの選択肢の表示に使う）。
    private function allCategories()
    {
        return Category::orderBy('display_order')->get();
    }

    // ---- 一覧・検索 ----

    // 一覧・検索
    // 検索条件の復元、絞り込み、並び替え、ページネーションは SearchableList::buildListData が行う
    public function index(Request $request): View|RedirectResponse
    {
        // 一覧データの読み込みとページング
        $result = $this->buildListData($request, News::query());

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        // 一覧を表示
        return view('admin.news.index', [
            'newsList' => $result['paginated'],
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['orderKey'],
            'categories' => $this->allCategories(),
        ]);
    }

    // 検索対象項目の検証ルール（SearchableListが要求する）。
    // integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件
    private function srchRules(): array
    {
        return [
            'disp_flg' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer'],
        ];
    }

    // イレギュラーな検索条件の追加処理
    // DB項目と単純に比較できないものは先にここでwhere条件を追加し、処理済み(true)を返す。
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        if ($key === 'category_id') {
            $query->whereHas('categories', function (Builder $q) use ($value) {
                $q->where('t_categories.id', $value);
            });

            return true;
        }

        return false;
    }

    // ---- CSVダウンロード ----

    // CSVダウンロード（一覧の今の検索条件・並び順で全件）
    public function csv(): StreamedResponse
    {
        return $this->downloadCsv(
            query: News::query()->with('categories', 'attach'),
            name: 'ニュース記事一覧',
            encoding: CsvEncoding::Utf8Bom,
            header: true,
            escapeFormula: true,
        );
    }

    // CSVに出す項目。見出し => 値の場所（書き方はApp\Support\CsvColumnSet参照）。
    // CSV取り込みも同じ定義を使う。
    private function csvColumns(): array
    {
        $categories = Category::orderBy('display_order')->pluck('name', 'id')->all();

        return [
            '記事ID' => 'id|format:%06d',
            'タイトル' => 'title',
            '記事日付' => 'article_date|date:Y/m/d',
            '状態' => ['disp_flg', [1 => '表示', 0 => '非表示']],
            '公開範囲' => ['members_only', [0 => '一般公開', 1 => '会員限定']],
            '掲載開始日時' => 'publish_start_at|date:Y/m/d H:i',
            '掲載終了日時' => 'publish_end_at|date:Y/m/d H:i',
            // 掲載カテゴリーはcategoriesをたどって出すので、取り込み先の項目名（category_ids）を書く
            'カテゴリー' => ['categories.*.id', $categories, 'import' => 'category_ids'],
            '一覧用画像' => 'list_image',
            '一覧用画像の表示名' => 'list_image_origin',
            // 添付ファイルは、表示名と保存ファイル名を1件ずつ組にして横に並べる
            '添付:*' => ['attach', 'group' => [
                '添付ファイル' => 'original_name',
                '保存ファイル名' => 'filename',
            ]],
            '登録日時' => 'created_at|date:Y/m/d H:i',
            // 取り込みのとき、ダウンロードした後に画面から変更された行を見分けるのに使う
            '更新日時' => 'updated_at|date:Y/m/d H:i:s',
            // カテゴリーごとの件数を集計しやすいよう、カテゴリーの列に横展開する
            'カテゴリー:*' => ['categories.*.id', $categories, 'import' => 'category_ids'],
        ];
    }

    // ---- CSV取り込み ----

    // CSV取り込みの設定（書き方はApp\Support\CsvImportSettings参照）。
    // 記事IDが空欄の行は、新しい記事として追加する。
    private function csvImportSettings(): CsvImportSettings
    {
        return new CsvImportSettings(
            query: News::query(),
            name: 'ニュース記事一覧',
            labelColumn: 'タイトル',
            route: 'admin.news.csv-import',
            mode: CsvImportMode::Save,
            encoding: null,
            header: true,
            escapeFormula: true,
            allowInsert: true,
            maxRows: null,
        );
    }

    // ---- 登録 ----

    // 新規登録フォームの表示
    public function create(): View
    {
        return view('admin.news.create', [
            // 初期値はdefaultInput()。old() があればそちらを優先
            'input' => $this->formInput(null, old()),
            'categories' => $this->allCategories(),
            'required' => $this->requiredFields(),
        ]);
    }

    // 新規登録の確認画面を表示
    public function confirmStore(Request $request): View
    {
        return view('admin.news.confirm', [
            'isCreate' => true,
            'news' => null,
            'input' => $this->confirmInput($request),
            'categories' => $this->allCategories(),
        ]);
    }

    // 確認画面からの「戻る」
    public function backToCreate(Request $request): RedirectResponse
    {
        return redirect()->route('admin.news.create')
            ->withInput($request->except('_token'));
    }

    // 新規登録の実行
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new News(), $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', 'ニュース記事を登録しました。');
    }

    // ---- 詳細・編集・削除 ----

    // 詳細画面の表示
    public function show(News $news): View
    {
        $news->load('categories', 'attach');

        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.news.show', [
            'news' => $news,
            'input' => $this->formInput($news),
            'categories' => $news->categories,
        ]);
    }

    // 編集フォームの表示
    public function edit(News $news): View
    {
        return view('admin.news.edit', [
            'news' => $news,
            // old() があればそちらを優先
            'input' => $this->formInput($news, old()),
            'categories' => $this->allCategories(),
            'required' => $this->requiredFields($news),
        ]);
    }

    // 編集の確認画面を表示
    public function confirmUpdate(Request $request, News $news): View
    {
        return view('admin.news.confirm', [
            'isCreate' => false,
            'news' => $news,
            'input' => $this->confirmInput($request, $news),
            'categories' => $this->allCategories(),
        ]);
    }

    // 確認画面からの「戻る」
    public function backToEdit(Request $request, News $news): RedirectResponse
    {
        return redirect()->route('admin.news.edit', $news)
            ->withInput($request->except('_token'));
    }

    // 更新の実行
    public function update(Request $request, News $news): RedirectResponse
    {
        $this->saveData($news, $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', 'ニュース記事を更新しました。');
    }

    // 削除の実行
    public function destroy(News $news): RedirectResponse
    {
        $this->deleteData($news);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', 'ニュース記事を削除しました。');
    }

    // Ajaxアップロード処理の function がトレイトから取り込まれる。
    // 実体はApp\Support\AjaxFileUpload::uploadAjaxFile()
}
