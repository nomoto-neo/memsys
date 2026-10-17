<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use setasign\Fpdi\PdfParser\PdfParser;
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
}
