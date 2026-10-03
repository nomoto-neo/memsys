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
 * BladeのテンプレートからPDFを作って返す共通処理（PDFにするのはmPDF）。
 *
 * PDFの見た目は、普通の画面と同じくBladeのテンプレート（HTMLとCSS）で書く。
 * コントローラーは、ルートから呼ばれる入口のメソッドでdownloadPdf()を呼ぶだけでよい。
 * どんなPDFができるかが呼び出しの1か所で分かるよう、省略できる引数も含めて全部書く。
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
 * - view:         テンプレートの名前（resources/views/pdf/ に置く）。
 * - data:         テンプレートに渡す変数。
 * - name:         PDFの名前。ファイル名は「名前_年月日_時分.pdf」（CSVダウンロードと同じ形）。
 * - images:       PDFに埋め込む画像。名前 => ['path' => 絶対パス, 'aspect' => [横, 縦] か null]。
 *                 aspectを書くと、画像の真ん中をその縦横の比で切り抜いてから埋め込む
 *                 （mPDFはCSSのobject-fitに対応していないので、決まった大きさの枠に
 *                 写真をゆがめずに収めるため）。pathがnullやファイルが無いときは埋め込まない。
 * - paper:        用紙の大きさ（'A4'・'A3'・'B5'など、mPDFのformat）。
 * - orientation:  'P'（縦）か'L'（横）。
 * - inline:       trueならブラウザの中で開く、falseならダウンロードさせる。
 *
 * ■ テンプレートの書き方
 * - 埋め込める画像は、テンプレートの$images（名前 => imgのsrcに書く値）に入っている。
 *
 *   @if (isset($images['photo'])) <img src="{{ $images['photo'] }}"> @endif のように使う
 *   （ファイルが無かった画像は$imagesに入らない）。画像はファイルのパスやURLではなく、
 *   mPDFの「var:名前」で渡すので、非公開の場所に置いたファイルも埋め込める。
 * - 余白は@page { margin: ... } で書く。
 * - フォントは、同梱のIPAexゴシック（ipaexg、既定）とIPAex明朝（ipaexm）を
 *   font-familyで指定する。使った文字だけがPDFに埋め込まれるので、どの環境でも同じ見た目になる。
 * - mPDFが解釈できるCSSは、ブラウザより少ない（flexやgridは使えない）。枠や罫線は<table>で組む。
 */
trait PdfDownload
{
    // 同梱の日本語フォント（resources/fonts/ipaex。IPAフォントライセンス）。
    // サイト全体で共通にするものなので、コントローラーからは変えない。
    private const PDF_FONT_DIR = 'fonts/ipaex';

    // font-familyの名前 => フォントファイル。
    private const PDF_FONTS = [
        'ipaexg' => 'ipaexg.ttf',
        'ipaexm' => 'ipaexm.ttf',
    ];

    private const PDF_DEFAULT_FONT = 'ipaexg';

    // mPDFがフォントの解析結果などを置く作業用のディレクトリ（storage/の下）。
    // 既定のvendor/の下は、サーバーでは書き込めないことがあるため。
    private const PDF_TEMP_DIR = 'framework/mpdf';

    // 切り抜いた画像をJPEGにするときの画質。
    private const PDF_IMAGE_QUALITY = 90;

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

    /**
     * PDFに埋め込む画像のデータ。$aspect（[横, 縦]）があれば、真ん中をその比で
     * 切り抜いたJPEGにする。ファイルが無い・読めないときはnull（その画像は埋め込まない）。
     */
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

        // 縦横の比を合わせるために、はみ出す方（横長なら左右、縦長なら上下）を切り落とす
        [$aspectWidth, $aspectHeight] = $aspect;
        $width = imagesx($image);
        $height = imagesy($image);
        $cropWidth = min($width, (int) round($height * $aspectWidth / $aspectHeight));
        $cropHeight = min($height, (int) round($width * $aspectHeight / $aspectWidth));

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        // 透過のあるPNGは、透明な部分を白にする
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
