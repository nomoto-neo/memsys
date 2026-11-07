# 詳細表示

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Crud/FormFlow.php`（`inputPageValues()`）　**実例**：`Admin\MemberController::show()`・`Admin\NewsController::show()`

## コントローラーに書くもの

詳細画面の値は、`inputPageValues($record)` で作ります（モデルの今の値。アップロード項目も含む）。入力欄と同じ形の値なので、画面は入力欄の部分ビューをそのまま使えます。

```php
public function show(News $news): View
{
    $news->load('categories', 'attach');

    return view('admin.news.show', [
        'news' => $news,
        'input' => $this->inputPageValues($news),
        'categories' => $news->categories,
    ]);
}
```

## ルート

```php
Route::get('/news/{news}', [AdminNewsController::class, 'show'])->name('news.show');
```

- `/news/csv` のような固定の URL より後ろに書きます（[structure.md](structure.md)）。
- 削除済みの行も見せるときは、`->withTrashed()` を付けます（実例：スタッフ。削除を取り消すボタンを出すため）。

## 画面（show.blade.php）

入力欄と同じ `_fields.blade.php` を、読み取り専用で読みます。

```blade
@include('admin.news._fields', [
    'input' => $input, 'model' => $news, 'categories' => $categories,
    'readonly' => ' readonly', 'disabled' => ' disabled', 'required' => [],
])
```

## 訪問者向けの詳細

フォームの無い、モデルをそのまま見せる画面では、`$input` を作らず、モデルとアクセサ（`$news->list_image_url` など）で表示します。
