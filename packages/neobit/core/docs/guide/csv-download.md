# CSV ダウンロード（CsvDownload）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Csv/CsvDownload.php`・`CsvColumnSet.php`（項目の定義の書き方）・`CsvWriter.php`（共通の書き出し方）　**実例**：`Admin\MemberController`・`Admin\NewsController`・`Admin\BulkMailController::failedCsv()`

```php
use CsvDownload;

public function csv(): StreamedResponse
{
    return $this->downloadCsv(
        query: Member::query()->with('editorStaff'),   // 使うリレーションは with() で先読み
        name: '会員一覧',                                // ファイル名「会員一覧_年月日_時分.csv」・記録の名前
        encoding: CsvEncoding::Utf8Bom,                  // Utf8Bom か Sjis
        header: true,                                    // 1行目に見出し
        escapeFormula: true,                             // Excel の数式の無害化
    );
}

/** 見出し => 値の場所。書いた順に列が並ぶ */
private function csvColumns(): array
{
    $prefectures = code_table('prefectures');

    return [
        '会員ID' => 'id',
        'お名前' => 'name',
        '生年月日' => 'birthday|date:Y/m/d',              // 書式の変換（date・format・number）
        '都道府県' => ['prefecture', $prefectures],         // 一覧の表示名に置き換え
        '最終更新者' => 'editorStaff.name',                 // リレーション
        '年齢' => '@age',                                   // csvCustomColumn('age', $record)
        '更新日時' => 'updated_at|date:Y/m/d H:i:s',        // 取り込みの衝突チェックに使う
        '都道府県:*' => ['prefecture', $prefectures],       // 一覧の件数分の列に横展開（該当に1）
    ];
}
```

- 書き方の全体（複数の値の「、」区切り、連番の横展開 `'添付ファイル:*' => 'attach.*.file_origin'`、組の横展開 `'添付:*' => ['attach', 'group' => [...]]` など）は `CsvColumnSet.php` の冒頭にあります。
- `downloadCsv()` に渡したクエリのデータを出力します。`SearchableList` を使っていれば、一覧の検索条件と並び順が自動的に反映されます。
- ルート：`Route::get('/members/csv', ...)->name('members.csv')`（`/members/{member}` より前）。一覧画面に「CSVダウンロード」ボタン。
- ダウンロードのたびに `csv_download_logs` に記録します。

## モデルではないデータを書き出す（CsvWriter）

文字コード・BOM・数式の無害化・`fputcsv()` の書き方と、ダウンロードの記録は、CSV の共通の部品 `CsvWriter` が持っています。`downloadCsv()` も、モデルのクエリから行を作って、これを呼んでいます。モデルではないデータ（ジョブの中の宛先、集計の結果など）は、行の配列を作って直接呼びます。

```php
// ブラウザにダウンロードさせる。ダウンロードの記録と操作ログを残す
return CsvWriter::download(
    name: '一斉メールの送れなかった宛先',          // ファイル名「名前_年月日_時分.csv」・記録の名前
    rows: $rows,                                  // 1行 = 値の配列。ジェネレーターなら少しずつ書き出す
    encoding: CsvEncoding::Utf8Bom,               // Utf8Bom か Sjis
    headings: null,                               // 見出しの行。null なら出さない
    escapeFormula: true,                          // Excel の数式の無害化
    conditions: ['bulk_mail_id' => $bulkMail->id], // ダウンロードの記録に残す条件
);

// CSV の中身を文字列で受け取る。保存先やメールへの添付は、呼んだ側が決める
$csv = CsvWriter::fetch(rows: $rows, encoding: CsvEncoding::Utf8Bom, headings: ['氏名', 'メールアドレス'], escapeFormula: true);
Storage::disk('local')->put('exports/members.csv', $csv);
```

- `fetch()` は全件をメモリに組み立てるので、大きな CSV には向きません。人がダウンロードしたことにはならないので、ダウンロードの記録は残しません。個人情報を書き出すときは、記録を残すかを呼んだ側で決めます。
- 引数は、`downloadCsv()` と同じく既定の値を持たせず、名前付きで全部書きます。
