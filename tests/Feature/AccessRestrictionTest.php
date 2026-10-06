<?php

namespace Tests\Feature;

use App\Support\MailUnsubscribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 接続元のIPアドレスの制限。.envに書いたときだけ働く。
 * サイト全体の制限（App\Http\Middleware\RestrictSiteAccess）はメンテナンス中の画面を、
 * 管理画面の制限（App\Http\Middleware\RestrictAdminAccess）は404を返す。
 */
class AccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    // 入ってよいIPアドレスと、そのほかのIPアドレス
    private const OFFICE_IP = '203.0.113.10';

    private const OTHER_IP = '198.51.100.20';

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    // ---- サイト全体 ----

    public function test_site_is_open_to_everyone_without_the_setting(): void
    {
        $this->fromIp(self::OTHER_IP)->get('/')->assertOk()->assertDontSee('メンテナンス中');
    }

    public function test_site_shows_the_maintenance_page_to_other_addresses(): void
    {
        config(['app.site_allowed_ips' => [self::OFFICE_IP]]);

        // ルートが無いURLでも、同じ画面を出す。ヘッダーも付く
        foreach (['/', '/login', '/admin/login', '/no-such-page'] as $path) {
            $this->fromIp(self::OTHER_IP)->get($path)
                ->assertStatus(503)
                ->assertSee('ただいまメンテナンス中です')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        $this->fromIp(self::OFFICE_IP)->get('/')->assertOk()->assertDontSee('メンテナンス中');
    }

    public function test_site_setting_accepts_a_range(): void
    {
        config(['app.site_allowed_ips' => ['192.0.2.1', '203.0.113.0/24']]);

        $this->fromIp(self::OFFICE_IP)->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/')->assertStatus(503);
    }

    public function test_health_check_and_unsubscribe_stay_open(): void
    {
        config(['app.site_allowed_ips' => [self::OFFICE_IP]]);

        $this->fromIp(self::OTHER_IP)->get('/up')->assertOk();
        $this->fromIp(self::OTHER_IP)->get(MailUnsubscribe::url('member@example.com'))->assertOk()->assertSee('配信を停止する');
        $this->fromIp(self::OTHER_IP)->post(MailUnsubscribe::url('member@example.com'))->assertOk();
    }

    // ---- 管理画面 ----

    public function test_admin_is_hidden_from_other_addresses(): void
    {
        config(['app.admin_allowed_ips' => [self::OFFICE_IP]]);

        // ログイン画面も、ログインの要る画面も、404にする。ログイン画面へは回さない
        $this->fromIp(self::OTHER_IP)->get('/admin/login')->assertNotFound();
        $this->fromIp(self::OTHER_IP)->get('/admin')->assertNotFound();
        $this->fromIp(self::OTHER_IP)->post('/admin/login', ['login_id' => 'x', 'password' => 'y'])->assertNotFound();

        // 訪問者の側は、どこからでも開ける
        $this->fromIp(self::OTHER_IP)->get('/')->assertOk();
        $this->fromIp(self::OTHER_IP)->get('/login')->assertOk();

        $this->fromIp(self::OFFICE_IP)->get('/admin/login')->assertOk();
        $this->fromIp(self::OFFICE_IP)->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_is_open_to_everyone_without_the_setting(): void
    {
        $this->fromIp(self::OTHER_IP)->get('/admin/login')->assertOk();
    }
}
