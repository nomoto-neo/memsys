<?php

namespace Tests\Feature;

use App\Enums\BulkMailStatus;
use App\Enums\StaffAcl;
use App\Models\BulkMail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Testing\TestResponse;

/**
 * 一斉メールの送信の状況の画面。送信中は読み直しまでの秒数を数え、終わっていれば完了の文を出す。
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
            ->assertSee('<p class="text-danger small fw-bold">送信処理は完了しました（失敗 2件）。</p>', false)
            // 送れなかった分が消えるまでの日数も出す
            ->assertSee('送れなかった分は、7日たつと消え、送り直せなくなります。');
    }

    public function test_failed_days_are_hidden_when_failed_jobs_are_kept(): void
    {
        config(['cleanup.failed_job_days' => 0]);

        $this->show(['status' => BulkMailStatus::Finished, 'sent_count' => 10, 'failed_count' => 2, 'finished_at' => now()])
            ->assertDontSee('送れなかった分は');
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
}
