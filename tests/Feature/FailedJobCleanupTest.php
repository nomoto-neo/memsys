<?php

namespace Tests\Feature;

use App\Enums\OperationLogAction;
use App\Models\OperationLog;
use App\Support\TemporaryDataCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 送れなかったキューのジョブの自動の削除（App\Support\TemporaryDataCleaner::oldFailedJobs()）。
 * failed_jobsの行のうち、config/cleanup.phpのfailed_job_daysの日数を過ぎたものを消す。
 */
class FailedJobCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function failedJob(int $daysAgo): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now()->subDays($daysAgo),
        ]);

        return $uuid;
    }

    public function test_old_failed_jobs_are_deleted(): void
    {
        config(['cleanup.failed_job_days' => 7]);

        $old = $this->failedJob(daysAgo: 8);
        $new = $this->failedJob(daysAgo: 6);

        $this->assertSame(1, TemporaryDataCleaner::oldFailedJobs());

        // 古いものは消え、送り直せる期間の中のものは残る
        $this->assertFalse(DB::table('failed_jobs')->where('uuid', $old)->exists());
        $this->assertTrue(DB::table('failed_jobs')->where('uuid', $new)->exists());
    }

    public function test_nothing_is_deleted_without_the_days_in_config(): void
    {
        // 設定のキャッシュが古くて、日数の項目がまだ無いとき。全部を消してしまわない
        config(['cleanup.failed_job_days' => null, 'cleanup.operation_log_days' => null]);

        $this->failedJob(daysAgo: 100);
        $log = new OperationLog(['action' => OperationLogAction::Login]);
        $log->created_at = now()->subDays(1000);
        $log->save();

        $this->assertSame(0, TemporaryDataCleaner::oldFailedJobs());
        $this->assertSame(0, TemporaryDataCleaner::oldOperationLogs());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame(1, OperationLog::count());
    }

    public function test_failed_jobs_outside_the_database_are_left(): void
    {
        config(['queue.failed.driver' => 'null']);
        $this->failedJob(daysAgo: 100);

        $this->assertSame(0, TemporaryDataCleaner::oldFailedJobs());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_cleanup_command_includes_failed_jobs(): void
    {
        $this->failedJob(daysAgo: 100);

        $this->assertSame(1, TemporaryDataCleaner::all()['保存期間を過ぎた、送れなかったジョブ']);
    }
}
