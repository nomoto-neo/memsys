<?php

namespace Neobit\Core\Pdf;

/**
 * 定型紙のページの使い方（SheetPagingRule）の、フレームワークが持つ3つ。
 * テンプレートのページ数が定型紙と同じか少なければ、どれも1ページ目から順に敷き、残った定型紙のページは使わない。
 * 違うのは、定型紙のページ数がテンプレートより少ないとき。
 */
enum SheetPaging implements SheetPagingRule
{
    /** 定型紙を1回だけ通す。定型紙のページ数がテンプレートより少なければ例外。例：2ページの定型紙なら 1・2 */
    case Once;

    /** 定型紙のページ数がテンプレートより少なければ、最後のページをくり返し敷く。例：1・2・2・2… */
    case Sequential;

    /** 定型紙のページ数がテンプレートより少なければ、1ページ目から順にくり返し敷く。例：1・2・1・2… */
    case Cycle;

    public function sheetPage(int $page, int $sheetPageCount): int
    {
        return match ($this) {
            // 定型紙に無いページを返して、downloadPdfOnSheet()に例外にさせる
            self::Once => $page,
            self::Sequential => min($page, $sheetPageCount),
            self::Cycle => ($page - 1) % $sheetPageCount + 1,
        };
    }
}
