<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Neobit\Core\Models\IpBlock;
use Neobit\Core\Security\AccessRestriction;
use Neobit\Core\Security\Gate;
use Neobit\Core\Security\GateState;
use Neobit\Core\Security\IpBlocker;
use Tests\TestCase;

/**
 * 入口の見張りの状態（Neobit\Core\Security\GateState）と、見張り（packages/neobit/core/gate.php）。
 * IPブロックとアクセス制限を変えると、状態のファイルに書き出され、見張りはそれを読んでLaravelと同じ判断をする。
 */
class GateStateTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACKER = '203.0.113.5';

    private const VISITOR = '198.51.100.7';

    /** 見張りを別のPHPで動かし、出したものを返す。通したときは「PASSED」を出す */
    private function runGate(string $ip, string $uri = '/', ?string $forwardedFor = null): string
    {
        $server = ['REMOTE_ADDR' => $ip, 'REQUEST_URI' => $uri];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }
        $code = '$_SERVER = array_merge($_SERVER, '.var_export($server, true).');'
            .'define("NEOBIT_GATE_STATE", '.var_export(GateState::stateFile(), true).');'
            .'require '.var_export(base_path('packages/neobit/core/gate.php'), true).';'
            .'echo "PASSED";';

        return Process::run([PHP_BINARY, '-r', $code])->throw()->output();
    }

    public function test_blocking_and_releasing_write_the_state(): void
    {
        $block = IpBlocker::block(self::ATTACKER, now()->addDay(), 'テスト');

        $state = GateState::read();
        $this->assertSame(self::ATTACKER, $state['blocks'][0][0]);
        $this->assertSame($block->blocked_until->getTimestamp(), $state['blocks'][0][1]);

        IpBlocker::release($block);

        $this->assertSame([], GateState::read()['blocks']);
    }

    public function test_access_restriction_writes_the_screen_and_env_addresses(): void
    {
        config(['app.site_allowed_ips' => ['192.0.2.0/24'], 'app.trusted_proxies' => ['10.0.0.5']]);

        AccessRestriction::save(true, ['203.0.113.10'], true, ['192.0.2.50']);

        $state = GateState::read();
        $this->assertTrue($state['site']['restricted']);
        $this->assertSame(['203.0.113.10', '192.0.2.0/24'], $state['site']['ips']);
        $this->assertSame(['192.0.2.50'], $state['admin']['ips']);
        $this->assertSame(['10.0.0.5'], $state['trusted_proxies']);

        // 制限を掛けないURLには、bootstrap/app.phpで渡したものと管理画面の下が入る
        $this->assertContains('up', $state['site_except']);
        $this->assertContains(GateState::adminPath().'/*', $state['site_except']);
    }

    public function test_gate_stops_like_laravel_with_the_same_pages(): void
    {
        IpBlocker::block(self::ATTACKER, null, 'テスト');

        // 止めたIPアドレスには、ルートの無いURLと同じ404の画面を返す
        $missing = $this->withServerVariables(['REMOTE_ADDR' => self::VISITOR])->get('/no-such-page')->assertNotFound()->getContent();
        $this->assertSame($missing, $this->runGate(self::ATTACKER));
        $this->assertSame('PASSED', $this->runGate(self::VISITOR));

        // サイトの制限では、メンテナンス中の画面を返す。管理画面の下は通す
        AccessRestriction::save(true, [], false, []);
        $maintenance = $this->withServerVariables(['REMOTE_ADDR' => self::VISITOR])->get('/')->assertStatus(503)->getContent();
        $this->assertSame($maintenance, $this->runGate(self::VISITOR, '/about'));
        $this->assertSame('PASSED', $this->runGate(self::VISITOR, '/'.GateState::adminPath().'/login'));
    }

    public function test_gate_lets_everything_pass_without_a_readable_state(): void
    {
        IpBlocker::block(self::ATTACKER, null, 'テスト');
        File::put(GateState::stateFile(), '{壊れたファイル');

        // 後ろのLaravelのミドルウェアが判断する
        $this->assertSame('PASSED', $this->runGate(self::ATTACKER));
        $this->withServerVariables(['REMOTE_ADDR' => self::ATTACKER])->get('/')->assertNotFound();
    }

    public function test_client_address_is_the_same_as_laravel(): void
    {
        $cases = [
            [[], '203.0.113.5', '1.2.3.4'],
            [['10.0.0.5'], '10.0.0.5', '1.2.3.4, 198.51.100.7'],
            [['10.0.0.0/8'], '10.0.0.5', '198.51.100.7, 10.0.0.9'],
            [['10.0.0.5'], '10.0.0.5', '10.0.0.5'],
            [['10.0.0.5'], '10.0.0.5', 'not-an-ip, 198.51.100.7:8080'],
            [['10.0.0.5'], '192.0.2.1', '198.51.100.7'],
            [['REMOTE_ADDR'], '10.0.0.5', '198.51.100.7'],
            [['PRIVATE_SUBNETS'], '10.0.0.5', '198.51.100.8, 192.168.1.1'],
            [['*'], '10.0.0.5', '198.51.100.7'],
            [['2001:db8::/32'], '2001:db8::1', '[2001:db8:ffff::9]:443, 2400:4050::1'],
        ];

        foreach ($cases as [$proxies, $remote, $forwarded]) {
            // LaravelのTrustProxiesを通した後の$request->ip()と比べる
            $request = Request::create('/', server: ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => $forwarded]);
            TrustProxies::at($proxies);
            $laravel = (new TrustProxies())->handle($request, fn (Request $request) => response((string) $request->ip()))->getContent();

            $this->assertSame($laravel, Gate::clientIp($proxies, $remote, $forwarded), json_encode([$proxies, $remote, $forwarded]));
        }
        TrustProxies::flushState();
    }

    public function test_state_is_written_again_when_missing_or_older_than_the_config_cache(): void
    {
        // 無ければ、最初のアクセスで書き出す。見張りが返す画面も作る
        $this->get('/');
        $this->assertFileExists(GateState::stateFile());
        $this->assertFileExists(dirname(GateState::stateFile()).'/404.html');

        // 設定のキャッシュが写しより新しければ、書き出し直す。設定のキャッシュの場所は、プロジェクトのルートからのパスで差し替える
        $configCache = 'storage/framework/testing/gate-config-cache.php';
        File::ensureDirectoryExists(dirname(base_path($configCache)));
        File::put(base_path($configCache), '<?php return [];');
        $_SERVER['APP_CONFIG_CACHE'] = $configCache;
        try {
            touch(GateState::stateFile(), time() - 60);
            config(['app.trusted_proxies' => ['10.0.0.9']]);

            $this->get('/');

            $this->assertSame(['10.0.0.9'], GateState::read()['trusted_proxies']);
        } finally {
            unset($_SERVER['APP_CONFIG_CACHE']);
            File::delete(base_path($configCache));
        }
    }

    public function test_failed_export_after_a_release_is_reported(): void
    {
        $block = IpBlocker::block(self::ATTACKER, null, 'テスト');

        // 書き出す場所をファイルでふさぎ、書き出せなくする
        File::deleteDirectory(dirname(GateState::stateFile()));
        File::put(dirname(GateState::stateFile()), '');
        Log::spy();

        IpBlocker::release($block);

        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'app:gate --export'))->once();
    }

    public function test_state_is_written_even_when_the_pages_cannot_be_drawn(): void
    {
        // 案件が足した画面が、書き出しのときに描けない
        $views = storage_path('broken-views');
        File::ensureDirectoryExists($views);
        File::put($views.'/maintenance.blade.php', '@php throw new RuntimeException("描けません"); @endphp');
        View::prependNamespace('neobit', $views);
        Log::spy();

        $block = IpBlocker::block(self::ATTACKER, null, 'テスト');
        IpBlocker::release($block);

        // 状態は書き出され、画面の失敗だけがログに残る
        $this->assertSame([], GateState::read()['blocks']);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, '画面を作れませんでした'));
    }

    public function test_command_shows_the_state_and_finds_a_stale_copy(): void
    {
        AccessRestriction::save(false, [], true, ['192.0.2.50']);
        IpBlocker::block(self::ATTACKER, null, 'テスト');

        $this->artisan('app:gate')
            ->expectsOutputToContain('止めている IP：1件')
            ->expectsOutputToContain('サイトの制限：しない')
            ->expectsOutputToContain('管理画面の制限：する（許す IP：1件）')
            ->assertSuccessful();

        // 書き出しを通さずにDBを変えると、写しが食い違う。--exportで直る
        IpBlock::query()->delete();
        cache()->flush();

        $this->artisan('app:gate')->expectsOutputToContain('食い違っています')->assertFailed();
        $this->artisan('app:gate --export')
            ->expectsOutputToContain('状態のファイルを書き出しました。')
            ->expectsOutputToContain('止めている IP：0件')
            ->assertSuccessful();
    }
}
