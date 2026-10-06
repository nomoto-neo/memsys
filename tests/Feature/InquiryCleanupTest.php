<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Support\TemporaryDataCleaner;
use App\Support\UploadFilePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * お問い合わせの自動の削除（App\Support\TemporaryDataCleaner::oldInquiries()）。
 * .envのINQUIRY_KEEP_DAYSに残す日数を書いたときだけ、過ぎた行と添付ファイルを消す。
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

        $this->assertSame(0, TemporaryDataCleaner::oldInquiries());
        $this->assertSame(1, Inquiry::count());
    }

    public function test_old_inquiries_are_deleted_with_their_files(): void
    {
        Storage::fake(UploadFilePath::PRIVATE_DISK);
        config(['app.inquiry_keep_days' => 30]);

        $old = $this->inquiry(daysAgo: 31, attach: 'old.pdf');
        $new = $this->inquiry(daysAgo: 29, attach: 'new.pdf');

        $this->assertSame(1, TemporaryDataCleaner::oldInquiries());

        // 古いものは行も添付ファイルも消え、新しいものは残る
        $this->assertNull(Inquiry::find($old->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing(UploadFilePath::directory(Inquiry::class, $old->id).'/old.pdf');
        $this->assertNotNull(Inquiry::find($new->id));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists(UploadFilePath::directory(Inquiry::class, $new->id).'/new.pdf');
    }
}
