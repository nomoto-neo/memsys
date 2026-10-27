<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Security\AdminRequestLimit;
use Tests\TestCase;

/**
 * ログイン後の管理画面の、回数の制限（Neobit\Core\Security\AdminRequestLimit）。
 * 1分に送れる回数を超えた回は処理せず、設定に書いた案内の文を、画面とJSONで返す。
 * 回数を0にすると制限を掛けず、設定に無いときは既定の回数にする。
 */
class AdminRequestLimitTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'テスト用の案内の文です。';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'neobit.admin_requests_per_minute' => 2,
            'neobit.admin_requests_exceeded_message' => self::MESSAGE,
        ]);

        $this->actingAs(Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]), 'admin');
    }

    public function test_requests_over_the_limit_get_the_message_from_config(): void
    {
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk();

        // 3回目は処理せず、案内の画面を返す
        $this->get(route('admin.dashboard'))
            ->assertStatus(429)
            ->assertSee(self::MESSAGE);

        // JavaScriptからの送信には、同じ文をJSONで返す
        $this->getJson(route('admin.dashboard'))
            ->assertStatus(429)
            ->assertExactJson(['message' => self::MESSAGE]);

        // 続けて超えても、操作ログに残すのは1回だけ
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Throttled)->count());
    }

    public function test_zero_means_no_limit(): void
    {
        config(['neobit.admin_requests_per_minute' => 0]);

        // 回数を数えないので、何回送っても処理する
        for ($i = 0; $i < 5; $i++) {
            $this->get(route('admin.dashboard'))->assertOk();
        }

        $this->assertSame(0, OperationLog::where('action', OperationLogAction::Throttled)->count());
    }

    public function test_missing_setting_uses_the_default_count(): void
    {
        config(['neobit.admin_requests_per_minute' => null]);

        $limit = AdminRequestLimit::limit(Request::create('/app/admin'));

        $this->assertSame(120, $limit->maxAttempts);
    }
}
