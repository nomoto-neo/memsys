<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\AjaxFileUpload;
use App\Support\FormFlow;
use App\Support\SearchableList;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 固定ページの管理。会社概要のように、本文を書いて、訪問者の側に決まったURLで出すページ。
 * 本文をSunEditorで書くコーナーの見本でもある。ニュースの本文はsummernoteで書く。
 */
class PageController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() を用意する。
    use FormFlow;

    // 本文のエディタに挿入する画像のアップロード（入口のuploadAjaxFile()もトレイト側）。
    use AjaxFileUpload;

    // ---- 一覧・検索（SearchableList）の設定 ----

    /**
     * 一覧画面のルート名。セッションキー名の識別子としても使用。
     * 登録・更新・削除の後の戻り先（?back付きの一覧）にも使う。
     */
    private const INDEX_ROUTE = 'admin.pages.index';

    /** フリーワード検索の検索対象とするカラムの一覧。 */
    private const FREE_WORD_COLUMNS = ['title', 'slug'];

    /** 1ページに表示する件数。 */
    private const PER_PAGE = 20;

    /** 一覧の並び順の選択肢。 */
    private const ORDER_OPTIONS = [
        'updated_desc' => [
            'label' => '更新日が新しい順',
            'orderBy' => [
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'slug_asc' => [
            'label' => 'URLの名前の順',
            'orderBy' => [
                ['slug', 'asc'],
            ],
        ],
    ];

    // ---- アップロード（AjaxFileUpload）の設定 ----

    /** 画像や添付ファイルの欄は無い。本文のエディタの画像だけを扱う */
    private const UPLOAD_FILES = [];

    /** WYSIWYGからアップロードされる画像の設定。フィールド名 => 横幅(px)。 */
    private const WYSIWYG_FIELDS = [
        'body' => Page::BODY_IMAGE_WIDTH,
    ];

    // ---- 入力をそろえる処理（InputNormalizer）の設定 ----

    /** 全角と半角をそろえない項目。データの仕様なので、モデルの指定を引く */
    private const RAW_INPUT_FIELDS = Page::RAW_INPUT_FIELDS;

    // ---- このコーナーの項目の定義 ----

    /** 本文に書ける文字数。エディタが付けるHTMLのタグも数える */
    private const BODY_MAX_LENGTH = 50000;

    /** 入力の検証ルール。$pageは、新規登録ならnull、更新なら対象の行 */
    private function rules(?Page $page): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:100',
                // 訪問者の側のルートが受け付ける文字だけにする
                'regex:/^'.Page::SLUG_PATTERN.'$/',
                // 自分を除いて、ほかの行と重ならないこと
                Rule::unique(Page::class, 'slug')->ignore($page?->id),
                // ほかの画面のURLと同じ語は、開けないページになるので断る
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_string($value) && Page::isReservedSlug($value)) {
                        $fail('この名前は、ほかの画面のURLで使っているので使えません。');
                    }
                },
            ],
            'body' => ['nullable', 'string', 'max:'.self::BODY_MAX_LENGTH],
            'disp_flg' => ['required', 'boolean'],
        ] + $this->ajaxUploadRules();
    }

    /** 保存する項目（t_pagesのカラム）。ここに書いた項目だけを保存する */
    private function saveFieldNames(array $validated, Page $page): array
    {
        return ['title', 'slug', 'body', 'disp_flg'];
    }

    /** モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う） */
    private function inputFromModel(Page $page): array
    {
        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'body' => $page->body,
            'disp_flg' => $page->disp_flg ? '1' : '0',
        ];
    }

    /** 新規登録フォームの初期値。書きかけのページが出てしまわないよう、非表示から始める */
    private function defaultInput(): array
    {
        return ['disp_flg' => '0'];
    }

    /** 検証の後、確認画面の表示・保存の前に行う整形。 */
    private function prepareInput(array $validated): array
    {
        // 許可していないHTMLタグや属性を取り除く
        $validated['body'] = safe_html($validated['body'] ?? null);

        return $validated;
    }

    // ---- 一覧・検索 ----

    /**
     * 一覧・検索
     * 検索条件の復元、絞り込み、並び替え、ページネーションは SearchableList::buildListData が行う
     */
    public function index(Request $request): View|RedirectResponse
    {
        // 一覧データの読み込みとページング
        $result = $this->buildListData($request, Page::query());

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        // 一覧を表示
        return view('admin.pages.index', [
            'pages' => $result['paginated'],
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['orderKey'],
        ]);
    }

    /**
     * 検索対象項目の検証ルール（SearchableListが要求する）。
     * integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件
     */
    private function srchRules(): array
    {
        return [
            'disp_flg' => ['nullable', 'boolean'],
        ];
    }

    /**
     * イレギュラーな検索条件の追加処理
     * DB項目と単純に比較できないものは先にここでwhere条件を追加し、処理済み(true)を返す。
     */
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        return false;
    }

    // ---- 登録 ----

    /** 新規登録フォームの表示 */
    public function create(): View
    {
        return view('admin.pages.create', [
            // 初期値はdefaultInput()。old() があればそちらを優先
            'input' => $this->formInput(null, old()),
            'required' => $this->requiredFields(),
        ]);
    }

    /** 新規登録の確認画面を表示 */
    public function confirmStore(Request $request): View
    {
        return view('admin.pages.confirm', [
            'isCreate' => true,
            'page' => null,
            'input' => $this->confirmInput($request),
        ]);
    }

    /** 確認画面からの「戻る」 */
    public function backToCreate(Request $request): RedirectResponse
    {
        return redirect()->route('admin.pages.create')
            ->withInput($request->except('_token'));
    }

    /** 新規登録の実行 */
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new Page(), $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '固定ページを登録しました。');
    }

    // ---- 詳細・編集・削除 ----

    /** 詳細画面の表示 */
    public function show(Page $page): View
    {
        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.pages.show', [
            'page' => $page,
            'input' => $this->formInput($page),
        ]);
    }

    /** 編集フォームの表示 */
    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            // old() があればそちらを優先
            'input' => $this->formInput($page, old()),
            'required' => $this->requiredFields($page),
        ]);
    }

    /** 編集の確認画面を表示 */
    public function confirmUpdate(Request $request, Page $page): View
    {
        return view('admin.pages.confirm', [
            'isCreate' => false,
            'page' => $page,
            'input' => $this->confirmInput($request, $page),
        ]);
    }

    /** 確認画面からの「戻る」 */
    public function backToEdit(Request $request, Page $page): RedirectResponse
    {
        return redirect()->route('admin.pages.edit', $page)
            ->withInput($request->except('_token'));
    }

    /** 更新の実行 */
    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->saveData($page, $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '固定ページを更新しました。');
    }

    /** 削除の実行 */
    public function destroy(Page $page): RedirectResponse
    {
        $this->deleteData($page);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', '固定ページを削除しました。');
    }

    // Ajaxアップロード処理の function がトレイトから取り込まれる。
    // 実体はApp\Support\AjaxFileUpload::uploadAjaxFile()
}
