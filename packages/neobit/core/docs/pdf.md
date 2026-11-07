# PDF 出力（PdfDownload）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Pdf/PdfDownload.php`（冒頭のコメント）・`resources/fonts/ipaex/`（IPAex フォントとライセンス）・`resources/views/pdf/`　**実例**：履歴書（`Admin\MemberController::resume()`・`MypageController::resume()`・`resources/views/pdf/resume.blade.php`）

PDF の見た目は、普通の画面と同じく Blade のテンプレート（HTML と CSS）で書き、mPDF（composer の `mpdf/mpdf`）で PDF にします。

## コントローラーに書くもの

```php
use PdfDownload;

public function resume(Member $member): Response
{
    return $this->downloadPdf(
        view: 'pdf.resume',                       // resources/views/pdf/resume.blade.php
        data: ['member' => $member],              // テンプレートに渡す変数
        name: '履歴書_'.$member->name,            // ファイル名「履歴書_名前_年月日_時分.pdf」
        images: [
            'photo' => [
                'path' => $member->photo_path,
                'aspect' => Member::PHOTO_ASPECT,
            ],
        ],
        paper: 'A4',                              // 用紙（mPDF の format）
        orientation: 'P',                         // P：縦、L：横
        inline: true,                             // true：ブラウザの中で開く、false：ダウンロード
    );
}
```

- `images` の `aspect`（`[横, 縦]`）を書くと、画像の真ん中をその比で切り抜いてから埋め込みます。mPDF は CSS の `object-fit` に対応していないので、決まった大きさの枠に写真をゆがめずに収めるためです。`path` が null か、ファイルが無いときは埋め込みません。
- ルート：画面のボタンやリンクは、今の画面が切り替わらないように `target="_blank"` で開きます。

    ```php
    // GET：会員の履歴書の PDF を返す
    Route::get('/members/{member}/resume', [AdminMemberController::class, 'resume'])->name('members.resume');
    ```

- 返す PDF には、ブラウザやプロキシに残させないヘッダー（`Cache-Control: private, no-store`）を付けています。

## テンプレート

- 埋め込める画像は `$images` に入っています。`<img>` の `src` に、そのまま書ける値です。`@if (isset($images['photo'])) <img src="{{ $images['photo'] }}" style="width: 30mm; height: 40mm;"> @endif` のように書きます。画像はファイルのパスや URL ではなく mPDF の「`var:名前`」で渡すので、非公開のファイルも埋め込めます。
- フォントは、同梱の IPAex ゴシック（`ipaexg`、既定）と IPAex 明朝（`ipaexm`）を `font-family` で指定します。使った文字だけが PDF に埋め込まれるので、どの環境でも同じ見た目になります。
- **フォントは、アプリの `resources/fonts/ipaex/` に置きます**。フレームワークには入っていません。PDF を使わないサイトに、大きなファイル（14MB）を持たせないためです。無いときは、PDF を作るときに、置き場所を書いた例外になります。
- 余白は `@page { margin: ... }`、大きさは mm で書きます。
- mPDF が解釈できる CSS はブラウザより少なく、flex や grid は使えません。枠や罫線は `<table>` で組みます。

## 定型紙に文字を載せる（downloadPdfOnSheet）

履歴書や申込書のような、決まった様式の PDF（定型紙）を下敷きにして、上に文字を載せた PDF を出します。**実例**：`Admin\MemberController::resumeOnSheet()`・`resources/views/pdf/resume_on_sheet.blade.php`（厚生労働省の履歴書様式例の A4・2ページ）。

```php
return $this->downloadPdfOnSheet(
    sheet: resource_path('pdf-sheets/resume_a4.pdf'),   // 定型紙。アプリの resources/pdf-sheets/ に置く
    sheetPaging: SheetPaging::Once,                     // 定型紙のページの使い方
    view: 'pdf.resume_on_sheet',                        // 上に載せる文字のテンプレート
    data: ['member' => $member],
    name: '履歴書_'.$member->name,
    images: [...],                                      // downloadPdf() と同じ
    inline: true,
);
```

