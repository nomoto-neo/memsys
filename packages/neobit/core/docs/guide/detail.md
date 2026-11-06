# 詳細表示

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：`Admin\MemberController::show()`・`Admin\NewsController::show()`

詳細画面も、入力欄と同じ `_fields.blade.php` を読み取り専用で表示します。値は `inputPageValues($record)` で作ります（モデルの今の値。アップロード項目も含む）。

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

```blade
@include('admin.news._fields', [
    'input' => $input, 'model' => $news, 'categories' => $categories,
    'readonly' => ' readonly', 'disabled' => ' disabled', 'required' => [],
])
```

訪問者向けの詳細（フォームの無い「モデルをそのまま見せる」画面）では `$input` を作らず、モデルとアクセサ（`$news->list_image_url` など）で表示します。
