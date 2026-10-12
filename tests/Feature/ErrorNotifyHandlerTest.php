<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use App\Support\ErrorNotifyHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Monolog\Level;
use Monolog\LogRecord;
use ReflectionProperty;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * エラーの通知のメール（App\Support\ErrorNotifyHandler）の「起きたところ」に載せる、ログイン中の人。
 * 設定にあるガードを全部見て、操作ログの「操作した人」と同じ呼び名で載せる。
 */
class ErrorNotifyHandlerTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テストはコマンドとして動くので、そのままでは「コマンド：」の行になる。画面からの扱いにする
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
    }

    /** エラーのログを1件渡して、送られた通知のメールの本文を返す */
    private function notifiedBody(): string
    {
        // 間隔を0にして、間引かない
        $handler = new ErrorNotifyHandler(Level::Error, 'dev@example.com', 0);
        $handler->handle(new LogRecord(now()->toDateTimeImmutable(), 'testing', Level::Error, 'テストのエラーです。'));

        return (string) $this->lastMail()->getTextBody();
    }

    public function test_logged_in_people_of_every_guard_are_listed(): void
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $member = Member::factory()->create();

        $this->actingAs($staff, 'admin')->actingAs($member, 'web');

        $body = $this->notifiedBody();

        $this->assertStringContainsString('スタッフ：'.$staff->id, $body);
        $this->assertStringContainsString('会員：'.$member->id, $body);
        $this->assertStringNotContainsString('企業の担当者：', $body);
    }

    public function test_nobody_is_listed_when_no_one_is_logged_in(): void
    {
        $body = $this->notifiedBody();

        $this->assertStringContainsString('IP：', $body);
        $this->assertStringNotContainsString('スタッフ：', $body);
        $this->assertStringNotContainsString('会員：', $body);
    }

    public function test_a_guard_added_in_config_is_listed_without_touching_the_handler(): void
    {
        // 設定にガードを足しただけで、そのガードでログインしている人も載る
        config([
            'auth.guards.partner' => ['driver' => 'session', 'provider' => 'partners'],
            'auth.providers.partners' => ['driver' => 'eloquent', 'model' => Member::class],
        ]);
        $member = Member::factory()->create();

        $this->actingAs($member, 'partner');

        // 呼び名は、そのガードのプロバイダーのモデルから決まる。個人会員のモデルを借りているので「会員」
        $this->assertStringContainsString('会員：'.$member->id, $this->notifiedBody());
    }
}
