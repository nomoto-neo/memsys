<?php

namespace Tests\Feature;

use App\Support\TemporaryDataCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 送れなかったキューのジョブの自動の削除（App\Support\TemporaryDataCleaner::oldFailedJobs()）。
 * failed_jobsの行のうち、FAILED_JOB_KEEP_DAYSを過ぎたものを消す。
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
        $old = $this->failedJob(daysAgo: TemporaryDataCleaner::FAILED_JOB_KEEP_DAYS + 1);
        $new = $this->failedJob(daysAgo: TemporaryDataCleaner::FAILED_JOB_KEEP_DAYS - 1);

        $this->assertSame(1, TemporaryDataCleaner::oldFailedJobs());

        // 古いものは消え、送り直せる期間の中のものは残る
        $this->assertFalse(DB::table('failed_jobs')->where('uuid', $old)->exists());
        $this->assertTrue(DB::table('failed_jobs')->where('uuid', $new)->exists());
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
