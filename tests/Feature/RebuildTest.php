<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Neobit\Core\Operations\AssetRebuilder;
use Tests\TestCase;

/**
 * 画面とJS・CSSの作り直し（Neobit\Core\Operations\AssetRebuilder）。
 * 手元の画面とビルドを書き換えないよう、作業用のディレクトリに、元のファイル・コンパイル済みの画面・公開ディレクトリを作る。
 * npm run buildは、出し先にmanifest.jsonを書く偽物にする。
 */
class RebuildTest extends TestCase
{
    /** 作業用のディレクトリ（プロジェクトの直下に見立てる） */
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/neobit-rebuild-'.uniqid();
        File::ensureDirectoryExists($this->base.'/resources/views');
        File::ensureDirectoryExists($this->base.'/resources/js');
        File::ensureDirectoryExists($this->base.'/bootstrap/cache/views');
        File::ensureDirectoryExists($this->base.'/public/build');
        file_put_contents($this->base.'/resources/views/top.blade.php', '<p>{{ $name }}</p>');
        file_put_contents($this->base.'/resources/js/app.js', 'console.log(1);');
        file_put_contents($this->base.'/package.json', '{}');
        file_put_contents($this->base.'/public/build/manifest.json', '{"old":true}');
        file_put_contents($this->base.'/bootstrap/cache/views/.gitignore', "*
!.gitignore
");

        // 画面の場所とコンパイル済みの置き場所を、作業用のディレクトリにする
        config(['view.compiled' => $this->base.'/bootstrap/cache/views', 'neobit.rebuild.npm' => 'npm']);
        $this->app->instance('blade.compiler', new BladeCompiler(new Filesystem(), $this->base.'/bootstrap/cache/views', $this->base));
        $this->app['view']->setFinder(new FileViewFinder(new Filesystem(), [$this->base.'/resources/views']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    private function rebuilder(): AssetRebuilder
    {
        return new AssetRebuilder($this->base, $this->base.'/public');
    }

    /** npm run buildの偽物。成功なら、出し先（--outDirの次）にmanifest.jsonを書く */
    private function fakeBuild(bool $success): void
    {
        Process::fake(function ($process) use ($success) {
            if (! $success) {
                return Process::result(output: '', errorOutput: 'SyntaxError: Unexpected token', exitCode: 1);
            }
            $command = $process->command;
            $outDir = $command[array_search('--outDir', $command, true) + 1];
            File::ensureDirectoryExists($outDir);
            file_put_contents($outDir.'/manifest.json', '{"new":true}');

            return Process::result('built');
        });
    }

    /** 元のファイルを書き換える。日時が同じ秒にならないよう、日時も進める */
    private function touchSource(string $path, string $contents): void
    {
        file_put_contents($this->base.'/'.$path, $contents);
        touch($this->base.'/'.$path, time() + 10);
    }

    public function test_first_run_only_records_the_signature(): void
    {
        $this->fakeBuild(true);

        $done = $this->rebuilder()->run();

        $this->assertStringContainsString('しるしを記録しました', $done[0]);
        $this->assertFileExists($this->base.'/bootstrap/cache/rebuild.json');
        $this->assertSame([], glob($this->base.'/bootstrap/cache/views/*.php'));
        Process::assertNothingRan();

        // 何も変わっていなければ、何もしない
        $this->assertSame([], $this->rebuilder()->run());
    }

    public function test_changed_view_is_compiled_and_removed_view_is_cleaned(): void
    {
        $this->fakeBuild(true);
        $this->rebuilder()->run();

        // 画面を足すと、全部をコンパイルする
        $this->touchSource('resources/views/news.blade.php', '<h1>お知らせ</h1>');
        $done = $this->rebuilder()->run();

        $this->assertStringContainsString('画面を作り直しました', $done[0]);
        $this->assertCount(2, glob($this->base.'/bootstrap/cache/views/*.php'));
        $this->assertFileExists($this->base.'/bootstrap/cache/views/.gitignore');
        $this->assertDirectoryDoesNotExist($this->base.'/bootstrap/cache/views_next');
        $this->assertDirectoryDoesNotExist($this->base.'/bootstrap/cache/views_old');
        Process::assertNothingRan();

        // 画面を消すと、そのコンパイル済みファイルも消す
        unlink($this->base.'/resources/views/news.blade.php');
        $this->rebuilder()->run();

        $this->assertCount(1, glob($this->base.'/bootstrap/cache/views/*.php'));
    }

    public function test_changed_script_is_built_and_swapped(): void
    {
        $this->fakeBuild(true);
        $this->rebuilder()->run();

        $this->touchSource('resources/js/app.js', 'console.log(2);');
        $done = $this->rebuilder()->run();

        $this->assertStringContainsString('JS・CSS を作り直しました', $done[0]);
        $this->assertSame('{"new":true}', file_get_contents($this->base.'/public/build/manifest.json'));
        $this->assertDirectoryDoesNotExist($this->base.'/public/build_next');
        $this->assertDirectoryDoesNotExist($this->base.'/public/build_old');
    }

    public function test_failed_build_keeps_the_previous_build_and_is_not_retried(): void
    {
        $this->fakeBuild(false);
        $this->rebuilder()->run();

        $this->touchSource('resources/js/app.js', 'console.log(;');
        $done = $this->rebuilder()->run();

        $this->assertStringContainsString('JS・CSS の作り直しに失敗しました', $done[0]);
        $this->assertStringContainsString('SyntaxError', $done[0]);
        $this->assertSame('{"old":true}', file_get_contents($this->base.'/public/build/manifest.json'));
        $this->assertDirectoryDoesNotExist($this->base.'/public/build_next');

        // 元のファイルがまた変わるまでは、作り直さない
        $this->assertSame([], $this->rebuilder()->run());
    }

    public function test_changed_package_is_reported_without_building(): void
    {
        $this->fakeBuild(true);
        $this->rebuilder()->run();

        $this->touchSource('package.json', '{"dependencies":{}}');
        $this->touchSource('resources/js/app.js', 'console.log(3);');
        $done = $this->rebuilder()->run();

        $this->assertStringContainsString('npm ci', $done[0]);
        $this->assertSame('{"old":true}', file_get_contents($this->base.'/public/build/manifest.json'));
        Process::assertNothingRan();
    }

    public function test_second_run_waits_while_the_lock_is_held(): void
    {
        $first = $this->rebuilder();
        $this->assertTrue($first->lock());
        $this->assertFalse($this->rebuilder()->lock());
        $first->unlock();

        $third = $this->rebuilder();
        $this->assertTrue($third->lock());
        $third->unlock();
    }
}
