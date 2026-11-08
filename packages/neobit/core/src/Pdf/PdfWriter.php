<?php

namespace Neobit\Core\Pdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * BladeのテンプレートからPDFを作り、PDFのデータ（文字列）を返す。PDFにするのはmPDF。
 * 保存先やメールへの添付は、呼んだ側が決める。ブラウザに返すときは、コントローラーでPdfDownloadを使う。
 * どんなPDFになるかが呼び出しの1か所で分かるよう、引数に既定の値は持たせず、名前付き引数で全部書く。
 *
 *     $pdf = PdfWriter::makePdf(
 *         view: 'pdf.resume',
 *         data: ['member' => $member],
 *         name: '履歴書_'.$member->name,
 *         images: ['photo' => ['path' => $member->photo_path, 'aspect' => Member::PHOTO_ASPECT]],
 *         paper: 'A4',
 *         orientation: 'P',
 *     );
 *     Storage::disk('local')->put("resumes/{$member->id}.pdf", $pdf);
 *
 * ■ makePdf()の引数
 * - view         テンプレートの名前。resources/views/pdf/に置く
 * - data         テンプレートに渡す変数
 * - name         PDFの名前。PDFのタイトル（文書のプロパティ）になる
 * - images       埋め込む画像。名前 => ['path' => サーバー上の場所, 'aspect' => [横, 縦]かnull]。
 *                aspectを書くと真ん中をその比で切り抜いてから埋め込む。mPDFはobject-fitを
 *                使えないので、決まった大きさの枠に写真をゆがめずに収めるため。
 *                pathがnullかファイルが無ければ埋め込まない
 * - paper        用紙の大きさ。'A4'・'A3'・'B5'など
 * - orientation  'P'が縦、'L'が横
 *
 * ■ テンプレートの書き方
 * - 埋め込める画像は$imagesに入っている。ファイルが無かった画像は入らない
 *
 *   @if (isset($images['photo'])) <img src="{{ $images['photo'] }}"> @endif
 *
 *   画像はファイルの場所やURLではなくmPDFの「var:名前」で渡すので、非公開のファイルも使える
 * - 余白は@page { margin: ... }で書く
 * - フォントは同梱のIPAexゴシックのipaexgとIPAex明朝のipaexmを、font-familyで指定する。
 *   指定しなければipaexgになる。使った文字だけをPDFに埋め込むので、どの環境でも同じ見た目になる
 * - mPDFが解釈できるCSSはブラウザより少なく、flexやgridは使えない。枠や罫線は<table>で組む
 *
 * ■ 定型紙に文字を載せる（makePdfOnSheet()）
 * 履歴書や申込書のような、決まった様式のPDF（定型紙）を下敷きにして、テンプレートの文字を上に載せる。
 * 引数は、makePdf()のpaper・orientationの代わりに、sheet（定型紙のPDFのサーバー上の場所）と、
 * sheetPaging（定型紙のページの使い方。SheetPaging）を渡す。
 * ページの大きさは定型紙の1ページ目に合わせ、余白は0にする。定型紙はアプリのresources/pdf-sheets/に置く。
 *
 *     $pdf = PdfWriter::makePdfOnSheet(
 *         sheet: resource_path('pdf-sheets/resume_a4.pdf'),
 *         sheetPaging: SheetPaging::Once,
 *         view: 'pdf.resume_on_sheet',
 *         data: ['member' => $member],
 *         name: '履歴書_'.$member->name,
 *         images: [],
 *     );
 *
 * - テンプレートでは、文字をposition: absoluteの<div>で置く。left・topは定型紙の左上からのmm。
 *   画像も、位置を決めた<div>の中に<img>を入れる。<img>に直接position: absoluteを付けても効かない
 * - widthを決めると、幅を超えた文字は折り返す。heightと一緒にoverflow: hiddenを書くとはみ出しを切り、
 *   overflow: autoを書くと、欄に収まるよう文字を縮める
 * - <pagebreak />で次のページへ進む。どのページに定型紙のどのページを敷くかは、sheetPagingで決める。
 *   定型紙のページ数がテンプレートより少なければ、Onceは例外、Sequentialは最後のページをくり返し、
 *   Cycleは1ページ目から順にくり返す。ほかの使い方は、アプリがSheetPagingRuleを実装した列挙型で足す
 * - 1ページの中身があふれて、mPDFが自動でページを足すと、足されたページには定型紙が敷かれない。
 *   文字はposition: absoluteで置くので、ふつうはあふれない
 * - 定型紙を読むのはmPDFに入っているFPDI。読めないPDFのときは、例外になる
 */
