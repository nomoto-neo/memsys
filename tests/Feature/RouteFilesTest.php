<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ルートのファイルの読み込み（bootstrap/app.php）。ファイルを読む順番で、当たるルートが変わるので確かめる。
 */
class RouteFilesTest extends TestCase
{
    /** そのURLのGETが当たるルートの名前 */
    private function routeNameOf(string $url): ?string
    {
        return Route::getRoutes()->match(Request::create($url))->getName();
    }

    public function test_other_screens_are_matched_before_the_page_route(): void
    {
        // 固定ページのルートは/を含むURLも受けるので、ほかの画面のルートが先に当たること
        $admin = config('neobit.admin_path');

        $this->assertSame('mypage', $this->routeNameOf('/mypage'));
        $this->assertSame('company.login', $this->routeNameOf('/company/login'));
        $this->assertSame('company.mypage', $this->routeNameOf('/company/mypage'));
        $this->assertSame('admin.login', $this->routeNameOf('/'.$admin.'/login'));
        $this->assertSame('admin.news.index', $this->routeNameOf('/'.$admin.'/news'));
    }

    public function test_urls_of_no_screen_are_matched_by_the_page_route(): void
    {
        $this->assertSame('pages.show', $this->routeNameOf('/aboutus'));
        $this->assertSame('pages.show', $this->routeNameOf('/service/price'));
    }

    public function test_each_file_gets_its_prefix_and_middleware(): void
    {
        // ファイルごとに1回だけ付ける頭のURL・名前・ミドルウェアが、そのファイルのルートに付いていること
        $company = Route::getRoutes()->getByName('company.login');
        $this->assertSame('company/login', $company->uri());
        $this->assertContains('web', $company->gatherMiddleware());

        $admin = Route::getRoutes()->getByName('admin.login');
        $this->assertSame(config('neobit.admin_path').'/login', $admin->uri());
        $this->assertContains('web', $admin->gatherMiddleware());
        $this->assertContains('admin.ip', $admin->gatherMiddleware());

        $this->assertContains('web', Route::getRoutes()->getByName('pages.show')->gatherMiddleware());
    }
}