- `paper`・`orientation` は渡しません。ページの大きさは定型紙の1ページ目に合わせ、余白は0にします。
- テンプレートでは、文字を `position: absolute` の `<div>` で置きます。`left`・`top` は、定型紙の左上からの mm です。画像も、位置を決めた `<div>` の中に `<img>` を入れます。`<img>` に直接 `position: absolute` を付けても効かず、ページの左上に元の大きさで出てしまいます。

    ```blade
    <div style="position: absolute; left: 34mm; top: 40mm; width: 115mm; font-size: 20pt;">{{ $member->name }}</div>
    ```

- **折り返しとはみ出し**：`width` を決めると、幅を超えた文字は次の行へ折り返します。`height` と一緒に `overflow: hidden` を書くと、はみ出した分を切ります。`overflow: auto` を書くと、欄に収まるよう文字を縮めます（長い住所や名前に向く）。
- **ページ**：`<pagebreak />` で次のページへ進みます。どのページに定型紙のどのページを敷くかは、`sheetPaging` で決めます。テンプレートのページ数が定型紙と同じか少なければ、どれも1ページ目から順に敷きます。違うのは、定型紙のページ数がテンプレートより少ないときです。

    | `sheetPaging` | 定型紙のページ数がテンプレートより少ないとき | 2ページの定型紙での例 |
    |---|---|---|
    | `SheetPaging::Once` | 例外にする。くり返しの要らない様式で、`<pagebreak />` の入れすぎに気付ける | 1・2 |
    | `SheetPaging::Sequential` | 最後のページをくり返し敷く。続きのページを何枚でも足す様式 | 1・2・2・2… |
    | `SheetPaging::Cycle` | 1ページ目から順にくり返し敷く。表・裏の様式を人数分まとめて出す | 1・2・1・2… |

    ほかの使い方が要るときは、`SheetPagingRule` を実装した列挙型をアプリに作って渡します（名前は `App` で始める）。`sheetPage($page, $sheetPageCount)` が、出来上がる PDF の `$page` ページ目に敷く、定型紙のページを返します。

    ```php
    enum AppSheetPaging implements SheetPagingRule
    {
        case CoverThenSecond;   // 1ページ目は表紙、2ページ目から先は定型紙の2ページ目

        public function sheetPage(int $page, int $sheetPageCount): int
        {
            return $page === 1 ? 1 : 2;
        }
    }
    ```

- **あふれ**：1ページ分の中身があふれて、mPDF が自動でページを足すと、足されたページには定型紙が敷かれません。文字は `position: absolute` で置くので、ふつうはあふれません。
- **表の行**：学歴や資格のように行の高さが決まった欄は、`@foreach` の中で `top` を行の高さずつずらします。見本の履歴書では、会員の経歴を学歴・職歴の欄に載せ、1ページ目の欄に入りきらない分を2ページ目の欄に続けています。
- **位置の決め方**：定型紙を印刷して測るか、罫線の位置を PDF から読み取って決めます。試しに出して、ずれを直します。
- **読めない PDF**：定型紙を読むのは、mPDF に入っている FPDI（無料版）です。古い作り方の PDF などで読めないときは、例外になります。そのときは、PDF の形式を変えてから置きます（「PDF 1.4 として保存し直す」など）。見本の定型紙（PDF 1.7）は、そのまま読めました。
- **使えない CSS**：flex や grid は使えません。枠の中で縦の真ん中にそろえる指定（`vertical-align`）も効かないので、`padding-top` で合わせます。

## 決まりごと

- mPDF の作業用のディレクトリは `storage/framework/mpdf`（Git の対象外）です。最初の1回はフォントを解析するので、少し時間がかかります。
- フォントを足すときは、`resources/fonts/` にファイルとライセンスを置き、`PdfDownload` の `PDF_FONTS` に足します。
