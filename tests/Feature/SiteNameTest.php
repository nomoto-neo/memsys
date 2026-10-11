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

    public function test_site_name_is_read_from_the_env(): void
    {
        $this->assertSame('デモサイト', $this->siteNameWhen('デモサイト'));
    }

    public function test_site_name_stays_empty_without_falling_back_to_the_app_name(): void
    {
        // SITE_NAMEを書いていなくても、空で書いても、名前は空のまま。APP_NAMEは人に見せる名前ではないので、
        // 代わりに出さない（config/app.php）
        $this->assertSame('', $this->siteNameWhen(null));
        $this->assertSame('', $this->siteNameWhen(''));
    }

    /**
     * .envのSITE_NAMEがその値のときに、設定のサイトの名前がどうなるか。nullは、書いていないとき。
     * 手元の.envの中身に左右されないよう、環境変数を差し替えてconfig/app.phpを読み直す。
     * 読み終えたら、環境変数を元に戻す。
     */
    private function siteNameWhen(?string $value): string
    {
        $original = ['env' => $_ENV['SITE_NAME'] ?? null, 'server' => $_SERVER['SITE_NAME'] ?? null, 'getenv' => getenv('SITE_NAME')];

        try {
            $this->putSiteNameEnv($value, $value, $value ?? false);

            return (require config_path('app.php'))['site_name'];
        } finally {
            $this->putSiteNameEnv($original['env'], $original['server'], $original['getenv']);
        }
    }

    /** 環境変数のSITE_NAMEを、Laravelが読む3か所とも書き換える。nullかfalseは、無い状態にする */
    private function putSiteNameEnv(?string $env, ?string $server, string|false $getenv): void
    {
        if ($env === null) {
            unset($_ENV['SITE_NAME']);
        } else {
            $_ENV['SITE_NAME'] = $env;
        }

        if ($server === null) {
            unset($_SERVER['SITE_NAME']);
        } else {
            $_SERVER['SITE_NAME'] = $server;
        }

        putenv($getenv === false ? 'SITE_NAME' : 'SITE_NAME='.$getenv);
    }
}
