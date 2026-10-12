<?php

namespace Tests\Feature;

use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 操作ログの一覧の、補足での検索（Admin\OperationLogController）。
 * 補足はJSONの列で、日本語をそのままの表記で持つ。語を部分一致で探せる。
 */
class OperationLogSearchTest extends TestCase
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

    /** 配信停止の操作ログを1行作る。見分けられるよう、IPアドレスを変える */
    private function log(array $detail, string $ip): OperationLog
    {
        return OperationLog::create([
            'action' => OperationLogAction::MailUnsubscribe,
            'detail' => $detail,
            'ip' => $ip,
        ]);
    }

    /**
     * 補足の語で検索した後の、一覧の画面。検索の後は?page=1へ移る。
     * pageの無いURLは、メニューから開いたときの扱いで、検索条件が消える
     */
    private function search(string $detail): TestResponse
    {
        return $this->actingAs($this->manager(), 'admin')
            ->followingRedirects()
            ->post(route('admin.operation-logs.search'), ['detail' => $detail])
            ->assertOk();
    }

    public function test_detail_is_stored_without_escaping_japanese(): void
    {
        $log = $this->log(['result' => '会員が見つからない'], '192.0.2.1');

        $this->assertSame('{"result":"会員が見つからない"}', DB::table('operation_logs')->where('id', $log->id)->value('detail'));
        $this->assertSame(['result' => '会員が見つからない'], $log->fresh()->detail);
    }

    public function test_logs_are_searched_by_a_japanese_word_in_detail(): void
    {
        $this->log(['result' => '会員が見つからない', 'email' => 'guest@example.com'], '192.0.2.1');
        $this->log(['result' => '停止済み'], '192.0.2.2');

        $this->search('見つからない')
            ->assertSee('192.0.2.1')
            ->assertDontSee('192.0.2.2');
    }

    public function test_logs_are_searched_by_an_email_in_detail(): void
    {
        $this->log(['result' => '会員が見つからない', 'email' => 'guest@example.com'], '192.0.2.1');
        $this->log(['result' => '会員が見つからない', 'email' => 'other@example.com'], '192.0.2.2');

        $this->search('guest@example.com')
            ->assertSee('192.0.2.1')
            ->assertDontSee('192.0.2.2');
    }

    public function test_all_words_must_be_in_detail(): void
    {
        $this->log(['result' => '会員が見つからない', 'email' => 'guest@example.com'], '192.0.2.1');
        $this->log(['result' => '会員が見つからない', 'email' => 'other@example.com'], '192.0.2.2');
        $this->log(['result' => 'URLが正しくない', 'email' => 'guest@example.com'], '192.0.2.3');

        // 全角の空白でも区切れる
        $this->search('見つからない　guest')
            ->assertSee('192.0.2.1')
            ->assertDontSee('192.0.2.2')
            ->assertDontSee('192.0.2.3');
    }

    public function test_a_word_with_a_slash_is_found(): void
    {
        $this->log(['path' => 'admin/members'], '192.0.2.1');
        $this->log(['path' => 'admin-members'], '192.0.2.2');

        $this->search('admin/members')
            ->assertSee('192.0.2.1')
            ->assertDontSee('192.0.2.2');
    }

    public function test_login_failures_are_narrowed_by_the_kind_of_login(): void
    {
        // ログインの失敗は、対象の種類だけが残る。どの1件かは分からないので、idは空
        foreach (['member' => '192.0.2.1', 'staff' => '192.0.2.2'] as $type => $ip) {
            OperationLog::create([
                'action' => OperationLogAction::LoginFailed,
                'target_type' => $type,
                'detail' => ['login_id' => 'nobody'],
                'ip' => $ip,
            ]);
        }

        $response = $this->actingAs($this->manager(), 'admin')
            ->followingRedirects()
            ->post(route('admin.operation-logs.search'), ['target_type' => 'member'])
            ->assertOk();

        $response->assertSee('192.0.2.1')->assertDontSee('192.0.2.2');

        // 対象の欄には、種類だけが出る。idの無い「ID:」は出さない
        $response->assertDontSee('個人会員ID:');
    }

    public function test_migration_rewrites_escaped_japanese(): void
    {
        // キャストを変える前に書かれた行。日本語が\uの表記で入っている
        $id = DB::table('operation_logs')->insertGetId([
            'action' => OperationLogAction::MailUnsubscribe->value,
            'detail' => json_encode(['result' => '会員が見つからない']),
            'ip' => '192.0.2.1',
            'created_at' => now(),
        ]);
        $this->assertStringContainsString('\u4f1a', DB::table('operation_logs')->where('id', $id)->value('detail'));

        (require database_path('migrations/2026_10_12_100000_store_operation_log_detail_as_unicode.php'))->up();

        $this->assertSame('{"result":"会員が見つからない"}', DB::table('operation_logs')->where('id', $id)->value('detail'));
        $this->search('見つからない')->assertSee('192.0.2.1');
    }
}
