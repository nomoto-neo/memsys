<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Neobit\Core\Csv\CsvWriter;
use Neobit\Core\Enums\CsvEncoding;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Models\OperationLog;
use Tests\TestCase;

/**
 * CSVの共通の書き出し方（Neobit\Core\Csv\CsvWriter）。文字コード・BOM・見出し・数式の無害化と、
 * download()のダウンロードの記録。
 */
class CsvWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_writes_utf8_with_bom_and_headings(): void
    {
        $csv = CsvWriter::fetch(
            rows: [['山田 太郎', 'yamada@example.com'], ['佐藤', 'sato@example.jp']],
            encoding: CsvEncoding::Utf8Bom,
            headings: ['氏名', 'メールアドレス'],
            escapeFormula: true,
        );

        // 空白を含む値は「"」で囲まれ、改行は\r\n
        $this->assertSame("\xEF\xBB\xBF氏名,メールアドレス\r\n\"山田 太郎\",yamada@example.com\r\n佐藤,sato@example.jp\r\n", $csv);
    }

    public function test_fetch_writes_shift_jis_without_bom_or_headings(): void
    {
        $csv = CsvWriter::fetch(
            rows: [['髙橋①', 'ソ表']],
            encoding: CsvEncoding::Sjis,
            headings: null,
            escapeFormula: false,
        );

        // Windowsの文字もCP932で変換し、「ソ」「表」も壊さない
        $this->assertSame(mb_convert_encoding("髙橋①,ソ表\r\n", 'SJIS-win', 'UTF-8'), $csv);
    }

    public function test_formula_is_escaped_but_numbers_and_headings_are_kept(): void
    {
        $csv = CsvWriter::fetch(
            rows: [['=SUM(A1)', '+81', '-100', '@x', '3.5']],
            encoding: CsvEncoding::Utf8Bom,
            headings: ['=見出し'],
            escapeFormula: true,
        );

        // 数値の「-100」「3.5」はそのまま。見出しはこちらで決めた文字列なので、無害化しない
        $this->assertSame("\xEF\xBB\xBF=見出し\r\n'=SUM(A1),+81,-100,'@x,3.5\r\n", $csv);
    }

    public function test_rows_can_be_a_generator(): void
    {
        $rows = (function () {
            yield ['a'];
            yield ['b'];
        })();

        $this->assertSame("\xEF\xBB\xBFa\r\nb\r\n", CsvWriter::fetch(rows: $rows, encoding: CsvEncoding::Utf8Bom, headings: null, escapeFormula: true));
    }

    public function test_download_records_the_download_and_its_row_count(): void
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $this->actingAs($staff, 'admin');

        $response = TestResponse::fromBaseResponse(CsvWriter::download(
            name: '見本',
            rows: [['1'], ['2'], ['3']],
            encoding: CsvEncoding::Utf8Bom,
            headings: ['番号'],
            escapeFormula: true,
            conditions: ['kind' => 'sample'],
        ));

        $this->assertSame("\xEF\xBB\xBF番号\r\n1\r\n2\r\n3\r\n", $response->streamedContent());
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('_'.now()->format('Ymd_Hi').'.csv', (string) $response->headers->get('Content-Disposition'));

        // 記録は書き出す前に作り、件数は書き終えてから入れる。見出しの行は数えない
        $log = CsvDownloadLog::sole();
        $this->assertSame('見本', $log->name);
        $this->assertSame($staff->id, $log->operator_id);
        $this->assertSame(3, $log->row_count);
        $this->assertSame(['kind' => 'sample'], $log->conditions);
        $this->assertTrue(OperationLog::where('action', OperationLogAction::CsvDownload)->exists());
    }
}
