<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Neobit\Core\Pdf\PdfDownload;
use Neobit\Core\Pdf\SheetPaging;
use Neobit\Core\Pdf\SheetPagingRule;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

/**
 * 定型紙に文字を載せたPDF（Neobit\Core\Pdf\PdfDownload::downloadPdfOnSheet()）。
 * 見本は、会員の履歴書を厚生労働省の履歴書様式例（2ページ）に載せたもの（Admin\MemberController::resumeOnSheet()）。
 */
class PdfOnSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_is_written_on_the_sheet(): void
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $member = Member::create([
            'name' => '山田 太郎',
            'kana' => 'ヤマダ タロウ',
            'email' => 'taro@example.com',
            'birthdate' => '1990-05-12',
            'password' => Hash::make('member-password'),
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->get(route('admin.members.resumeOnSheet', $member))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // 定型紙と同じ2ページ・A4の縦。どちらのページにも、定型紙が下敷きになっている
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($response->getContent())));
        $this->assertSame(2, $reader->getPageCount());
        foreach ([1, 2] as $page) {
            [$width, $height] = $reader->getPage($page)->getWidthAndHeight();
            $this->assertEqualsWithDelta(210.0, $width * 25.4 / 72, 0.5);
            $this->assertEqualsWithDelta(297.0, $height * 25.4 / 72, 0.5);
            $this->assertStringContainsString(' Do', $reader->getPage($page)->getContentStream());
        }

        // 出したことは、操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::Pdf->value)->sole();
        $this->assertSame('履歴書（定型紙）', $log->detail['name']);

        // マイページからも、本人の分を同じ形で出せる
        $this->actingAs($member, 'web')
            ->get(route('mypage.resumeOnSheet'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sheet_paging_decides_which_sheet_page_is_laid(): void
    {
        // 2ページの定型紙に、4ページのテンプレートを載せる。ページごとに、下敷きにした定型紙のページを比べる
        $this->assertSame([1, 2, 2, 2], $this->laidSheetPages(SheetPaging::Sequential, 4));
        $this->assertSame([1, 2, 1, 2], $this->laidSheetPages(SheetPaging::Cycle, 4));
        $this->assertSame([1, 2], $this->laidSheetPages(SheetPaging::Once, 2));

        // アプリが決めた使い方も渡せる
        $this->assertSame([2, 2, 2], $this->laidSheetPages(TestSecondPageOnly::Always, 3));
    }

    public function test_once_rejects_more_pages_than_the_sheet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('定型紙のページ数（2）が、テンプレートのページ数（3）より少ないです。');

        $this->laidSheetPages(SheetPaging::Once, 3);
    }

    /**
     * $countページのテンプレートを見本の定型紙（2ページ）に載せ、出来上がったPDFの各ページに敷いた
     * 定型紙のページを返す。敷いた定型紙の中身を、定型紙の各ページの中身と比べて割り出す
     */
    private function laidSheetPages(SheetPagingRule $sheetPaging, int $count): array
    {
        View::addLocation(base_path('tests/Support/views'));

        // 定型紙の各ページの中身。中身 => ページ
        $sheetPath = resource_path('pdf-sheets/resume_a4.pdf');
        $sheet = new PdfReader(new PdfParser(StreamReader::createByFile($sheetPath)));
        $sheetPages = [];
        for ($page = 1; $page <= $sheet->getPageCount(); $page++) {
            $sheetPages[md5($sheet->getPage($page)->getContentStream())] = $page;
        }

        $parser = new PdfParser(StreamReader::createByString((new TestSheetPdf())->make($sheetPaging, $count)->getContent()));
        $reader = new PdfReader($parser);

        // 各ページで描いている定型紙（/TPL0 などの名前のXObject）の中身を取り出し、どのページかを引く
        $laid = [];
        for ($page = 1; $page <= $reader->getPageCount(); $page++) {
            preg_match('/\/(TPL\d+) Do/', $reader->getPage($page)->getContentStream(), $m);
            $resources = PdfType::resolve($reader->getPage($page)->getAttribute('Resources'), $parser);
            $xObjects = PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $parser);
            $stream = PdfType::resolve(PdfDictionary::get($xObjects, $m[1]), $parser);
            $laid[] = $sheetPages[md5($stream->getUnfilteredStream())] ?? 0;
        }

        return $laid;
    }
}

/** テスト用：PdfDownloadのdownloadPdfOnSheet()を呼ぶだけのクラス */
class TestSheetPdf
{
    use PdfDownload;

    public function make(SheetPagingRule $sheetPaging, int $count): Response
    {
        return $this->downloadPdfOnSheet(
            sheet: resource_path('pdf-sheets/resume_a4.pdf'),
            sheetPaging: $sheetPaging,
            view: 'sheet_pages',
            data: ['count' => $count],
            name: 'テスト',
            images: [],
            inline: true,
        );
    }
}

/** テスト用：アプリが決める定型紙のページの使い方。いつも2ページ目を敷く */
enum TestSecondPageOnly implements SheetPagingRule
{
    case Always;

    public function sheetPage(int $page, int $sheetPageCount): int
    {
        return 2;
    }
}
