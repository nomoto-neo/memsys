<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 管理画面の頭のURL（設定のneobit.admin_path）。/adminは置かず、管理画面は検索に出さない
 * （Neobit\Core\Http\Middleware\NoIndexHeader）。
 */
class AdminPathTest extends TestCase
{
    public function test_admin_lives_under_the_configured_path(): void
    {
        $this->assertSame('/app/admin/login', parse_url(route('admin.login'), PHP_URL_PATH));

        // 管理画面は検索に出さない
        $this->get(route('admin.login'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // /adminには何も置かない。新しいURLへも移さない
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();

        // 訪問者の画面には付けない
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }
}
