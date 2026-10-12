<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Services\InquiryCleanup;
use App\Support\TemporaryDataCleaner;
use App\Support\UploadFilePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * お問い合わせの自動の削除（App\Services\InquiryCleanup）。一時データの後片付け
 * （App\Support\TemporaryDataCleaner）が、config/cleanup.phpのtasksに書いた日数を渡して呼ぶ。
 * 日数を決めたときだけ、過ぎた行と添付ファイルを消す。
 */
class InquiryCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function inquiry(int $daysAgo, ?string $attach = null): Inquiry
    {
        $inquiry = new Inquiry([
            'name' => '問合 太郎',
            'kana' => 'トイアワセ タロウ',
            'email' => 'inquiry@example.com',
            'body' => '内容',
            'attach_file' => $attach,
        ]);
        $inquiry->created_at = now()->subDays($daysAgo);
        $inquiry->save();

        if ($attach !== null) {
            Storage::disk(UploadFilePath::PRIVATE_DISK)->put(UploadFilePath::directory(Inquiry::class, $inquiry->id).'/'.$attach, 'file');
        }

        return $inquiry;
    }

    public function test_inquiries_are_kept_without_the_setting(): void
    {
        $this->inquiry(daysAgo: 1000);

        $this->assertSame(0, InquiryCleanup::deleteOld(null));
        $this->assertSame(1, Inquiry::count());
    }

    public function test_old_inquiries_are_deleted_with_their_files(): void
    {
        Storage::fake(UploadFilePath::PRIVATE_DISK);
        $old = $this->inquiry(daysAgo: 31, attach: 'old.pdf');
        $new = $this->inquiry(daysAgo: 29, attach: 'new.pdf');

        $this->assertSame(1, InquiryCleanup::deleteOld(30));

        // 古いものは行も添付ファイルも消え、新しいものは残る
        $this->assertNull(Inquiry::find($old->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing(UploadFilePath::directory(Inquiry::class, $old->id).'/old.pdf');
        $this->assertNotNull(Inquiry::find($new->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists(UploadFilePath::directory(Inquiry::class, $new->id).'/new.pdf');
    }

    public function test_cleanup_runs_the_tasks_in_config_with_their_days(): void
    {
        // 設定のとおりなら、お問い合わせの日数は.envのINQUIRY_KEEP_DAYS。テストでは空なので、消さない
        $this->inquiry(daysAgo: 1000);
        $this->assertSame(0, TemporaryDataCleaner::all()['保存期間を過ぎたお問い合わせ']);

        // 日数を書くと、その日数がメソッドに渡って、過ぎた行が消える
        config(['cleanup.tasks.保存期間を過ぎたお問い合わせ.days' => 30]);
        $this->assertSame(1, TemporaryDataCleaner::all()['保存期間を過ぎたお問い合わせ']);
        $this->assertSame(0, Inquiry::count());

        // tasksから外した片付けは、呼ばれない
        config(['cleanup.tasks' => []]);
        $this->assertArrayNotHasKey('保存期間を過ぎたお問い合わせ', TemporaryDataCleaner::all());
    }
}
