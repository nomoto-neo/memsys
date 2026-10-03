<?php

namespace App\Console\Commands;

use App\Support\TemporaryDataCleaner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 一時データの後片付けのコマンド。php artisan app:cleanup-temporary-dataで動く。
 *
 * 片付けの中身はApp\Support\TemporaryDataCleanerにあり、このコマンドはそれを呼んで、
 * 消した件数を画面とログに出すだけ。スケジューラーから1時間ごとに呼ばれるほか、手で動かしてもよい。
 */
#[Signature('app:cleanup-temporary-data')]
#[Description('一時ファイル・期限の切れたキャッシュなどの一時データを消す')]
class CleanupTemporaryData extends Command
{
    public function handle(): int
    {
        // 片付けて、消した件数を表で出す
        $counts = TemporaryDataCleaner::all();

        $this->table(['一時データ', '消した件数'], collect($counts)->map(fn (int $count, string $name) => [$name, $count])->values()->all());

        // 何も消さなかった回はログに残さない（1時間ごとに同じ行が並ばないように）
        if (array_sum($counts) > 0) {
            Log::info('一時データを消しました。', $counts);
        }

        return self::SUCCESS;
    }
}
