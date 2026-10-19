<?php

namespace Tests\Feature;

use App\Enums\BulkMailStatus;
use App\Models\BulkMail;
use App\Services\BulkMailCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Neobit\Core\Operations\TemporaryDataCleaner;
use Neobit\Core\Upload\UploadFilePath;
use Tests\TestCase;

/**
 * 一斉メールの送信の記録の自動の削除（App\Services\BulkMailCleanup）と、終わったジョブのバッチの行の削除
 * （Neobit\Core\Operations\TemporaryDataCleaner::oldJobBatches()）。
 */
class BulkMailCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** ジョブのバッチの行を1件入れ、idを返す。終わってからの日数がnullなら、まだ送っている */
    private function batch(?int $finishedDaysAgo): string
    {
        $id = (string) Str::uuid();

        DB::table('job_batches')->insert([
            'id' => $id,
            'name' => '一斉メール',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'created_at' => now()->subDays(1000)->getTimestamp(),
            'finished_at' => $finishedDaysAgo === null ? null : now()->subDays($finishedDaysAgo)->getTimestamp(),
        ]);

        return $id;
    }

    /** 送信の記録を1件作る。送り終えてからの日数がnullなら、送信中 */
    private function bulkMail(?int $finishedDaysAgo, ?string $attach = null): BulkMail
    {
        $bulkMail = BulkMail::create([
            'subject' => 'お知らせ',
            'body' => '本文',
            'attach' => $attach,
            'csv_filename' => 'recipients.csv',
            'recipient_count' => 1,
            'status' => $finishedDaysAgo === null ? BulkMailStatus::Sending : BulkMailStatus::Finished,
            'batch_id' => $this->batch($finishedDaysAgo),
            'finished_at' => $finishedDaysAgo === null ? null : now()->subDays($finishedDaysAgo),
        ]);

        if ($attach !== null) {
            Storage::disk(UploadFilePath::PRIVATE_DISK)->put(UploadFilePath::directory(BulkMail::class, $bulkMail->id).'/'.$attach, 'file');
        }

        return $bulkMail;
    }

    public function test_records_are_kept_without_the_setting(): void
    {
        $this->bulkMail(finishedDaysAgo: 1000);

        $this->assertSame(0, BulkMailCleanup::deleteOld(null));
        $this->assertSame(1, BulkMail::count());
    }

    public function test_old_records_are_deleted_with_their_files_and_batches(): void
    {
        Storage::fake(UploadFilePath::PRIVATE_DISK);
        $old = $this->bulkMail(finishedDaysAgo: 31, attach: 'old.pdf');
        $new = $this->bulkMail(finishedDaysAgo: 29, attach: 'new.pdf');
        $sending = $this->bulkMail(finishedDaysAgo: null);

        $this->assertSame(1, BulkMailCleanup::deleteOld(30));

        // 古いものは記録も添付ファイルもバッチの行も消える
        $this->assertNull(BulkMail::find($old->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing(UploadFilePath::directory(BulkMail::class, $old->id).'/old.pdf');
        $this->assertFalse(DB::table('job_batches')->where('id', $old->batch_id)->exists());

        // 新しいものと、送信中のものは残る
        $this->assertNotNull(BulkMail::find($new->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists(UploadFilePath::directory(BulkMail::class, $new->id).'/new.pdf');
        $this->assertNotNull(BulkMail::find($sending->id));
    }

    public function test_days_are_read_from_the_tasks_in_config(): void
    {
        $this->assertNull(BulkMailCleanup::days());

        config(['cleanup.tasks.保存期間を過ぎた一斉メールの記録.days' => 365]);
        $this->assertSame(365, BulkMailCleanup::days());

        // tasksに書いていなければ、消さないのと同じ
        config(['cleanup.tasks' => []]);
        $this->assertNull(BulkMailCleanup::days());
    }

    public function test_finished_batches_are_deleted_after_the_failed_job_days(): void
    {
        config(['cleanup.failed_job_days' => 7]);
        $old = $this->batch(finishedDaysAgo: 8);
        $new = $this->batch(finishedDaysAgo: 6);
        $sending = $this->batch(finishedDaysAgo: null);

        $this->assertSame(1, TemporaryDataCleaner::all()['保存期間を過ぎた、終わったジョブのバッチ']);

        // 送り直せる日数を過ぎた、終わったバッチだけが消える
        $this->assertFalse(DB::table('job_batches')->where('id', $old)->exists());
        $this->assertTrue(DB::table('job_batches')->where('id', $new)->exists());
        $this->assertTrue(DB::table('job_batches')->where('id', $sending)->exists());
    }
}
