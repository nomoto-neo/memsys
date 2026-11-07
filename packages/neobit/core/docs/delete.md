# 削除

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Crud/FormFlow.php`（`deleteData()`）　**実例**：`Admin\NewsController::destroy()`（物理削除）・`Admin\StaffController`（論理削除と取り消し）

## コントローラーに書くもの

```php
public function destroy(News $news): RedirectResponse
{
    $this->deleteData($news);   // beforeDelete() → アップロードファイルの削除 → 本体の削除（トランザクション）

    return redirect()->route(self::INDEX_ROUTE, ['back'])->with('status', 'ニュース記事を削除しました。');
}

/** 削除の直前に、関連テーブルを片付ける */
private function beforeDelete(News $news): void
{
    $news->categories()->detach();
}
```

- 物理削除のとき、`deleteData()` が自動で消すもの：アップロードファイル、子テーブル（[form.md](form.md) の「子テーブル」）の行とそのファイル、記事ごとの SEO の欄の行。
- 自分で消すもの：多対多の中間テーブル（`detach()`）と、子テーブルとして定義していない関連テーブル。`beforeDelete()` に書きます。
- アップロードファイルの実物は、トランザクションが確定した後に消えます（[rules.md](rules.md)の「トランザクション」）。
- 画面の削除ボタンで防いでいる条件（使用中のカテゴリーは削除できない、など）も、`destroy()` の中で必ずもう一度確かめます。

## ルート

```php
// DELETE：削除して、一覧へ戻る
Route::delete('/news/{news}/delete', [AdminNewsController::class, 'destroy'])->name('news.destroy');
```

## 画面

一覧の削除ボタンは、Bootstrap のモーダルで確認してから DELETE を送ります（実例：`admin/news/index.blade.php`）。

## 論理削除と取り消し

モデルに `SoftDeletes` を付けると、`deleteData()` は論理削除になります。

- 論理削除のときは、子テーブル・SEO の欄・アップロードファイルを残します。削除を取り消したときに、元に戻すためです。
- `beforeDelete()` は論理削除でも呼ばれます。取り消したときに要るもの（多対多の中間テーブルなど）は、消さないようにします。

```php
// PATCH：削除を取り消して、詳細へ戻る
Route::patch('/staff/{staff}/restore', [AdminStaffController::class, 'restore'])
    ->withTrashed()
    ->middleware('can:restore,staff')
    ->name('staff.restore');
```

- 削除済みを一覧に出すのは、`applyCustomSearch()` の `withTrashed()` です。
- 削除済みを表示・取り消しするルートには、`->withTrashed()` を付けます。取り消しは `$record->restore()` です（実例：スタッフ）。
- 削除済みのときにできない操作は、Policy で false を返します（[authorization.md](authorization.md)）。
