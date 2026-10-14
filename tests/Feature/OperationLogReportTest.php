<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Mail\MailTemplateException;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Operations\OperationLogReport;
use Tests\TestCase;

/**
 * 操作ログの1日ぶんの報告（Neobit\Core\Operations\OperationLogReport）の、気になる点。
 * 目安の数は、設定（config/neobit.phpのoperation_report_thresholds）で決める。
 */
class OperationLogReportTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-10-11';

    /** その日の操作ログを、同じ時刻に$count行作る */
    private function logs(OperationLogAction $action, int $count, array $attributes = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            $log = new OperationLog(['action' => $action] + $attributes);
            $log->created_at = Carbon::parse(self::DAY.' 10:00:00');
            $log->save();
        }
    }

    private function notable(): string
    {
        return OperationLogReport::for(Carbon::parse(self::DAY))['notable'];
    }

    public function test_thresholds_come_from_config(): void
    {
        config(['neobit.operation_report_thresholds' => [
            'views_per_hour' => 3,
            'csv_downloads_per_day' => 2,
            'login_failures_per_day' => 4,
        ]]);

        $staff = ['operator_type' => 'staff', 'operator_id' => 1];
        $this->logs(OperationLogAction::View, 3, $staff);
        $this->logs(OperationLogAction::CsvDownload, 2, $staff);
        $this->logs(OperationLogAction::LoginFailed, 4);

        $notable = $this->notable();

        $this->assertStringContainsString('10時台に詳細を3件開いています（目安：1時間に3件）', $notable);
        $this->assertStringContainsString('CSVを2回ダウンロードしています（目安：1日に2回）', $notable);
        $this->assertStringContainsString('ログインの失敗が4件あります（目安：1日に4件）', $notable);
    }

    public function test_counts_under_the_thresholds_are_not_notable(): void
    {
        config(['neobit.operation_report_thresholds' => [
            'views_per_hour' => 4,
            'csv_downloads_per_day' => 3,
            'login_failures_per_day' => 5,
        ]]);

        $staff = ['operator_type' => 'staff', 'operator_id' => 1];
        $this->logs(OperationLogAction::View, 3, $staff);
        $this->logs(OperationLogAction::CsvDownload, 2, $staff);
        $this->logs(OperationLogAction::LoginFailed, 4);

        $this->assertSame('', $this->notable());
    }

    public function test_a_threshold_of_zero_is_not_checked(): void
    {
        config(['neobit.operation_report_thresholds' => [
            'views_per_hour' => 0,
            'csv_downloads_per_day' => 0,
            'login_failures_per_day' => 0,
        ]]);

        $staff = ['operator_type' => 'staff', 'operator_id' => 1];
        $this->logs(OperationLogAction::View, 100, $staff);
        $this->logs(OperationLogAction::CsvDownload, 100, $staff);
        $this->logs(OperationLogAction::LoginFailed, 100);

        $this->assertSame('', $this->notable());
    }

    public function test_broken_template_stops_the_command_instead_of_being_logged(): void
    {
        // 報告のテンプレートを、見出しの書き方を誤った形でアプリに置く
        $path = resource_path('mail-templates/operation_log_report.blade.php');
        $this->assertFileDoesNotExist($path);

        config(['neobit.operation_report_to' => 'admin@example.com']);

        try {
            file_put_contents($path, "FROM_NAME: サイト\nTO: {!! \$to !!}\nSUBJECT: 件名\n\n本文\n");

            // 送信の失敗はログに残して続けるが、テンプレートの誤りは、受け止めずに止める
            $this->expectException(MailTemplateException::class);

            $this->artisan('app:report-operation-logs', ['--always' => true])->run();
        } finally {
            @unlink($path);
        }
    }
}
