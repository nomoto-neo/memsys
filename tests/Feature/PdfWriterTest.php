<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Neobit\Core\Pdf\PdfWriter;
use Neobit\Core\Pdf\SheetPaging;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

/**
 * PDFのデータを作る部品（Neobit\Core\Pdf\PdfWriter）。ブラウザに返さずに、保存やメールの添付に使うPDFを作れること。
 * テンプレートは、PdfOnSheetTestと同じテスト用の、ページ数を変えられるもの。
 */
class PdfWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(base_path('tests/Support/views'));
    }

    public function test_make_pdf_returns_the_pdf_data(): void
    {
        $pdf = PdfWriter::makePdf(
            view: 'sheet_pages',
            data: ['count' => 2],
            name: 'テスト',
            images: [],
            paper: 'A4',
            orientation: 'L',
        );

        // テンプレートのとおり2ページで、用紙はA4の横
        $this->assertStringStartsWith('%PDF', $pdf);
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
        $this->assertSame(2, $reader->getPageCount());
        [$width, $height] = $reader->getPage(1)->getWidthAndHeight();
        $this->assertEqualsWithDelta(297.0, $width * 25.4 / 72, 0.5);
        $this->assertEqualsWithDelta(210.0, $height * 25.4 / 72, 0.5);
    }

    public function test_make_pdf_on_sheet_returns_the_pdf_data(): void
    {
        $pdf = PdfWriter::makePdfOnSheet(
            sheet: resource_path('pdf-sheets/resume_a4.pdf'),
            sheetPaging: SheetPaging::Once,
            view: 'sheet_pages',
            data: ['count' => 2],
            name: 'テスト',
            images: [],
        );

        // 定型紙と同じ2ページで、どのページにも定型紙が下敷きになっている
        $this->assertStringStartsWith('%PDF', $pdf);
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
        $this->assertSame(2, $reader->getPageCount());
        foreach ([1, 2] as $page) {
            $this->assertMatchesRegularExpression('/\/TPL\d+ Do/', $reader->getPage($page)->getContentStream());
        }
    }
}
