<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * BladeのテンプレートからPDFを作って返す共通処理。PDFにするのはmPDF。
 *
 * PDFの見た目は普通の画面と同じくBladeのテンプレートで書く。コントローラーは入口の
 * メソッドでdownloadPdf()を呼ぶだけでよい。どんなPDFになるかが呼び出しの1か所で分かるよう、
 * 引数に既定の値は持たせず、名前付き引数で全部書く。
 *
 *     public function resume(Member $member): Response
 *     {
 *         return $this->downloadPdf(
 *             view: 'pdf.resume',
 *             data: ['member' => $member],
 *             name: '履歴書_'.$member->name,
 *             images: ['photo' => ['path' => $member->photo_path, 'aspect' => Member::PHOTO_ASPECT]],
 *             paper: 'A4',
 *             orientation: 'P',
 *             inline: true,
 *         );
 *     }
 *
 * ■ downloadPdf()の引数
 * - view         テンプレートの名前。resources/views/pdf/に置く
 * - data         テンプレートに渡す変数
 * - name         PDFの名前。ファイル名は「名前_年月日_時分.pdf」
 * - images       埋め込む画像。名前 => ['path' => サーバー上の場所, 'aspect' => [横, 縦]かnull]。
 *                aspectを書くと真ん中をその比で切り抜いてから埋め込む。mPDFはobject-fitを
 *                使えないので、決まった大きさの枠に写真をゆがめずに収めるため。
 *                pathがnullかファイルが無ければ埋め込まない
 * - paper        用紙の大きさ。'A4'・'A3'・'B5'など
 * - orientation  'P'が縦、'L'が横
 * - inline       trueならブラウザの中で開き、falseならダウンロードさせる
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
 */
trait PdfDownload
{
    // 同梱の日本語フォントの場所。IPAフォントライセンスで配られているもの。
    // サイト全体で共通にするものなので、コントローラーからは変えない。
    private const PDF_FONT_DIR = 'fonts/ipaex';

    // font-familyの名前 => フォントファイル。
    private const PDF_FONTS = [
        'ipaexg' => 'ipaexg.ttf',
        'ipaexm' => 'ipaexm.ttf',
    ];

    private const PDF_DEFAULT_FONT = 'ipaexg';

    // mPDFがフォントの解析結果などを置く、storage/の下の作業用のディレクトリ。
    // 既定のvendor/の下はサーバーでは書き込めないことがあるため。
    private const PDF_TEMP_DIR = 'framework/mpdf';

    // 切り抜いた画像をJPEGにするときの画質。
    private const PDF_IMAGE_QUALITY = 90;

    // テンプレートからPDFを作って返す。引数の意味はこのファイルの冒頭にある
    private function downloadPdf(
        string $view,
        array $data,
        string $name,
        array $images,
        string $paper,
        string $orientation,
        bool $inline,
    ): Response {
        $tempDir = storage_path(self::PDF_TEMP_DIR);
        File::ensureDirectoryExists($tempDir);

        // mPDFの既定の設定に、同梱のフォントを足す
        $defaultFontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $defaultFontData = (new FontVariables())->getDefaults()['fontdata'];
        $fontData = [];
        foreach (self::PDF_FONTS as $family => $file) {
            $fontData[$family] = ['R' => $file];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $paper,
            'orientation' => $orientation,
            'tempDir' => $tempDir,
            'fontDir' => [...$defaultFontDirs, resource_path(self::PDF_FONT_DIR)],
            'fontdata' => $defaultFontData + $fontData,
            'default_font' => self::PDF_DEFAULT_FONT,
        ]);

        // 画像をmPDFに渡し、テンプレートには「var:名前」を渡す
        $imageSrcs = [];
        foreach ($images as $imageName => $image) {
            $imageData = $this->pdfImageData($image['path'] ?? null, $image['aspect'] ?? null);

            if ($imageData !== null) {
                $mpdf->imageVars[$imageName] = $imageData;
                $imageSrcs[$imageName] = 'var:'.$imageName;
            }
        }

        $mpdf->SetTitle($name);
        $mpdf->WriteHTML(view($view, $data + ['images' => $imageSrcs])->render());

        $filename = $name.'_'.now()->format('Ymd_Hi').'.pdf';
        // 日本語のファイル名に対応していないブラウザのための、英数字だけの名前
        $fallback = 'document_'.now()->format('Ymd_Hi').'.pdf';

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                $fallback,
            ),
            // 個人情報を含むPDFを、ブラウザやプロキシに残させない
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // PDFに埋め込む画像のデータ。[横, 縦]の$aspectがあれば、真ん中をその比で切り抜いたJPEGにする。
    // ファイルが無いか読めないときはnullを返し、その画像は埋め込まない。
    private function pdfImageData(?string $path, ?array $aspect): ?string
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
            Log::warning('PdfDownload: 画像を読み込めませんでした。', ['path' => $path]);

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
        imagejpeg($cropped, null, self::PDF_IMAGE_QUALITY);
        $jpeg = ob_get_clean();

        imagedestroy($image);
        imagedestroy($cropped);

        return $jpeg;
    }
}
