<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Operations\OperationRecorder;
use Tests\TestCase;

/**
 * 操作ログを書く処理（Neobit\Core\Operations\OperationRecorder）。
 * 残す日数を0にすると取らず、設定に無いときは取る。
 */
class OperationRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_when_days_are_set(): void
    {
        config(['cleanup.operation_log_days' => 365]);

        OperationRecorder::record(OperationLogAction::CsvDownload);

        $this->assertSame(1, OperationLog::count());
    }

    public function test_does_not_record_when_days_are_zero(): void
    {
        config(['cleanup.operation_log_days' => 0]);

        OperationRecorder::record(OperationLogAction::CsvDownload);

        $this->assertSame(0, OperationLog::count());
    }

    public function test_records_when_days_are_missing(): void
    {
        // 設定のキャッシュが古くて項目がまだ無いときも、記録は止めない
        config(['cleanup.operation_log_days' => null]);

        OperationRecorder::record(OperationLogAction::CsvDownload);

        $this->assertSame(1, OperationLog::count());
    }
}
