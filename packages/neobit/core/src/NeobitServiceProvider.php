<?php

namespace Neobit\Core;

use Illuminate\Support\ServiceProvider;
use Neobit\Core\Console\CleanupTemporaryData;
use Neobit\Core\Console\ReportOperationLogs;
use Neobit\Core\Console\ResetTwoFactor;
use Neobit\Core\Console\SetNextId;

/**
 * ネオビットフレームワークの共通部品を、アプリに組み込む。
 * Composer の自動検出で読み込まれる。
 */
class NeobitServiceProvider extends ServiceProvider
{
    /** 設定の既定値を登録する。アプリの config/neobit.php に書いたキーが、これに優先する */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/neobit.php', 'neobit');
    }

    /** 画面（neobit::）と、共通部品のArtisanコマンドを登録する */
    public function boot(): void
    {
        // アプリは、resources/views/vendor/neobit/ に同じ名前で置いて、画面を上書きする
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'neobit');

        // 共通のテーブルのマイグレーション（php artisan migrate で、アプリのものと一緒に流れる）
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // アップロードファイルのルート（uploads.tmp・uploads.show）
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupTemporaryData::class,
                ReportOperationLogs::class,
                ResetTwoFactor::class,
                SetNextId::class,
            ]);
        }
    }
}
