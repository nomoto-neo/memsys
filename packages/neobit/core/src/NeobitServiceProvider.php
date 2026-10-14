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
    /**
     * 既定値を持つ設定の名前。config/ の同じ名前のファイルが既定値になる。
     * stubs/config/ の同じ名前のファイルが、アプリへ写す見本になる。
     */
    private const CONFIG_NAMES = [
        'neobit',
        'upload',
        'cleanup',
    ];

    /**
     * 設定の既定値を登録する。アプリの config/ に同じ名前のファイルで書いたキーが、これに優先する。
     * 混ぜるのは、いちばん上の階層のキーだけ。配列の値は、アプリが書くと丸ごと置き換わる。
     */
    public function register(): void
    {
        foreach (self::CONFIG_NAMES as $name) {
            $this->mergeConfigFrom(__DIR__."/../config/{$name}.php", $name);
        }
    }

    /** 画面（neobit::）・マイグレーション・ルートと、設定を写す手段・共通部品のArtisanコマンドを登録する */
    public function boot(): void
    {
        // アプリは、resources/views/vendor/neobit/ に同じ名前で置いて、画面を上書きする
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'neobit');

        // 共通のテーブルのマイグレーション（php artisan migrate で、アプリのものと一緒に流れる）
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // アップロードファイルのルート（uploads.tmp・uploads.show）
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            // 設定の見本を、アプリの config/ へ写す（php artisan vendor:publish --tag=neobit-config）。
            // 写す元は stubs/config/ で、既定値と同じ値に // を付けてある
            $this->publishes(
                array_combine(
                    array_map(fn (string $name) => __DIR__."/../stubs/config/{$name}.php", self::CONFIG_NAMES),
                    array_map(fn (string $name) => config_path("{$name}.php"), self::CONFIG_NAMES),
                ),
                'neobit-config',
            );

            $this->commands([
                CleanupTemporaryData::class,
                ReportOperationLogs::class,
                ResetTwoFactor::class,
                SetNextId::class,
            ]);
        }
    }
}