final class PdfWriter
{
    /**
     * 日本語フォントの場所。アプリのresources/の下。IPAフォントライセンスで配られているもの。
     * サイト全体で共通にするものなので、呼ぶ側からは変えない。
     */
    private const FONT_DIR = 'fonts/ipaex';

    /** font-familyの名前 => フォントファイル。 */
    private const FONTS = [
        'ipaexg' => 'ipaexg.ttf',
        'ipaexm' => 'ipaexm.ttf',
    ];

    private const DEFAULT_FONT = 'ipaexg';

    /**
     * mPDFに渡す、storage/の下の作業用のディレクトリ。mPDFはこの下にmpdf/を作り、フォントの解析結果などを置くので、
     * 置き場所はstorage/framework/mpdf/になる。既定のvendor/の下はサーバーでは書き込めないことがあるため。
     */
    private const TEMP_DIR = 'framework';

    /** 切り抜いた画像をJPEGにするときの画質。 */
    private const IMAGE_QUALITY = 90;

    /** テンプレートからPDFを作り、PDFのデータを返す。引数の意味はこのファイルの冒頭にある */
    public static function makePdf(
        string $view,
        array $data,
        string $name,
        array $images,
        string $paper,
        string $orientation,
    ): string {
        $mpdf = self::newMpdf([
            'format' => $paper,
            'orientation' => $orientation,
        ]);

        $mpdf->SetTitle($name);
        $mpdf->WriteHTML(view($view, $data + ['images' => self::imageSrcs($mpdf, $images)])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * 定型紙のPDFを下敷きにして、テンプレートの文字を上に載せたPDFのデータを返す。引数の意味はこのファイルの冒頭にある。
     * 用紙の大きさに合わせるので、用紙の大きさと向きは渡さない
     */
    public static function makePdfOnSheet(
        string $sheet,
        SheetPagingRule $sheetPaging,
        string $view,
        array $data,
        string $name,
        array $images,
    ): string {
        if (! is_file($sheet)) {
            throw new RuntimeException("定型紙のPDFがありません：{$sheet}");
        }

        // ページの大きさは、定型紙の1ページ目に合わせる。余白は0にして、位置を定型紙の左上から決められるようにする
        $size = (new PdfReader(new PdfParser(StreamReader::createByFile($sheet))))
            ->getPage(1)
            ->getWidthAndHeight(PageBoundaries::CROP_BOX);
        $mpdf = self::newMpdf([
            'format' => [$size[0] * 25.4 / 72, $size[1] * 25.4 / 72],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle($name);
        $sheetPageCount = $mpdf->setSourceFile($sheet);

        // テンプレートを、<pagebreak />の所でページごとに分ける。下敷きにする定型紙のページを、ページごとに
        // $sheetPagingで選ぶため。mPDFの機能では、定型紙の全ページをくり返すことができない
        $html = view($view, $data + ['images' => self::imageSrcs($mpdf, $images)])->render();
        $pages = preg_split('/<pagebreak\b[^>]*>/i', $html);

        // ページを足し、選んだ定型紙のページを敷いてから、そのページの文字を書く。
        // 同じ定型紙のページは、1回だけ読み込んで使い回す
        $templates = [];
        foreach ($pages as $index => $pageHtml) {
            $sheetPage = $sheetPaging->sheetPage($index + 1, $sheetPageCount);
            if ($sheetPage < 1 || $sheetPage > $sheetPageCount) {
                throw new RuntimeException("定型紙のページ数（{$sheetPageCount}）が、テンプレートのページ数（".count($pages).'）より少ないです。');
            }

            $mpdf->AddPage();
            $mpdf->useTemplate($templates[$sheetPage] ??= $mpdf->importPage($sheetPage));
            $mpdf->WriteHTML($pageHtml);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** 同梱の日本語フォントを使うmPDF。$configは、用紙の大きさや余白などのmPDFの設定 */
    private static function newMpdf(array $config): Mpdf
    {
        $tempDir = storage_path(self::TEMP_DIR);
        File::ensureDirectoryExists($tempDir);

        // フォントはアプリのresources/に置く。PDFを使わないアプリに大きなファイルを持たせないよう、
        // フレームワークには入れていない。無ければ、置き場所が分かるように止める
        $fontDir = resource_path(self::FONT_DIR);
        if (! is_dir($fontDir)) {
            throw new RuntimeException("PDFの日本語フォントがありません。{$fontDir} に、IPAexフォント（ipaexg.ttf・ipaexm.ttf）を置いてください。");
        }

        // mPDFの既定の設定に、同梱のフォントを足す
        $defaultFontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $defaultFontData = (new FontVariables())->getDefaults()['fontdata'];
        $fontData = [];
        foreach (self::FONTS as $family => $file) {
            $fontData[$family] = ['R' => $file];
        }

        return new Mpdf($config + [
            'mode' => 'utf-8',
            'tempDir' => $tempDir,
            'fontDir' => [...$defaultFontDirs, $fontDir],
            'fontdata' => $defaultFontData + $fontData,
            'default_font' => self::DEFAULT_FONT,
        ]);
    }

    /** 画像をmPDFに渡し、テンプレートに渡す「var:名前」の一覧を返す。ファイルが無かった画像は入らない */
    private static function imageSrcs(Mpdf $mpdf, array $images): array
    {
        $imageSrcs = [];
        foreach ($images as $imageName => $image) {
            $imageData = self::imageData($image['path'] ?? null, $image['aspect'] ?? null);

            if ($imageData !== null) {
                $mpdf->imageVars[$imageName] = $imageData;
                $imageSrcs[$imageName] = 'var:'.$imageName;
            }
        }

        return $imageSrcs;
    }

    /**
     * PDFに埋め込む画像のデータ。[横, 縦]の$aspectがあれば、真ん中をその比で切り抜いたJPEGにする。
     * ファイルが無いか読めないときはnullを返し、その画像は埋め込まない。
     */
    private static function imageData(?string $path, ?array $aspect): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($aspect === null) {
            return $contents;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            Log::warning('PdfWriter: 画像を読み込めませんでした。', ['path' => $path]);

            return null;
        }

        // 縦横の比を合わせるため、はみ出す方を切り落とす。横長なら左右、縦長なら上下
        [$aspectWidth, $aspectHeight] = $aspect;
        $width = imagesx($image);
        $height = imagesy($image);
        $cropWidth = min($width, (int) round($height * $aspectWidth / $aspectHeight));
        $cropHeight = min($height, (int) round($width * $aspectHeight / $aspectWidth));

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        // 透過のあるPNGは透明な部分を白にする
        imagefill($cropped, 0, 0, imagecolorallocate($cropped, 255, 255, 255));
        imagecopy($cropped, $image, 0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2), $cropWidth, $cropHeight);

        ob_start();
        imagejpeg($cropped, null, self::IMAGE_QUALITY);
        $jpeg = ob_get_clean();

        imagedestroy($image);
        imagedestroy($cropped);

        return $jpeg;
    }
}
