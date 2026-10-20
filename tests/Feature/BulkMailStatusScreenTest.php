<?php

namespace Tests\Feature;

use App\Enums\BulkMailStatus;
use App\Enums\StaffAcl;
use App\Jobs\SendBulkMail;
use App\Models\BulkMail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Models\OperationLog;
use Tests\TestCase;
use Illuminate\Testing\TestResponse;

/**
 * 一斉メールの送信の状況の画面。送信中は読み直しまでの秒数を数え、終わっていれば完了の文を出す。
 * 送れなかった宛先があれば、画面の下に並べ、全件をCSVで落とせるようにする。
 */
class BulkMailStatusScreenTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    private function show(array $bulkMail): TestResponse
    {
        $staff = $this->manager();

        $bulkMail = BulkMail::create($bulkMail + [
            'subject' => 'お知らせ',
            'body' => '本文',
            'csv_filename' => 'recipients.csv',
            'recipient_count' => 10,
        ]);

        return $this->actingAs($staff, 'admin')->get(route('admin.bulk-mails.show', $bulkMail))->assertOk();
    }

    public function test_sending_screen_counts_down_to_the_reload(): void
    {
        $this->show(['status' => BulkMailStatus::Sending])
            ->assertSee('送信中です。')
            ->assertSee('id="refresh-countdown" data-seconds="5"', false)
            ->assertDontSee('送信処理は完了しました');
    }

    public function test_finished_screen_says_so_in_green(): void
    {
        $this->show(['status' => BulkMailStatus::Finished, 'sent_count' => 10, 'failed_count' => 0, 'finished_at' => now()])
            ->assertSee('<p class="text-success small fw-bold">送信処理は完了しました。</p>', false)
            ->assertDontSee('refresh-countdown');
    }

    public function test_finished_screen_with_failures_is_red(): void
    {
        $this->show(['status' => BulkMailStatus::Finished, 'sent_count' => 10, 'failed_count' => 2, 'finished_at' => now()])
            ->assertSee('<p class="text-danger small fw-bold">送信処理は完了しました（失敗 2件）。</p>', false);
    }

    /**
     * 送り終えた送信の記録を、バッチと送れなかったジョブ付きで作る。
     * 宛先は「氏名 => メールアドレス」。
     */
    private function finishedWithFailures(array $recipients): BulkMail
    {
        $bulkMail = BulkMail::create([
            'subject' => 'お知らせ',
            'body' => '本文',
            'csv_filename' => 'recipients.csv',
            'recipient_count' => 10 + count($recipients),
            'status' => BulkMailStatus::Finished,
            'sent_count' => 10,
            'failed_count' => count($recipients),
            'finished_at' => now(),
        ]);

        $uuids = [];
        foreach ($recipients as $name => $email) {
            $uuid = (string) Str::uuid();
            $uuids[] = $uuid;

            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['data' => ['command' => serialize(new SendBulkMail($bulkMail->id, $name, $email))]]),
                'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: 550 5.1.1 User unknown in /var/www/vendor/Transport.php:120\nStack trace:\n#0 {main}",
                'failed_at' => now(),
            ]);
        }

        $batchId = (string) Str::uuid();
        DB::table('job_batches')->insert([
            'id' => $batchId,
            'name' => '一斉メール',
            'total_jobs' => 10,
            'pending_jobs' => count($uuids),
            'failed_jobs' => count($uuids),
            'failed_job_ids' => json_encode($uuids),
            'options' => serialize([]),
            'created_at' => now()->getTimestamp(),
            'finished_at' => now()->getTimestamp(),
        ]);
        $bulkMail->update(['batch_id' => $batchId]);

        return $bulkMail;
    }

    public function test_failed_recipients_are_listed_with_their_reasons(): void
    {
        $bulkMail = $this->finishedWithFailures(['山田 太郎' => 'yamada@example.com', '佐藤 花子' => 'sato@example.jp']);

        $this->actingAs($this->manager(), 'admin')->get(route('admin.bulk-mails.show', $bulkMail))
            ->assertOk()
            ->assertSee('送れなかった宛先（2件）')
            ->assertSee('yamada@example.com')
            ->assertSee('佐藤 花子')
            // 理由は、例外のクラス名とファイルの場所を除いた1文
            ->assertSee('550 5.1.1 User unknown')
            ->assertDontSee('TransportException')
            ->assertDontSee('/var/www/vendor')
            ->assertSee('送れなかった分は、7日たつと消え、宛先もこの一覧から消えます。')
            ->assertSee(route('admin.bulk-mails.failedCsv', $bulkMail));
    }

    public function test_failed_recipients_over_the_limit_are_left_to_the_csv(): void
    {
        $recipients = [];
        for ($i = 1; $i <= 101; $i++) {
            $recipients["宛先 {$i}"] = "user{$i}@example.com";
        }
        $bulkMail = $this->finishedWithFailures($recipients);

        $this->actingAs($this->manager(), 'admin')->get(route('admin.bulk-mails.show', $bulkMail))
            ->assertSee('送れなかった宛先（101件）')
            ->assertSee('user100@example.com')
            ->assertDontSee('user101@example.com')
            ->assertSee('ほか1件は、CSVダウンロードで見られます。');
    }

    public function test_failed_recipients_are_gone_after_the_cleanup(): void
    {
        // バッチが片付けで消えた後は、件数だけが記録に残る
        $bulkMail = $this->finishedWithFailures(['山田 太郎' => 'yamada@example.com']);
        DB::table('job_batches')->delete();
        DB::table('failed_jobs')->delete();

        $this->actingAs($this->manager(), 'admin')->get(route('admin.bulk-mails.show', $bulkMail))
            ->assertSee('送れなかった宛先（0件）')
            ->assertSee('残す日数を過ぎたので、宛先は消えています。')
            ->assertDontSee(route('admin.bulk-mails.failedCsv', $bulkMail));
    }

    public function test_failed_recipients_box_is_hidden_without_failures(): void
    {
        $this->show(['status' => BulkMailStatus::Finished, 'sent_count' => 10, 'failed_count' => 0, 'finished_at' => now()])
            ->assertDontSee('送れなかった宛先');
    }

    public function test_failed_days_are_hidden_when_failed_jobs_are_kept(): void
    {
        config(['cleanup.failed_job_days' => 0]);
        $bulkMail = $this->finishedWithFailures(['山田 太郎' => 'yamada@example.com']);

        $this->actingAs($this->manager(), 'admin')->get(route('admin.bulk-mails.show', $bulkMail))
            ->assertSee('yamada@example.com')
            ->assertDontSee('送れなかった分は');
    }

    public function test_failed_recipients_csv_has_all_rows_and_is_logged(): void
    {
        $bulkMail = $this->finishedWithFailures(['山田 太郎' => 'yamada@example.com', '=危険' => 'danger@example.com']);

        $csv = $this->actingAs($this->manager(), 'admin')->get(route('admin.bulk-mails.failedCsv', $bulkMail));
        $csv->assertOk();

        // 見出しの行は無く、氏名・メールアドレス・理由の3列。空白を含む値は「"」で囲まれる。数式に見える値は無害化する
        $this->assertSame(
            "\xEF\xBB\xBF\"山田 太郎\",yamada@example.com,\"550 5.1.1 User unknown\"\r\n'=危険,danger@example.com,\"550 5.1.1 User unknown\"\r\n",
            $csv->streamedContent(),
        );

        // ほかのCSVのダウンロードと同じく、記録と操作ログを残す
        $log = CsvDownloadLog::sole();
        $this->assertSame('一斉メールの送れなかった宛先', $log->name);
        $this->assertSame(2, $log->row_count);
        $this->assertSame(['bulk_mail_id' => $bulkMail->id], $log->conditions);
        $this->assertTrue(OperationLog::where('action', OperationLogAction::CsvDownload)->exists());
    }

    public function test_history_shows_how_long_records_are_kept(): void
    {
        $staff = $this->manager();

        // 日数を決めていなければ、消さないと出す
        $this->actingAs($staff, 'admin')->get(route('admin.bulk-mails.index'))
            ->assertSee('送信の記録は、消さずに残します。');

        // 日数を決めれば、その日数を出す
        config(['cleanup.tasks.保存期間を過ぎた一斉メールの記録.days' => 365]);
        $this->actingAs($staff, 'admin')->get(route('admin.bulk-mails.index'))
            ->assertSee('送信の記録は365日残し、過ぎたものは自動で消えます。');
    }

    public function test_history_returns_to_the_page_being_viewed(): void
    {
        $staff = $this->manager();

        // 2ページ目まである履歴。新しい順なので、2ページ目には最初に送った分が出る
        foreach (range(1, 21) as $number) {
            BulkMail::create([
                'subject' => "お知らせ{$number}号",
                'body' => '本文',
                'csv_filename' => 'recipients.csv',
                'recipient_count' => 1,
                'status' => BulkMailStatus::Finished,
            ]);
        }

        $this->actingAs($staff, 'admin')->get(route('admin.bulk-mails.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('お知らせ1号');

        // 詳細から?backで戻ると、見ていた2ページ目に戻る
        $this->actingAs($staff, 'admin')->get(route('admin.bulk-mails.index', ['back']))
            ->assertRedirect(route('admin.bulk-mails.index', ['page' => 2]));
    }
}
