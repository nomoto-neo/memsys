<?php

use App\Console\Commands\CleanupTemporaryData;
use App\Console\Commands\ReportOperationLogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- スケジューラー ----
// サーバーのcronで、毎分 php artisan schedule:run を動かすと、ここに書いた処理が
// それぞれの時刻に動く（cronの設定は利用ガイドの19章）。登録した一覧は
// php artisan schedule:list で確かめられる。

// 一時データの後片付け（App\Support\TemporaryDataCleaner）。24時間を過ぎた一時ファイルが、
// 遅くとも1時間以内に消えるよう、1時間ごとに動かす。前の回が終わっていなければ重ねて動かさない。
Schedule::command(CleanupTemporaryData::class)->hourly()->withoutOverlapping();

// 操作ログの報告（App\Support\OperationLogReport）。毎朝、前の日の分を、気になる点があったときだけ
// 管理者へメールで送る。
Schedule::command(ReportOperationLogs::class)->dailyAt('08:00');

// キューに積んだジョブを送る係。毎分動かし、積まれているものが無くなったら止まる。
// 常駐の仕組みを入れなくても、今のcronのままでキューが動く。--max-timeは、次の回と重ならない秒数
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50'])->everyMinute()->withoutOverlapping();
