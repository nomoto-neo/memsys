<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 画面に出すサイトの名前（.envのSITE_NAME。config('app.site_name')）。
 * 機械の名前のAPP_NAMEとは別に、日本語で決められる。
 */
class SiteNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_screens_show_the_site_name(): void
    {
        config(['app.site_name' => 'デモサイト']);

        // 訪問者の側は、メニューの左上と、題名を決めていない画面の題名
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>デモサイト</title>', false)
            ->assertSee('<a class="navbar-brand" href="/">デモサイト</a>', false);

        // 管理画面と企業会員の画面
        $this->get(route('admin.login'))->assertOk()->assertSee('デモサイト 管理画面');
        $this->get(route('company.login'))->assertOk()->assertSee('企業会員ログイン - デモサイト')->assertSee('デモサイト 企業会員');
    }

    public function test_site_name_falls_back_to_the_app_name(): void
    {
        // SITE_NAMEを書いていなければ、APP_NAMEが出る（config/app.php）
        $this->assertSame(config('app.name'), config('app.site_name'));
    }
}
