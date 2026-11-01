<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Neobit\Core\Routing\FrameworkRoutes;
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
        $admin = config('neobit.route.admin_path');

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
        $this->assertSame(config('neobit.route.admin_path').'/login', $admin->uri());
        $this->assertContains('web', $admin->gatherMiddleware());
        $this->assertContains('admin.ip', $admin->gatherMiddleware());

        $this->assertContains('web', Route::getRoutes()->getByName('pages.show')->gatherMiddleware());
    }

    public function test_framework_routes_use_the_given_url_for_the_address_and_the_name(): void
    {
        // 見本のサイトと違うURLを渡しても、そのURLと、そこから作った名前でルートができること
        FrameworkRoutes::redirects('/tools/moved');
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame('tools/moved', Route::getRoutes()->getByName('tools.moved.index')->uri());
        $this->assertSame('tools/moved/csv-import/confirm', Route::getRoutes()->getByName('tools.moved.csv-import.confirm')->uri());
        $this->assertSame('tools/moved/{redirect}', Route::getRoutes()->getByName('tools.moved.update')->uri());
    }

    public function test_sample_site_writes_the_framework_screens_for_managers_only(): void
    {
        // 見本のサイトの8画面が、URLと同じ名前で、管理者だけのグループの中にあること
        $screens = [
            'codes',
            'ip-blocks',
            'access-restriction',
            'basic-auth',
            'file-watch',
            'seo-settings',
            'redirects',
            'operation-logs',
        ];

        foreach ($screens as $screen) {
            $route = Route::getRoutes()->getByName('admin.'.$screen.'.index');
            $this->assertSame(config('neobit.route.admin_path').'/'.$screen, $route->uri());
            $this->assertContains('acl.manager', $route->gatherMiddleware());
        }
    }
}
