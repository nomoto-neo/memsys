<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\IpBlock;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Operations\TemporaryDataCleaner;
use Neobit\Core\Security\AccessRestriction;
use Neobit\Core\Security\IpBlocker;
use Tests\TestCase;

/**
 * IPブロック管理（Neobit\Core\Security\IpBlocker・RejectBlockedIp・IpBlockController）。
 * 止めたIPアドレスには、どのページも404を返す。存在しないURLへのアクセスが多いIPアドレスは、自動で止める。
 */
class IpBlockTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACKER = '203.0.113.5';

    private const VISITOR = '198.51.100.7';

    /** 指定したIPアドレスから開く */
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

    public function test_blocked_ip_gets_not_found_on_every_page(): void
    {
        IpBlocker::block(self::ATTACKER, now()->addDay(), 'テスト');

        // 止められていることに気付かせないよう、ルートの無いURLと同じ404の画面を返す
        $blocked = $this->fromIp(self::ATTACKER)->get('/')->assertNotFound()->getContent();
        $missing = $this->fromIp(self::VISITOR)->get('/no-such-page')->assertNotFound()->getContent();
        $this->assertSame($missing, $blocked);

        $this->fromIp(self::VISITOR)->get('/')->assertOk();
    }

    public function test_range_blocks_every_address_in_it(): void
    {
        IpBlocker::block('203.0.113.0/24', null, 'テスト');

        $this->fromIp('203.0.113.200')->get('/')->assertNotFound();
        $this->fromIp('203.0.114.1')->get('/')->assertOk();
    }

    public function test_block_ends_at_its_time(): void
    {
        IpBlocker::block(self::ATTACKER, now()->addHour(), 'テスト');

        $this->travel(2)->hours();

        $this->fromIp(self::ATTACKER)->get('/')->assertOk();

        // 期限の過ぎた行は、毎時の片付けが消す
        TemporaryDataCleaner::all();
        $this->assertSame(0, IpBlock::count());
    }

    public function test_many_missing_pages_block_the_ip_automatically(): void
    {
        config(['neobit.ip_block_watch_count' => 3, 'neobit.ip_block_hours' => 5]);

        foreach (range(1, 3) as $i) {
            $this->fromIp(self::ATTACKER)->get('/no-such-page-'.$i)->assertNotFound();
        }

        // 止めた後は、あるページも404になる
        $this->fromIp(self::ATTACKER)->get('/')->assertNotFound();
        $block = IpBlock::sole();
        $this->assertTrue($block->auto);
        $this->assertSame(3, $block->hits);
        $this->assertTrue($block->blocked_until->between(now()->addHours(5)->subMinute(), now()->addHours(5)->addMinute()));

        // 自動で止めたことは、操作した人のいない操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::IpBlock->value)->sole();
        $this->assertNull($log->operator_type);
        $this->assertSame(self::ATTACKER, $log->detail['ip']);
    }

    public function test_ip_is_blocked_automatically_even_without_the_setting(): void
    {
        // 設定のキャッシュが古く、ip_block_autoがまだ無いときも、自動で止める
        config(['neobit.ip_block_watch_count' => 2]);
        $settings = config('neobit');
        unset($settings['ip_block_auto']);
        config(['neobit' => $settings]);

        $this->fromIp(self::ATTACKER)->get('/no-such-page');
        $this->fromIp(self::ATTACKER)->get('/no-such-page');

        $this->assertSame(self::ATTACKER, IpBlock::sole()->ip);
    }

    public function test_ipv6_is_blocked_by_its_64_bit_range(): void
    {
        config(['neobit.ip_block_watch_count' => 2]);

        $this->fromIp('2001:db8:1:2::10')->get('/no-such-page');
        $this->fromIp('2001:db8:1:2::20')->get('/no-such-page');

        $this->assertSame('2001:db8:1:2::/64', IpBlock::sole()->ip);
        $this->fromIp('2001:db8:1:2:ffff::1')->get('/')->assertNotFound();
        $this->fromIp('2001:db8:1:3::1')->get('/')->assertOk();
    }

    public function test_allowed_ip_and_disabled_setting_are_not_blocked(): void
    {
        config(['neobit.ip_block_watch_count' => 2, 'app.admin_allowed_ips' => [self::ATTACKER]]);

        // 入ってよいIPアドレスは、自動で止めない。.envの分も、アクセス制限の画面で入れた分も
        $this->fromIp(self::ATTACKER)->get('/no-such-page');
        $this->fromIp(self::ATTACKER)->get('/no-such-page');
        AccessRestriction::save(false, '192.0.2.77', false, '');
        $this->fromIp('192.0.2.77')->get('/no-such-page');
        $this->fromIp('192.0.2.77')->get('/no-such-page');
        $this->assertSame(0, IpBlock::count());

        // 自動で止める設定を止めれば、数えない
        config(['neobit.ip_block_auto' => false]);
        $this->fromIp(self::VISITOR)->get('/no-such-page');
        $this->fromIp(self::VISITOR)->get('/no-such-page');
        $this->assertSame(0, IpBlock::count());
    }

    public function test_range_bounds_show_the_first_and_last_address(): void
    {
        $this->assertSame(['192.0.2.0', '192.0.2.255'], IpBlocker::rangeBounds('192.0.2.77/24'));
        $this->assertSame(['192.0.2.5', '192.0.2.5'], IpBlocker::rangeBounds('192.0.2.5'));
        $this->assertSame(['2001:db8::', '2001:db8::ffff:ffff:ffff:ffff'], IpBlocker::rangeBounds('2001:db8::/64'));
    }

    public function test_manager_registers_changes_and_releases_a_block(): void
    {
        $manager = $this->manager();

        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->get(route('admin.ip-blocks.index'))
            ->assertOk()
            ->assertSee(self::VISITOR);

        // 登録すると、一覧に止まる範囲が出る
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->post(route('admin.ip-blocks.store'), ['ip' => '203.0.113.9/30', 'reason' => '攻撃', 'days' => 7])
            ->assertRedirect(route('admin.ip-blocks.index'));

        $block = IpBlock::sole();
        $this->assertSame('203.0.113.9/30', $block->ip);
        $this->assertSame('管理 花子', $block->registered_by);
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->get(route('admin.ip-blocks.index'))
            ->assertSee('203.0.113.8 ～ 203.0.113.11');

        // 期限なしに変える
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->patch(route('admin.ip-blocks.update', $block), ['days' => 0]);
        $this->assertNull($block->fresh()->blocked_until);

        // 解除すると、行が消えて、そのIPアドレスから開けるようになる
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->delete(route('admin.ip-blocks.destroy', $block));
        $this->assertSame(0, IpBlock::count());
        $this->fromIp('203.0.113.9')->get('/')->assertOk();

        $this->assertSame(2, OperationLog::where('action', OperationLogAction::IpBlock->value)->count());
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::IpUnblock->value)->count());
    }

    public function test_own_ip_and_too_wide_range_cannot_be_registered(): void
    {
        $manager = $this->manager();

        // 自分のIPアドレスを含むまとまり
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->post(route('admin.ip-blocks.store'), ['ip' => '198.51.100.0/24', 'days' => 1])
            ->assertSessionHasErrors('ip');

        // 広すぎるまとまりと、IPアドレスでないもの
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->post(route('admin.ip-blocks.store'), ['ip' => '10.0.0.0/8', 'days' => 1])
            ->assertSessionHasErrors('ip');
        $this->fromIp(self::VISITOR)->actingAs($manager, 'admin')
            ->post(route('admin.ip-blocks.store'), ['ip' => 'example.com', 'days' => 1])
            ->assertSessionHasErrors('ip');

        $this->assertSame(0, IpBlock::count());
    }
}
