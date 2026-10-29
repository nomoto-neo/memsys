<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use App\Services\MailUnsubscribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Security\AccessRestriction;
use Tests\TestCase;

/**
 * アクセス制限（Neobit\Core\Security\AccessRestriction）。サイト（訪問者画面）と管理画面のそれぞれに、
 * 管理画面の「アクセス制限」でチェックを入れたときだけ掛かる。管理画面のIPアドレスは、サイトの制限でも通す。
 * サイトの制限（RestrictSiteAccess）はメンテナンス中の画面を、管理画面の制限（RestrictAdminAccess）は404を返す。
 */
class AccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    /** サイトのIPアドレス・管理画面のIPアドレス・そのほかのIPアドレス */
    private const OFFICE_IP = '203.0.113.10';

    private const ADMIN_IP = '192.0.2.50';

    private const OTHER_IP = '198.51.100.20';

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

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

    // ---- サイト（訪問者画面） ----

    public function test_nothing_is_restricted_unless_checked(): void
    {
        // .envにIPアドレスがあっても、画面でチェックを入れなければ、どちらも制限しない
        config(['app.site_allowed_ips' => [self::OFFICE_IP], 'app.admin_allowed_ips' => [self::ADMIN_IP]]);

        $this->fromIp(self::OTHER_IP)->get('/')->assertOk()->assertDontSee('メンテナンス中');
        $this->fromIp(self::OTHER_IP)->get('/app/admin/login')->assertOk();
    }

    public function test_site_shows_the_maintenance_page_to_other_addresses(): void
    {
        AccessRestriction::save(true, [self::OFFICE_IP], false, []);

        // ルートが無いURLでも、同じ画面を出す。ヘッダーも付く
        foreach (['/', '/login', '/no-such-page'] as $path) {
            $this->fromIp(self::OTHER_IP)->get($path)
                ->assertStatus(503)
                ->assertSee('ただいまメンテナンス中です')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        $this->fromIp(self::OFFICE_IP)->get('/')->assertOk()->assertDontSee('メンテナンス中');
    }

    public function test_site_restriction_does_not_touch_the_admin(): void
    {
        AccessRestriction::save(true, [self::OFFICE_IP], false, []);

        // サイトのIPアドレスに無くても、管理画面は開ける
        $this->fromIp(self::OTHER_IP)->get('/app/admin/login')->assertOk();
    }

    public function test_site_lets_in_env_addresses_and_ranges(): void
    {
        config(['app.site_allowed_ips' => ['203.0.113.0/24']]);
        AccessRestriction::save(true, [], true, [self::ADMIN_IP]);

        $this->fromIp(self::OFFICE_IP)->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/')->assertStatus(503);
        $this->fromIp(self::ADMIN_IP)->get('/app/admin/login')->assertOk();
    }

    public function test_admin_addresses_also_open_the_restricted_site(): void
    {
        // 管理画面に入れる人は、メンテナンス中のサイトも開ける。管理画面の制限をしていなくても、一覧に書いた人だけ
        config(['app.admin_allowed_ips' => ['192.0.2.60']]);
        AccessRestriction::save(true, [self::OFFICE_IP], false, [self::ADMIN_IP]);

        $this->fromIp(self::ADMIN_IP)->get('/')->assertOk();
        $this->fromIp('192.0.2.60')->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/')->assertStatus(503);
    }

    public function test_health_check_and_unsubscribe_stay_open(): void
    {
        AccessRestriction::save(true, [self::OFFICE_IP], false, []);

        $this->fromIp(self::OTHER_IP)->get('/up')->assertOk();
        $this->fromIp(self::OTHER_IP)->get(MailUnsubscribe::url('member@example.com'))->assertOk()->assertSee('配信を停止する');
        $this->fromIp(self::OTHER_IP)->post(MailUnsubscribe::url('member@example.com'))->assertOk();
    }

    // ---- 管理画面 ----

    public function test_admin_is_hidden_from_other_addresses(): void
    {
        config(['app.admin_allowed_ips' => [self::OFFICE_IP]]);
        AccessRestriction::save(false, [], true, []);

        // ログイン画面も、ログインの要る画面も、404にする。ログイン画面へは回さない
        $this->fromIp(self::OTHER_IP)->get('/app/admin/login')->assertNotFound();
        $this->fromIp(self::OTHER_IP)->get('/app/admin')->assertNotFound();
        $this->fromIp(self::OTHER_IP)->post('/app/admin/login', ['login_id' => 'x', 'password' => 'y'])->assertNotFound();

        // サイトは、どこからでも開ける
        $this->fromIp(self::OTHER_IP)->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/login')->assertOk();

        // .envのIPアドレスは、画面の一覧に書かなくても開ける
        $this->fromIp(self::OFFICE_IP)->get('/app/admin/login')->assertOk();
        $this->fromIp(self::OFFICE_IP)->get('/app/admin')->assertRedirect(route('admin.login'));
    }

    // ---- 画面 ----

    public function test_manager_saves_the_settings_from_the_screen(): void
    {
        // 設定ファイルで管理画面を開けるIPアドレスから操作する
        config(['app.admin_allowed_ips' => ['192.0.2.99']]);
        $manager = $this->manager();

        // 設定ファイルで常に許可しているIPアドレスを、欄の上に出す
        $this->fromIp('192.0.2.99')->actingAs($manager, 'admin')
            ->get(route('admin.access-restriction.index'))
            ->assertOk()
            ->assertSee('次のIPアドレスは、設定ファイルで常に許可されています。')
            ->assertSee('192.0.2.99');

        // 1行に1つでも、カンマ区切りでもよい
        $this->fromIp('192.0.2.99')->actingAs($manager, 'admin')
            ->patch(route('admin.access-restriction.update'), [
                'site_restricted' => '1',
                'site_ips' => self::OFFICE_IP."\n198.51.100.0/24, 203.0.113.11",
                'admin_restricted' => '1',
                'admin_ips' => self::ADMIN_IP,
            ])
            ->assertRedirect(route('admin.access-restriction.index'));

        $settings = AccessRestriction::settings();
        $this->assertTrue($settings['site_restricted']);
        $this->assertSame([self::OFFICE_IP, '198.51.100.0/24', '203.0.113.11'], $settings['site_ips']);
        $this->assertTrue($settings['admin_restricted']);
        $this->assertSame([self::ADMIN_IP], $settings['admin_ips']);
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::AccessRestrictionChange->value)->count());
    }

    public function test_settings_that_lock_out_the_current_address_are_not_saved(): void
    {
        $manager = $this->manager();

        // 管理画面の制限を掛けるのに、自分が管理画面のIPアドレスに無い
        $this->fromIp(self::OTHER_IP)->actingAs($manager, 'admin')
            ->patch(route('admin.access-restriction.update'), ['site_restricted' => '0', 'site_ips' => '', 'admin_restricted' => '1', 'admin_ips' => self::ADMIN_IP])
            ->assertSessionHasErrors('admin_ips');

        // IPアドレスの形になっていないもの
        $this->fromIp(self::OTHER_IP)->actingAs($manager, 'admin')
            ->patch(route('admin.access-restriction.update'), ['site_restricted' => '0', 'site_ips' => 'example.com', 'admin_restricted' => '0', 'admin_ips' => ''])
            ->assertSessionHasErrors('site_ips');

        $this->assertFalse(AccessRestriction::settings()['admin_restricted']);

        // サイトの制限は管理画面に影響しないので、自分がサイトのIPアドレスに無くても保存できる
        $this->fromIp(self::OTHER_IP)->actingAs($manager, 'admin')
            ->patch(route('admin.access-restriction.update'), ['site_restricted' => '1', 'site_ips' => self::OFFICE_IP, 'admin_restricted' => '0', 'admin_ips' => ''])
            ->assertSessionHasNoErrors();
        $this->assertTrue(AccessRestriction::settings()['site_restricted']);
    }

    // ---- 非常口のコマンド ----

    public function test_command_turns_both_restrictions_off(): void
    {
        AccessRestriction::save(true, [self::OFFICE_IP], true, [self::ADMIN_IP]);

        $this->artisan('app:access-denied-off')->assertSuccessful();

        // 両方の制限を外し、IPアドレスの一覧は、次に制限を掛けるときのために残す
        $settings = AccessRestriction::settings();
        $this->assertFalse($settings['site_restricted']);
        $this->assertFalse($settings['admin_restricted']);
        $this->assertSame([self::OFFICE_IP], $settings['site_ips']);
        $this->assertSame([self::ADMIN_IP], $settings['admin_ips']);

        $this->fromIp(self::OTHER_IP)->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/app/admin/login')->assertOk();
    }
}
