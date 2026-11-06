<?php

namespace Neobit\Core;

use Illuminate\Support\ServiceProvider;
use Neobit\Core\Console\AccessDeniedOff;
use Neobit\Core\Console\CheckGatekeeper;
use Neobit\Core\Console\CleanupTemporaryData;
use Neobit\Core\Console\ReportOperationLogs;
use Neobit\Core\Console\ResetTwoFactor;
use Neobit\Core\Console\SetNextId;
use Neobit\Core\Console\FileWatch;
use Neobit\Core\Console\Rebuild;

/**
 * ネオビットフレームワークの共通部品を、アプリに組み込む。
 * Composer の自動検出で読み込まれる。
 */
class NeobitServiceProvider extends ServiceProvider
{
    /** 既定値を持つ設定の節の名前。config/neobit/ の同じ名前のファイルが既定値になる */
    private const CONFIG_SECTIONS = [
        'site',
        'route',
        'layouts',
        'form',
        'auth',
        'code_table',
        'operation_log',
        'security',
        'file_watch',
        'seo',
        'schedule',
        'upload',
        'cleanup',
        'rebuild',
    ];

    /**
     * 設定の既定値を、節ごとに登録する。アプリの config/neobit.php の節に書いたキーが、これに優先する。
     * アプリは変える値だけを書くので、パッケージの更新で足した既定の値が、アプリのファイルを直さずに効く。
     * 混ぜるのは節の中の1段目のキーだけで、その中の配列は、アプリが書くと丸ごと置き換わる。
     * wordpressは既定値を持たない。アプリに節があることが、WordPressを置く印になるため。
     */
    public function register(): void
    {
        foreach (self::CONFIG_SECTIONS as $section) {
            $this->mergeConfigFrom(__DIR__."/../config/neobit/{$section}.php", "neobit.{$section}");
        }
    }

    /** 画面（neobit::）・マイグレーション・ルートと、設定を写す手段・共通部品のArtisanコマンドを登録する */
    public function boot(): void
    {
        // アプリは、resources/views/vendor/neobit/ に同じ名前で置いて、画面を上書きする。
        // パスに..を入れない。view:cacheは実際のパスでコンパイルするので、..があると別のファイルとみなされ、画面を開くときに作り直そうとする
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'neobit');

        // 共通のテーブルのマイグレーション（php artisan migrate で、アプリのものと一緒に流れる）
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // アップロードファイルのルート（uploads.tmp・uploads.show）
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            // 設定の見本を、アプリの config/ へ写す（php artisan vendor:publish --tag=neobit-config）
            $this->publishes(
                [__DIR__.'/../stubs/config/neobit.php' => config_path('neobit.php')],
                'neobit-config',
            );

            // 既定のメールのテンプレートを、アプリの resources/mail-templates/ へ写す
            // （php artisan vendor:publish --tag=neobit-mail-templates）
            $this->publishes(
                [__DIR__.'/../resources/mail-templates' => resource_path('mail-templates')],
                'neobit-mail-templates',
            );

            $this->commands([
                AccessDeniedOff::class,
                CheckGatekeeper::class,
                CleanupTemporaryData::class,
                ReportOperationLogs::class,
                ResetTwoFactor::class,
                SetNextId::class,
                FileWatch::class,
                Rebuild::class,
            ]);
        }
    }
}
