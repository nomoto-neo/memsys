<?php

namespace Neobit\Core\Pdf;

/**
 * 定型紙に文字を載せたPDF（PdfDownload::downloadPdfOnSheet()）で、出来上がるPDFの各ページに、
 * 定型紙のどのページを下敷きにするかを決める約束。フレームワークの決め方はSheetPagingにある。
 * ほかの決め方が要るときは、アプリがこれを実装した列挙型（AppSheetPagingなど）を作って渡す。
 */
interface SheetPagingRule
{
    /**
     * 出来上がるPDFの$pageページ目（1から）に敷く、定型紙のページ（1から）。
     * 定型紙に無いページ（1より小さいか、$sheetPageCountより大きい）を返すと、downloadPdfOnSheet()が例外にする。
     */
    public function sheetPage(int $page, int $sheetPageCount): int;
}
