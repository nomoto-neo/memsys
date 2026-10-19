<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Neobit\Core\Operations\TemporaryDataCleaner;

/**
 * 一時データの後片付けのコマンド。php artisan app:cleanup-temporary-dataで動く。
 *
 * 片付けの中身はNeobit\Core\Operations\TemporaryDataCleanerにあり、このコマンドはそれを呼んで、
 * 消した件数を画面に出すだけ。ログにはTemporaryDataCleanerが残す。スケジューラーから1時間ごとに
 * 呼ばれるほか、手で動かしてもよい。
 */
#[Signature('app:cleanup-temporary-data')]
#[Description('一時ファイル・期限の切れたキャッシュなどの一時データを消す')]
class CleanupTemporaryData extends Command
{
    public function handle(): int
    {
        // ほかの片付けと重ならないように片付ける
        $counts = TemporaryDataCleaner::allExclusively();

        // アクセスを契機にした片付けなどが、今動いているとき
        if ($counts === null) {
            $this->info('ほかの片付けが動いているので、今回は取りやめました。');

            return self::SUCCESS;
        }

        // 消した件数を表で出す
        $this->table(['一時データ', '消した件数'], collect($counts)->map(fn (int $count, string $name) => [$name, $count])->values()->all());

        return self::SUCCESS;
    }
}
