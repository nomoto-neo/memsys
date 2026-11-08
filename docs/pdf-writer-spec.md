# 仕様メモ：PDF をファイルとして作る（pdf-writer）

- 実装プロンプト：`docs/prompt/pdf-writer.md`
- 実装完了日：2026-11-08

## A. 概要

**目的・解決する課題**

PDF を作る処理を、ブラウザへ返す応答から切り離した。新しいクラス `PdfWriter` が、PDF のデータ（文字列）を返す。作った PDF を、ファイルに保存したりメールに添付したりできるようにするため。

今までは `PdfDownload` の private のメソッドが応答（`Response`）しか返さなかった。そのため、コントローラーの外からは使えず、中身は `->getContent()` で応答から取り出すしかなかった。

あわせて、`TemplatedMail` の添付ファイルに、データを直接渡せるようにした。作った PDF を、ファイルに書かずにメールに付けられる。

見本のサイトの画面は足していない。利用ガイドの PDF 出力に、保存とメールへの添付のコードの例を書いた。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | 新規 `PdfWriter`。`PdfDownload` はそれを呼んで応答にするだけになった。`TemplatedMail` の添付に `data` を足した |
| 見本のサイト | 変わらない。履歴書の PDF のコントローラーは、書き換えずにそのまま動く |
| 案件のコード | `downloadPdf()`・`downloadPdfOnSheet()` の書き方は変わらない。`pdfImageData()` は呼べなくなった（使っている案件は無い） |

## B. 実装仕様

**PdfWriter**（`packages/neobit/core/src/Pdf/PdfWriter.php`）

- `final class`。static のメソッドで、名前付き引数で全部書く。`CsvWriter`（`download()`・`fetch()`）にそろえた
- `makePdf(view, data, name, images, paper, orientation): string`
- `makePdfOnSheet(sheet, sheetPaging, view, data, name, images): string`
- 引数は、`downloadPdf()`・`downloadPdfOnSheet()` から `inline` を除いたもの。`name` は PDF のタイトル（文書のプロパティ）に使う
- フォント・作業用のディレクトリ・画質の定数と、mPDF を作る処理・画像を埋め込む処理は、`PdfDownload` からここへ移した。定数の名前は `PDF_` の頭を外した（`FONTS`・`TEMP_DIR` など）。trait では使う側のクラスの定数と重ならないよう頭を付けていたが、クラスに閉じたので要らなくなったため

**PdfDownload**（`packages/neobit/core/src/Pdf/PdfDownload.php`）

- `downloadPdf()`・`downloadPdfOnSheet()` は、`PdfWriter` で作ったデータを `pdfResponse()` で応答にするだけ
- ファイル名（「{name}_年月日_時分.pdf」）・`Content-Disposition`・`Cache-Control: private, no-store` は今までのまま

**TemplatedMail の添付**（`packages/neobit/core/src/Mail/TemplatedMail.php`）

| 書き方 | 付け方 |
|---|---|
| `['path' => サーバー上の場所, 'name' => ファイル名]` | 今までどおり `Attachment::fromPath()` |
| `['data' => 中身, 'name' => ファイル名]` | `Attachment::fromData()`。種類は `name` の拡張子から Symfony の `MimeTypes` で決め、分からなければ `application/octet-stream` |

- `path` と `data` の両方がある、どちらも無い、`name` が無いときは、作るときに `LogicException`

**文書**

- 利用ガイド：`pdf.md`（何を返すかの1行、「ファイルに保存する・メールに付ける」の節）、`mail.md`（`data` の添付とキューの注意）、`names.md`（`PdfWriter` を足し、`pdfImageData` を外した）、`parts.md`・`guide.md`（`PdfWriter` の名前）
- `check_latest.sh`：`PdfDownload` の定数を確かめていた行を、`PdfWriter` と `TemplatedMail` の行に替えた
- `docs/laravel-dependency.md`・`docs/packaging-plan.md`：mPDF を使うクラスの名前

## C. 前提・依存

- mPDF（`mpdf/mpdf`）と、その中の FPDI。パッケージの追加はしていない
- 添付の種類の判定に使う `Symfony\Component\Mime\MimeTypes` は、Laravel のメールがもともと使っているもの
- フォント（`resources/fonts/ipaex/`）は、今までどおり案件の側に置く

## D. 設計判断とトレードオフ

- **trait のメソッドを足すのではなく、クラスに分けた**：trait のメソッドは private で、コントローラーの中でしか呼べない。サービスやコマンドからも PDF を作れるよう、static のクラスにした
- **クラスの名前は `PdfWriter`**：処理を受け持つクラスの「対象＋〜er」の形と、`CsvWriter` にそろえた
- **メソッドの名前は `makePdf`・`makePdfOnSheet`**：CSV の `fetch()` ではなく、trait の `downloadPdf`・`downloadPdfOnSheet` と対になる名前にした
- **`pdfImageData` を閉じた**：名前の一覧に載っていたが、案件のコードで使っているところが無かった。`PdfWriter` の private にした
- **データの添付は、キューに積む使い方を書かない**：中身の文字列をそのまま積むと、キューに入れる中身を JSON にできないことがあるため。キューに積むときは、ファイルに保存してパスで渡すと利用ガイドに書いた

## E. 課題・懸念点

- データの添付をキューに積んだときに止める仕掛けは無い。今は見本のサイトでメールをキューに積んでいないので、利用ガイドの注意で足りるとした
- 公開のディレクトリに保存した PDF の URL をメールに載せる形や、期限付きの署名付き URL で渡す形は、今の機能で作れることを確かめたが、利用ガイドにも見本にも入れていない

## F. セキュリティ・権限・データ整合性の注意

- `PdfWriter` は保存先を決めない。利用ガイドでは、個人情報を含む PDF をファイルに保存するときは、公開しない `local` のディスク（`storage/app/private`）を勧めている
- ブラウザに返すときの `Cache-Control: private, no-store` は、`PdfDownload` に残っている

## G. テスト状況

- 新規 `tests/Feature/PdfWriterTest.php`
  - `makePdf()` が、テンプレートのページ数と用紙（A4 の横）のとおりの PDF のデータを返す
  - `makePdfOnSheet()` が、定型紙の見本で、どのページにも定型紙を敷いた PDF のデータを返す
- 新規 `tests/Feature/TemplatedMailAttachmentTest.php`
  - `data` の添付が、名前と `application/pdf` で付く
  - `path` の添付が、今までどおり付く
  - `path` と `data` の両方、どちらも無い、`name` が無い添付は `LogicException`
- 今の `PdfOnSheetTest` と、履歴書の PDF のテストは、そのまま通る
- 全体：560件のうち559件が通り、1件はもともと飛ばすもの

## H. 今後の改善提案・TODO

- 作った PDF をモデルの非公開のアップロードの欄に入れる処理は、今は `UploadFilePath` の部品を組み合わせて案件の側で書く。何度も要るようなら、メソッドにすることを考える
