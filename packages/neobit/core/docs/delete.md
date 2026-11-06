# 削除

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：`Admin\NewsController::destroy()`（物理削除）・`Admin\StaffController`（論理削除と取り消し）

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

- ルートは `Route::delete('/news/{news}/delete', ...)`。一覧の削除ボタンは Bootstrap のモーダルで確認してから DELETE を送ります（実例：`admin/news/index.blade.php`）。
- アップロードファイルの実物は、トランザクションが確定した後に消えます（[rules.md](rules.md)の「トランザクション」）。
- **論理削除**：モデルに `SoftDeletes` を付けると、`deleteData()` は論理削除になります。削除済みを一覧に出すのは `applyCustomSearch()` の `withTrashed()`、削除済みを表示・取り消しするルートは `->withTrashed()` を付けます。取り消しは `$record->restore()`（実例：スタッフ）。削除済みのときにできない操作は Policy で false を返します（[authorization.md](authorization.md)）。
- 画面の削除ボタンで防いでいる条件（使用中のカテゴリーは削除できない、など）も、`destroy()` の中で必ずもう一度確かめます。
