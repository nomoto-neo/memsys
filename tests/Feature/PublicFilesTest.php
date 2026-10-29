<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Neobit\Core\Http\PublicFiles;
use Neobit\Core\Http\PublicScriptResponse;
use Tests\TestCase;

/**
 * 公開ディレクトリのHTMLと古いPHPを、Laravelを通して返す（Neobit\Core\Http\PublicFiles・PublicFileController）。
 * 一時のディレクトリを公開ディレクトリにして試す。
 */
class PublicFilesTest extends TestCase
{
    /** 一時の公開ディレクトリ */
    private string $public;

    protected function setUp(): void
    {
        parent::setUp();

        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/public-files-test-'.uniqid().'/public';
        $this->putFile('index.php', '<?php // 入口');
        $this->putFile('page.html', '<p>ページ</p>');
        $this->putFile('about/index.html', '<p>会社案内</p>');
        $this->putFile('inquiry/index.php', '<?php echo "お問い合わせ";');
        $this->putFile('news/index.html', '<p>古いお知らせ</p>');
        $this->putFile('storage/shell.php', '<?php');
        $this->putFile('build/page.html', '<p>build</p>');
        $this->putFile('.hidden/page.html', '<p>hidden</p>');
        $this->putFile('app/admin/page.html', '<p>管理画面の下</p>');
        $this->putFile('style.txt', 'text');
        file_put_contents(dirname($this->public).'/outside.html', '<p>外</p>');

        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->public));

        parent::tearDown();
    }

    private function putFile(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname($this->public.'/'.$path));
        file_put_contents($this->public.'/'.$path, $content);
    }

    /** 末尾の/を残したまま送る。テストのクライアントは、URLの末尾の/を外して送るため */
    private function getRaw(string $uri): TestResponse
    {
        return TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle(Request::create('https://example.com'.$uri, 'GET')));
    }

    public function test_html_is_returned_through_the_middleware(): void
    {
        $response = $this->get('/page.html')->assertOk();

        // 中身はそのまま。charsetは付けず、ページのmetaに任せる。全体のミドルウェアのヘッダーが付く
        $this->assertSame('<p>ページ</p>', $response->getContent());
        $this->assertSame('text/html', $response->headers->get('Content-Type'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // 変わっていなければ304
        $this->get('/page.html', ['If-Modified-Since' => $response->headers->get('Last-Modified')])->assertStatus(304);
    }

    public function test_directory_urls_keep_the_trailing_slash(): void
    {
        // 末尾の/が無ければ付けたURLへ移し、/のあるURLでは外さずにindexを返す
        $this->getRaw('/about?x=1')->assertRedirect('https://example.com/about/?x=1')->assertStatus(301);
        $this->assertSame('<p>会社案内</p>', $this->getRaw('/about/')->assertOk()->getContent());

        // ディレクトリでなければ、今までどおり末尾の/を外す
        $this->getRaw('/page.html/')->assertRedirect('https://example.com/page.html');
    }

    public function test_laravel_routes_come_first(): void
    {
        // 公開ディレクトリにnews/があっても、/newsはLaravelのルート
        $this->withoutVite();
        $this->get('/news')->assertOk()->assertDontSee('古いお知らせ');
    }

    public function test_php_is_handed_to_the_entry_without_csrf(): void
    {
        // 入口が動かす目印の応答になる。POSTもCSRFの確かめで断らない
        foreach (['get', 'post'] as $method) {
            $response = $this->{$method}('/inquiry/index.php');
            $response->assertOk();
            $this->assertInstanceOf(PublicScriptResponse::class, $response->baseResponse);
            $this->assertSame(realpath($this->public.'/inquiry/index.php'), $response->baseResponse->script);
        }

        // ディレクトリのURLでも、index.phpを動かす
        $this->assertInstanceOf(PublicScriptResponse::class, $this->getRaw('/inquiry/')->baseResponse);
    }

    public function test_files_outside_the_allowed_places_are_not_returned(): void
    {
        foreach ([
            '/index.php',
            '/build/page.html',
            '/.hidden/page.html',
            '/app/admin/page.html',
            '/style.txt',
            '/missing.html',
            '/about/../../outside.html',
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }

        // /storage/の下は、Laravelのファイルを渡すルートが先に受ける。公開ディレクトリのファイルとしても返さない
        $this->assertNull(PublicFiles::file('/storage/shell.php'));
    }

    public function test_entry_runs_php_where_apache_would(): void
    {
        $cwd = getcwd();
        $errorHandler = set_error_handler(fn () => false);
        restore_error_handler();
        $exceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        $errorReporting = error_reporting();

        try {
            // 古い.phpは、動かすファイルのパスを返し、作業のディレクトリと$_SERVERをそのファイルにする
            $script = PublicFiles::handle($this->app, Request::create('https://example.com/inquiry/index.php', 'POST'));

            $this->assertSame(realpath($this->public.'/inquiry/index.php'), $script);
            $this->assertSame(realpath($this->public.'/inquiry'), getcwd());
            $this->assertSame('/inquiry/index.php', $_SERVER['PHP_SELF']);
            $this->assertSame($script, $_SERVER['SCRIPT_FILENAME']);
        } finally {
            chdir($cwd);
            set_error_handler($errorHandler);
            set_exception_handler($exceptionHandler);
            error_reporting($errorReporting);
        }

        // HTMLは、送って後片付けまで済ませ、nullを返す
        ob_start();
        $this->assertNull(PublicFiles::handle($this->app, Request::create('https://example.com/page.html')));
        $this->assertSame('<p>ページ</p>', ob_get_clean());
    }
}
