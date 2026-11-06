# 新しいコーナーを作る手順

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

管理画面に「一覧・検索・登録・詳細・編集・削除」のあるコーナーを作る場合の手順です。一番近い実例（多くはニュースか会員）を写しながら進めます。

1. **テーブル**：マイグレーションを作る。業務で参照・削除するテーブルは `t_` を付ける（[table-model.md](table-model.md)）。
2. **モデル**：`$table`・`$fillable`・`$casts`、リレーション、データ項目の仕様の定数（画像の横幅など）、表示用のアクセサ（ファイルの URL など）。
3. **コントローラー**：[overview.md](overview.md)の「コントローラーの並び順」で書く。
   - `use SearchableList;` と一覧の定数、`searchRules()`・`applyCustomSearch()`（[list.md](list.md)）
   - `use FormFlow;` と `rules()`・`formValues()`・`saveValues()`（[form.md](form.md)）
   - アップロードがあれば `use AjaxFileUpload;` と `UPLOAD_FILES`（[upload.md](upload.md)）
   - 入口：`index`・`create`・`confirmForStore`・`backToCreate`・`store`・`show`・`edit`・`confirmForUpdate`・`backToEdit`・`update`・`destroy`
4. **ルート**：`routes/admin.php` の、ログイン中のスタッフのグループ（`auth:admin`・`auth.session` の内側）に足す。`/xxx/csv` のような固定の URL は、`/xxx/{id}` より前に書く。
5. **画面**：`resources/views/admin/<コーナー>/` に `index`・`_fields`・`create`・`edit`・`confirm`・`show`。
6. **入口のリンク**：管理画面の左のメニュー（`admin/_menu.blade.php`）に項目を足す。
7. **権限**：管理者だけの機能なら `acl.manager`、1件ごとに判断が要るなら Policy（[authorization.md](authorization.md)）。
8. **確かめる**：一覧の検索と並び順、詳細から「一覧へ戻る」で検索条件とページが戻ること、登録・編集の確認画面と「戻る」、必須マーク、削除。
