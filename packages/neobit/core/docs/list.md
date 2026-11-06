# 一覧・検索（SearchableList）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Crud/SearchableList.php`　**実例**：`Admin\StaffController`・`Admin\MemberController`・`Admin\NewsController`・`NewsController`（訪問者向け）

## コントローラーに書くもの

```php
use SearchableList;

/** 一覧画面のルート名。セッションのキーにも、登録・更新・削除の後の戻り先にも使う */
private const INDEX_ROUTE = 'admin.members.index';
/** フリーワード（q）検索の対象カラム（使わなければ空配列） */
private const FREE_WORD_COLUMNS = ['name', 'kana'];
/** 1ページの件数 */
private const PER_PAGE = 20;
/** 並び順の選択肢（先頭が既定）。orderBy は [カラム, 方向] を書いた順に重ねる */
private const ORDER_OPTIONS = [
    'updated_desc' => ['label' => '更新日が新しい順', 'orderBy' => [['updated_at', 'desc'], ['id', 'desc']]],
    'updated_asc'  => ['label' => '更新日が古い順',   'orderBy' => [['updated_at', 'asc'],  ['id', 'asc']]],
];

public function index(Request $request): View|RedirectResponse
{
    $result = $this->listPageValues($request, Member::query());
    if ($result instanceof RedirectResponse) {
        return $result;   // ?back で戻ってきたとき
    }

    // 画面の変数の名前は、どのコーナーでも返ってきたキーと同じにする。画面を写して使い回せるようにするため。
    // まとめて渡さず1つずつ書くのは、画面で使える変数をここで見て分かるようにするため
    return view('admin.members.index', [
        'records' => $result['records'],
        'filters' => $result['filters'],
        'orderOptions' => $result['orderOptions'],
        'selectedOrder' => $result['selectedOrder'],
    ]);
}

/** 検索項目の検証ルール。キーはフォームの name で、そのまま検索するカラム名になる */
private function searchRules(): array
{
    return [
        'email' => ['nullable', 'string', 'max:255'],                       // 部分一致
        'prefecture' => ['nullable', 'array'],                               // 配列なら IN()
        'prefecture.*' => ['integer', Rule::in(code_keys('prefectures'))],  // 完全一致
    ];
}

/** カラムと直接比べられない項目（多対多、チェックボックスなど）。処理したら true */
private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
{
    return false;
}
```

## ルート

```php
Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
Route::post('/members', [AdminMemberController::class, 'storeSearchCondition'])->name('members.search');
```

`storeSearchCondition()` はトレイトにあるので、コントローラーには書きません。

## 決まりごと

- **完全一致か部分一致か**は、`searchRules()` のルールから自動で決まります。`integer`・`boolean`・`Rule::in`・`Rule::enum` のどれかがあれば完全一致、無ければ部分一致、値が配列なら `IN()`。コードやフラグに型のルールを付け忘れると部分一致になるので注意します。
- `q`（フリーワード）と `orderby`（並び順）はトレイトが使う名前なので、検索項目の名前には使えません。
- `searchRules()` に書いていない項目は捨てられます。`applyCustomSearch()` で処理する項目も、`searchRules()` には必ず書きます。
- 例：多対多の絞り込み（ニュースのカテゴリー）は `whereHas()`、「削除済みも含める」（スタッフ）は `withTrashed()` を `applyCustomSearch()` で書いています。
- 検索条件・ページ番号はセッションに保存されます。メニューから入ると（`?page` が無いと）検索条件は消えます。
- **一覧へ戻る**：詳細・編集・登録の後は `route(self::INDEX_ROUTE, ['back'])` に戻します。`?back` が付いていると、保存してあったページ番号へリダイレクトし、検索条件もそのまま戻ります。画面の「一覧へ戻る」「キャンセル」も `route('admin.xxx.index', ['back'])` にします。
- 一覧と同じ検索条件・並び順で絞り込んだクエリが欲しいとき（CSV ダウンロードなど）は `applyListConditions($query)` を使います。

## 画面（index.blade.php）

- 検索フォームは `route('admin.xxx.search')` へ POST。値は `$filters['項目名']` から出す（配列の項目は文字列の配列）。
- 並び順のプルダウンは `$orderOptions` をそのまま並べ、`$selectedOrder` を選択状態にする。
- ページ送りは `{{ $list->links('pagination::bootstrap-5') }}`。
- 削除済み（論理削除）の行は `<tr @class(['row-deleted' => $row->trashed()])>` で赤字になります（CSS はフレームワークの `global.css` にあります）。

ページ分けしない一覧（並び替えのためにすべて並べるカテゴリーなど）は、`SearchableList` を使わず `index()` で普通に取得します。
