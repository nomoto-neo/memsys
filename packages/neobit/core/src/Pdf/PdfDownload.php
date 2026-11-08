<?php

namespace Neobit\Core\Pdf;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * BladeのテンプレートからPDFを作り、ブラウザに返す共通処理。PDFを作るのはPdfWriterで、ここは応答にするだけ。
 *
 * コントローラーは入口のメソッドでdownloadPdf()を呼び、返った応答をそのままreturnする。
 * どんなPDFになるかが呼び出しの1か所で分かるよう、引数に既定の値は持たせず、名前付き引数で全部書く。
 * ファイルに保存したりメールに添付したりするときは、PdfWriterでPDFのデータを作る。
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
 * ■ 引数
 * - inline  trueならブラウザの中で開き、falseならダウンロードさせる
 * - ほかの引数は、PdfWriter::makePdf()と同じ。テンプレートの書き方もPdfWriterにある
 * - name    PDFのタイトルのほか、ファイル名「{name}_年月日_時分.pdf」にも使う
 *
 * ■ 定型紙に文字を載せる（downloadPdfOnSheet()）
 * 引数は、PdfWriter::makePdfOnSheet()の引数にinlineを足したもの。
 *
 *     return $this->downloadPdfOnSheet(
 *         sheet: resource_path('pdf-sheets/resume_a4.pdf'),
 *         sheetPaging: SheetPaging::Once,
 *         view: 'pdf.resume_on_sheet',
 *         data: ['member' => $member],
 *         name: '履歴書_'.$member->name,
 *         images: [],
 *         inline: true,
 *     );
 */
trait PdfDownload
{
    /** テンプレートからPDFを作って返す。引数の意味はこのファイルの冒頭にある */
    private function downloadPdf(
        string $view,
        array $data,
        string $name,
        array $images,
        string $paper,
        string $orientation,
        bool $inline,
    ): Response {
        $pdf = PdfWriter::makePdf(
            view: $view,
            data: $data,
            name: $name,
            images: $images,
            paper: $paper,
            orientation: $orientation,
        );

        return $this->pdfResponse($pdf, $name, $inline);
    }

    /** 定型紙のPDFを下敷きにして、テンプレートの文字を上に載せたPDFを返す。引数の意味はこのファイルの冒頭にある */
    private function downloadPdfOnSheet(
        string $sheet,
        SheetPagingRule $sheetPaging,
        string $view,
        array $data,
        string $name,
        array $images,
        bool $inline,
    ): Response {
        $pdf = PdfWriter::makePdfOnSheet(
            sheet: $sheet,
            sheetPaging: $sheetPaging,
            view: $view,
            data: $data,
            name: $name,
            images: $images,
        );

        return $this->pdfResponse($pdf, $name, $inline);
    }

    /** 作ったPDFを返す。ファイル名は「{name}_年月日_時分.pdf」 */
    private function pdfResponse(string $pdf, string $name, bool $inline): Response
    {
        $filename = $name.'_'.now()->format('Ymd_Hi').'.pdf';
        // 日本語のファイル名に対応していないブラウザのための、英数字だけの名前
        $fallback = 'document_'.now()->format('Ymd_Hi').'.pdf';

        return response($pdf, 200, [
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
}
