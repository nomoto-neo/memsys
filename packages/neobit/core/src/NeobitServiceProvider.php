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
    /** 共通部品のArtisanコマンドを登録する */
    public function boot(): void
    {
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
