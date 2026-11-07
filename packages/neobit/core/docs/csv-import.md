# CSV 取り込み（CsvImport）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Csv/CsvImport.php` ほか（冒頭のコメント）、設計の詳細は「CSV取り込みの設計」（`docs/csv-import-spec.md`）　**実例**：`Admin\MemberController`（更新だけ）・`Admin\NewsController`（アップロード項目と子テーブル（添付ファイル）あり。一覧用画像が必須なので、追加はできない）

## コントローラーに書くもの

項目の定義は、ダウンロードと同じ `csvColumns()` を使います。文字コードの判定は、取り込みの設定が `encoding: null` なら自動判別です。ダウンロードした CSV を直して、そのまま取り込めます。検証・保存は画面からの登録・更新（FormFlow）と同じ処理を通ります。

```php
use CsvImport;

private function csvImportSettings(): CsvImportSettings
{
    return new CsvImportSettings(
        query: Member::query(),              // id で探す範囲
        name: '会員一覧',                     // 画面の見出し・取り込み記録の名前
        labelColumn: 'お名前',                // エラー・警告の「何行目」に添える列（見出し、または何列目か）
        route: 'admin.members.csv-import',   // 取り込み画面のルート名（確認は .confirm、実行は .execute）
        mode: CsvImportMode::Save,           // Save：追加・更新／Process：保存せず処理だけ
        encoding: null,                      // null なら自動判定
        header: true,                        // false なら見出し無し（定義の順に全部の列）
        escapeFormula: true,                 // ダウンロードで付けた先頭の「'」を外す
        allowInsert: false,                  // id が空欄の行（追加）を認めるか
        maxRows: null,                       // 行数の上限
    );
}
```

## ルート

```php
// ルート（/members/{member} より前）
Route::get('/members/csv-import', [AdminMemberController::class, 'csvImport'])->name('members.csv-import');
Route::post('/members/csv-import/confirm', [AdminMemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
Route::post('/members/csv-import/execute', [AdminMemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
```

## 決まりごと

- 入口の3つ（取り込み画面・確認・実行）はトレイトにあり、画面は `admin/csv_import/` の共通のものを使います。取り込みが終わると取り込み画面に戻って結果を出します。「一覧へ戻る」は `INDEX_ROUTE` から作ります。
- 取り込めるのは `rules()` にある項目だけです。リレーションをたどる列は `'import' => '項目名'` で取り込み先を書きます（例：`['categories.*.id', $categories, 'import' => 'category_ids']`）。`@名前` の列は `'import' => [...]` と `csvCustomImport()` を書いたときだけ取り込みます。
- CSV にある列だけを更新します。変更の無い行は保存しません。
- セルの値は、画面からの入力と同じく、検証の前に全角と半角をそろえます。コントローラーの `RAW_INPUT_FIELDS` に書いた項目の列は、そろえません（[form.md](form.md)の「入力をそろえる」）。
- `labelColumn` を指定すると、確認画面のエラー・警告と実行を中止したときのメッセージで、「2行目（山田太郎）」のように行の見分けになる値を添えます（10文字を超えたら「...」で縮める。空欄なら添えない）。
- **子テーブル**：`csvColumns()` に組の横展開（[csv-download.md](csv-download.md)）を書けば、子テーブルも取り込めます。書かなければ、子テーブルには触りません。
    - 取り込みは、CSV に組の列がある行だけ、子テーブルを CSV の内容で入れ直します。CSV には子テーブルの行の id が無いので、id を保つ組でも、取り込むと行の id が変わります。組の列が無い CSV では、子テーブルには触りません。
    - 組の項目の列が全部そろっているときだけ取り込み、足りなければ「取り込めない列なので、無視しました」の警告にします。ファイルの表示名の列だけは無くてよく、無ければ今のファイルの表示名を使います。
    - 「添付ファイル:2」「保存ファイル名:2」のような番号の組が全部空欄なら詰めます。行の数の上限と、組の項目の検証ルールは、画面と同じに効きます。
    - ファイル名は、1つだけの欄と同じく、今のファイルか、その記事の保存先にあるものだけ使えます。追加の行には書けません。
    - 確認画面の変更の内容には、組を1つの項目として「a.pdf（案内.pdf）、b.pdf」のように出します。ファイルの無い組は、項目の値を「／」でつなぎます。
- **アップロードの欄の必須**：`UPLOAD_FILES` の `'required' => true` の欄は、取り込みでも確かめます。ファイル名の列があればファイル名が、無ければ今のファイルが要ります。追加の行にはファイル名を書けないので、必須の欄があるコーナーでは、CSV で追加できません。子テーブルの中の必須の欄は、組の列を取り込むときだけ、どの行にもファイルを求めます。
- **処理を組み込む**：`validateCsvRows()`（行をまたいだチェック）・`afterCsvImportRow()`（1行保存するたび）・`afterCsvImport()`（確定後の通知など）・`processCsvRows()`（処理だけのモード）。
- 取り込むたびに `csv_import_logs` に記録します。
- 自分の画面を持つ機能が CSV の読み込みと検証だけを使うときは、`CsvRead` を `use` します。項目の定義と行の検証ルールを引数で渡し、画面と流れは自分で作ります（設計 15）。
