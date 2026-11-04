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
use Neobit\Core\Security\Gatekeeper;
use Neobit\Core\Security\GatekeeperState;
use Neobit\Core\Security\IpBlocker;
use Tests\TestCase;

/**
 * 入口の見張りの状態（Neobit\Core\Security\GatekeeperState）と、見張り（packages/neobit/core/gatekeeper.php）。
 * IPブロックとアクセス制限を変えると、状態のファイルに書き出され、見張りはそれを読んでLaravelと同じ判断をする。
 */
class GatekeeperStateTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACKER = '203.0.113.5';

    private const VISITOR = '198.51.100.7';

    /** 見張りを別のPHPで動かし、出したものを返す。通したときは「PASSED」を出す */
    private function runGatekeeper(string $ip, string $uri = '/', ?string $forwardedFor = null): string
    {
        $server = ['REMOTE_ADDR' => $ip, 'REQUEST_URI' => $uri];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }
        $code = '$_SERVER = array_merge($_SERVER, '.var_export($server, true).');'
            .'define("NEOBIT_GATEKEEPER_STATE", '.var_export(GatekeeperState::stateFile(), true).');'
            .'require '.var_export(base_path('packages/neobit/core/gatekeeper.php'), true).';'
            .'echo "PASSED";';

        return Process::run([PHP_BINARY, '-r', $code])->throw()->output();
    }

    public function test_blocking_and_releasing_write_the_state(): void
    {
        $block = IpBlocker::block(self::ATTACKER, now()->addDay(), 'テスト');

        $state = GatekeeperState::read();
        $this->assertSame(self::ATTACKER, $state['blocks'][0][0]);
        $this->assertSame($block->expires_at->getTimestamp(), $state['blocks'][0][1]);

        IpBlocker::release($block);

        $this->assertSame([], GatekeeperState::read()['blocks']);
    }

    public function test_access_restriction_writes_the_screen_and_env_addresses(): void
    {
        config(['neobit.security.site_allowed_ips' => ['192.0.2.0/24'], 'neobit.security.trusted_proxies' => ['10.0.0.5']]);

        AccessRestriction::save(true, '203.0.113.10', true, '192.0.2.50');

        $state = GatekeeperState::read();
        $this->assertTrue($state['site']['restricted']);
        $this->assertSame(['203.0.113.10', '192.0.2.0/24'], $state['site']['ips']);
        $this->assertSame(['192.0.2.50'], $state['admin']['ips']);
        $this->assertSame(['10.0.0.5'], $state['trusted_proxies']);

        // 制限を掛けないURLには、bootstrap/app.phpで渡したものと管理画面の下が入る
        $this->assertContains('up', $state['site_except']);
        $this->assertContains(GatekeeperState::adminPath().'/*', $state['site_except']);
    }

    public function test_gatekeeper_stops_like_laravel_with_the_same_pages(): void
    {
        IpBlocker::block(self::ATTACKER, null, 'テスト');

        // 止めたIPアドレスには、ルートの無いURLと同じ404の画面を返す
        $missing = $this->withServerVariables(['REMOTE_ADDR' => self::VISITOR])->get('/no-such-page')->assertNotFound()->getContent();
        $this->assertSame($missing, $this->runGatekeeper(self::ATTACKER));
        $this->assertSame('PASSED', $this->runGatekeeper(self::VISITOR));

        // サイトの制限では、メンテナンス中の画面を返す。管理画面の下は通す
        AccessRestriction::save(true, '', false, '');
        $maintenance = $this->withServerVariables(['REMOTE_ADDR' => self::VISITOR])->get('/')->assertStatus(503)->getContent();
        $this->assertSame($maintenance, $this->runGatekeeper(self::VISITOR, '/about'));
        $this->assertSame('PASSED', $this->runGatekeeper(self::VISITOR, '/'.GatekeeperState::adminPath().'/login'));
    }

    public function test_gatekeeper_lets_everything_pass_without_a_readable_state(): void
    {
        IpBlocker::block(self::ATTACKER, null, 'テスト');
        File::put(GatekeeperState::stateFile(), '{壊れたファイル');

        // 後ろのLaravelのミドルウェアが判断する
        $this->assertSame('PASSED', $this->runGatekeeper(self::ATTACKER));
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

            $this->assertSame($laravel, Gatekeeper::clientIp($proxies, $remote, $forwarded), json_encode([$proxies, $remote, $forwarded]));
        }
        TrustProxies::flushState();
    }

    public function test_state_is_written_again_when_missing_or_older_than_the_config_cache(): void
    {
        // 無ければ、最初のアクセスで書き出す。見張りが返す画面も作る
        $this->get('/');
        $this->assertFileExists(GatekeeperState::stateFile());
        $this->assertFileExists(dirname(GatekeeperState::stateFile()).'/404.html');

        // 設定のキャッシュが写しより新しければ、書き出し直す。設定のキャッシュの場所は、プロジェクトのルートからのパスで差し替える
        $configCache = 'storage/framework/testing/gatekeeper-config-cache.php';
        File::ensureDirectoryExists(dirname(base_path($configCache)));
        File::put(base_path($configCache), '<?php return [];');
        $_SERVER['APP_CONFIG_CACHE'] = $configCache;
        try {
            touch(GatekeeperState::stateFile(), time() - 60);
            config(['neobit.security.trusted_proxies' => ['10.0.0.9']]);

            $this->get('/');

            $this->assertSame(['10.0.0.9'], GatekeeperState::read()['trusted_proxies']);
        } finally {
            unset($_SERVER['APP_CONFIG_CACHE']);
            File::delete(base_path($configCache));
        }
    }

    public function test_failed_export_after_a_release_is_reported(): void
    {
        $block = IpBlocker::block(self::ATTACKER, null, 'テスト');

        // 書き出す場所をファイルでふさぎ、書き出せなくする
        File::deleteDirectory(dirname(GatekeeperState::stateFile()));
        File::put(dirname(GatekeeperState::stateFile()), '');
        Log::spy();

        IpBlocker::release($block);

        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'neobit:gatekeeper --export'))->once();
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
        $this->assertSame([], GatekeeperState::read()['blocks']);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, '画面を作れませんでした'));
    }

    /** WordPressを置く案件の設定。$gatekeeperは.envのWORDPRESS_GATEKEEPERにあたる */
    private function useWordPress(string $gatekeeper, string $wpConfig = 'wp-config.php'): void
    {
        config(['neobit.wordpress' => [
            'gatekeeper' => $gatekeeper,
            'admin_paths' => ['wp-admin', 'wp-admin/*', 'wp-login.php'],
            'open_paths' => ['wp-admin/admin-ajax.php', 'wp-cron.php'],
            'hidden_paths' => ['xmlrpc.php'],
            'wp_config' => $wpConfig,
            'upload_path' => 'public/wp-content/uploads',
            'watch_exclude' => [],
        ]]);
    }

    public function test_gatekeeper_guards_wordpress_paths(): void
    {
        // 段が書かれていれば、WordPressのURLの決まりを書き出す
        $this->useWordPress('prepend');
        $missing = $this->withServerVariables(['REMOTE_ADDR' => self::VISITOR])->get('/no-such-page')->assertNotFound()->getContent();
        AccessRestriction::save(true, '', true, '192.0.2.50');
        $this->assertSame(['wp-admin', 'wp-admin/*', 'wp-login.php'], GatekeeperState::read()['wordpress']['admin']);

        $this->assertSame($missing, $this->runGatekeeper(self::VISITOR, '/xmlrpc.php'));
        $this->assertSame($missing, $this->runGatekeeper(self::VISITOR, '/wp-login.php'));
        $this->assertSame('PASSED', $this->runGatekeeper(self::VISITOR, '/wp-cron.php?doing_wp_cron=1'));
        $this->assertSame('PASSED', $this->runGatekeeper('192.0.2.50', '/wp-admin/'));

        // 管理画面に入れる人は、メンテナンス中のサイトも開ける
        $this->assertSame('PASSED', $this->runGatekeeper('192.0.2.50', '/wp-json/wp/v2/posts/1'));

        // 段が書かれていなければ、WordPressの決まりは書き出さない
        $this->useWordPress('');
        GatekeeperState::export();
        $this->assertNull(GatekeeperState::read()['wordpress']);
    }

    public function test_missing_gatekeeper_setting_is_reported(): void
    {
        $this->useWordPress('');
        GatekeeperState::export();
        Log::spy();

        // 1時間に1回だけ知らせる
        GatekeeperState::watchWordPress();
        GatekeeperState::watchWordPress();
        Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'WORDPRESS_GATEKEEPER'))->once();

        $this->artisan('neobit:gatekeeper')->expectsOutputToContain('.env の WORDPRESS_GATEKEEPER がありません')->assertFailed();

        // WordPressを置かない案件では、何も言わない
        config(['neobit.wordpress' => null]);
        GatekeeperState::export();
        $this->artisan('neobit:gatekeeper')->doesntExpectOutputToContain('WordPress')->assertSuccessful();
    }

    public function test_prepend_is_checked_on_web_requests(): void
    {
        $this->useWordPress('prepend');
        GatekeeperState::export();
        Log::spy();

        // コマンドからは分からないので、Webの要求で見るまでは確かめていない。送った直後の手順を失敗にしないよう、警告だけ
        $this->artisan('neobit:gatekeeper')->expectsOutputToContain('まだ Web の要求で確かめていません')->assertSuccessful();

        // auto_prepend_fileが見張りを指していなければ、変わったときに1回知らせる
        GatekeeperState::watchWordPress('');
        GatekeeperState::watchWordPress('');
        Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'auto_prepend_file'))->once();
        $this->artisan('neobit:gatekeeper')->expectsOutputToContain('gatekeeper.php が設定されていません')->assertFailed();

        // 指していれば、確かめた時刻を出す
        GatekeeperState::watchWordPress(base_path('packages/neobit/core/gatekeeper.php'));
        $this->artisan('neobit:gatekeeper')->expectsOutputToContain('WordPress の保護機能：prepend（')->assertSuccessful();
    }

    public function test_wp_config_gatekeeper_is_checked_in_the_file(): void
    {
        // wp-config.phpの場所は、プロジェクトのルートからのパス
        $wpConfig = 'storage/framework/testing/wp-config-gatekeeper-test.php';
        File::ensureDirectoryExists(dirname(base_path($wpConfig)));
        $this->useWordPress('wp-config', $wpConfig);
        GatekeeperState::export();

        try {
            File::put(base_path($wpConfig), "<?php\ndefine('DB_NAME', 'wp');\n");
            $this->artisan('neobit:gatekeeper')->expectsOutputToContain('gatekeeper.php の require がありません')->assertFailed();

            // コメントにした行は数えない
            File::put(base_path($wpConfig), "<?php\n// require __DIR__.'/vendor/neobit/core/gatekeeper.php';\n  # require 'gatekeeper.php';\n");
            $this->artisan('neobit:gatekeeper')->expectsOutputToContain('gatekeeper.php の require がありません')->assertFailed();

            File::put(base_path($wpConfig), "<?php\nrequire __DIR__.'/vendor/neobit/core/gatekeeper.php';\ndefine('DB_NAME', 'wp');\n");
            $this->artisan('neobit:gatekeeper')->expectsOutputToContain('WordPress の保護機能：wp-config')->assertSuccessful();
        } finally {
            File::delete(base_path($wpConfig));
        }
    }

    public function test_command_shows_the_state_and_finds_a_stale_copy(): void
    {
        AccessRestriction::save(false, '', true, '192.0.2.50');
        IpBlocker::block(self::ATTACKER, null, 'テスト');

        $this->artisan('neobit:gatekeeper')
            ->expectsOutputToContain('止めている IP：1件')
            ->expectsOutputToContain('サイトの制限：しない')
            ->expectsOutputToContain('管理画面の制限：する（許す IP：1件）')
            ->assertSuccessful();

        // 書き出しを通さずにDBを変えると、写しが食い違う。--exportで直る
        IpBlock::query()->delete();
        cache()->flush();

        $this->artisan('neobit:gatekeeper')->expectsOutputToContain('食い違っています')->assertFailed();
        $this->artisan('neobit:gatekeeper --export')
            ->expectsOutputToContain('状態のファイルを書き出しました。')
            ->expectsOutputToContain('止めている IP：0件')
            ->assertSuccessful();
    }
}
