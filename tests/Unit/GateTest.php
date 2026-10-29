<?php

namespace Tests\Unit;

use Neobit\Core\Security\Gate;
use PHPUnit\Framework\TestCase;

/**
 * 入口の見張りの判断（Neobit\Core\Security\Gate）。Laravelに頼らないので、Laravelを起動せずに確かめる。
 * 訪問者のIPアドレスの求め方が$request->ip()と同じになることは、Laravelを使うTests\Feature\GateStateTestで確かめる。
 */
class GateTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /** 判断に使う状態。何も止めず、どちらも制限しない形に、$overridesを重ねる */
    private function state(array $overrides = []): array
    {
        return $overrides + [
            'blocks' => [],
            'site' => ['restricted' => false, 'ips' => []],
            'admin' => ['restricted' => false, 'ips' => []],
            'admin_path' => 'app/admin',
            'site_except' => ['up', 'mail/unsubscribe', 'app/admin', 'app/admin/*'],
            'trusted_proxies' => [],
        ];
    }

    public function test_addresses_and_ranges_match_by_their_prefix(): void
    {
        $this->assertTrue(Gate::inRange('203.0.113.5', ['203.0.113.5']));
        $this->assertTrue(Gate::inRange('203.0.113.200', ['203.0.113.0/24']));
        $this->assertTrue(Gate::inRange('10.20.30.40', ['10.16.0.0/12']));
        $this->assertFalse(Gate::inRange('10.32.0.1', ['10.16.0.0/12']));
        $this->assertTrue(Gate::inRange('198.51.100.7', ['0.0.0.0/0']));
        $this->assertTrue(Gate::inRange('2001:db8:1:2::10', ['2001:db8:1:2::/64']));
        $this->assertFalse(Gate::inRange('2001:db8:1:3::1', ['2001:db8:1:2::/64']));

        // IPv4とIPv6は互いに当たらない。正しくない形は、当たらないものとして読み飛ばす
        $this->assertFalse(Gate::inRange('203.0.113.5', ['::/0']));
        $this->assertFalse(Gate::inRange('203.0.113.5', ['*', 'not-an-ip', '203.0.113.0/33']));
        $this->assertFalse(Gate::inRange('', ['0.0.0.0/0']));
    }

    public function test_blocks_end_at_their_time(): void
    {
        $blocks = [['203.0.113.5', self::NOW + 60], ['198.51.100.0/24', null]];

        $this->assertTrue(Gate::isBlocked($blocks, '203.0.113.5', self::NOW));
        $this->assertFalse(Gate::isBlocked($blocks, '203.0.113.5', self::NOW + 60));
        $this->assertTrue(Gate::isBlocked($blocks, '198.51.100.99', self::NOW + 999999));
        $this->assertFalse(Gate::isBlocked($blocks, '192.0.2.1', self::NOW));
    }

    public function test_blocked_address_gets_not_found_everywhere(): void
    {
        $state = $this->state(['blocks' => [['203.0.113.5', null]]]);

        $this->assertSame(Gate::NOT_FOUND, Gate::decide($state, '203.0.113.5', '/', self::NOW));
        $this->assertSame(Gate::NOT_FOUND, Gate::decide($state, '203.0.113.5', 'up', self::NOW));
        $this->assertSame(Gate::PASS, Gate::decide($state, '198.51.100.7', '/', self::NOW));
    }

    public function test_site_restriction_shows_maintenance_except_on_open_paths(): void
    {
        $state = $this->state(['site' => ['restricted' => true, 'ips' => ['203.0.113.10']]]);

        $this->assertSame(Gate::MAINTENANCE, Gate::decide($state, '198.51.100.7', '/', self::NOW));
        $this->assertSame(Gate::MAINTENANCE, Gate::decide($state, '198.51.100.7', 'column/1', self::NOW));
        $this->assertSame(Gate::PASS, Gate::decide($state, '203.0.113.10', '/', self::NOW));

        // 制限を掛けないURLと、管理画面の下は通す
        $this->assertSame(Gate::PASS, Gate::decide($state, '198.51.100.7', 'up', self::NOW));
        $this->assertSame(Gate::PASS, Gate::decide($state, '198.51.100.7', 'app/admin/login', self::NOW));
    }

    public function test_admin_restriction_hides_only_the_admin(): void
    {
        $state = $this->state(['admin' => ['restricted' => true, 'ips' => ['192.0.2.50']]]);

        $this->assertSame(Gate::NOT_FOUND, Gate::decide($state, '198.51.100.7', 'app/admin', self::NOW));
        $this->assertSame(Gate::NOT_FOUND, Gate::decide($state, '198.51.100.7', 'app/admin/login', self::NOW));
        $this->assertSame(Gate::PASS, Gate::decide($state, '192.0.2.50', 'app/admin/login', self::NOW));

        // 頭が同じだけの別のURLと、サイトの側は、管理画面の制限を受けない
        $this->assertSame(Gate::PASS, Gate::decide($state, '198.51.100.7', 'app/administrator', self::NOW));
        $this->assertSame(Gate::PASS, Gate::decide($state, '198.51.100.7', '/', self::NOW));
    }

    public function test_paths_are_read_like_laravel(): void
    {
        $this->assertSame('/', Gate::path('/'));
        $this->assertSame('/', Gate::path('/?p=1'));
        $this->assertSame('app/admin/login', Gate::path('/app/admin/login/?x=1'));
        $this->assertSame('お知らせ', Gate::path('/%E3%81%8A%E7%9F%A5%E3%82%89%E3%81%9B'));

        $this->assertTrue(Gate::matchesPath(['app/admin/*'], 'app/admin/staff/1'));
        $this->assertFalse(Gate::matchesPath(['app/admin/*'], 'app/admin'));
        $this->assertFalse(Gate::matchesPath(['up'], 'upload'));
    }

    public function test_forwarded_address_is_read_only_from_trusted_proxies(): void
    {
        // 信頼するプロキシが無ければ、ヘッダーは見ない
        $this->assertSame('203.0.113.5', Gate::clientIp([], '203.0.113.5', '1.2.3.4'));

        // 信頼するプロキシから届けば、右から見て、信頼するプロキシでない最初のIPアドレス
        $this->assertSame('198.51.100.7', Gate::clientIp(['10.0.0.5'], '10.0.0.5', '1.2.3.4, 198.51.100.7'));
        $this->assertSame('198.51.100.7', Gate::clientIp(['10.0.0.0/8'], '10.0.0.5', '198.51.100.7, 10.0.0.9'));

        // 信頼しない相手から届いたヘッダーは見ない
        $this->assertSame('192.0.2.1', Gate::clientIp(['10.0.0.5'], '192.0.2.1', '198.51.100.7'));
    }
}
