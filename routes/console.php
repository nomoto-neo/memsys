<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Neobit\Core\Console\CleanupTemporaryData;
use Neobit\Core\Console\ReportOperationLogs;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * スケジューラーの設定。この下に書いた処理が、それぞれの時刻に動く。
 * サーバーのcronで、毎分 php artisan schedule:run を動かしておく。cronの設定は、
 * 利用ガイドの19章にある。登録した一覧は php artisan schedule:list で確かめられる。
 * cronの動かないサーバー（.envのCRON_ENABLED=false）では、同じ予定を、サイトへのアクセスを契機に
 * 動かす（Neobit\Core\Operations\RequestScheduler）。Schedule::exec()のシェルのコマンドは、そこでは動かない。
 */

// 一時データの後片付け（Neobit\Core\Operations\TemporaryDataCleaner）。24時間を過ぎた一時ファイルが、
// 遅くとも1時間以内に消えるよう、1時間ごとに動かす。前の回が終わっていなければ重ねて動かさない。
Schedule::command(CleanupTemporaryData::class)->hourly()->withoutOverlapping();

// 操作ログの報告（Neobit\Core\Operations\OperationLogReport）。毎朝、前の日の分を、気になる点があったときだけ
// 管理者へメールで送る。
Schedule::command(ReportOperationLogs::class)->dailyAt('08:00');

// キューに積んだジョブを送る係。毎分動かし、積まれているものが無くなったら止まる。
// 常駐の仕組みを入れなくても、今のcronのままでキューが動く。--max-timeは、次の回と重ならない秒数。
// --sleep=0は、積まれているものが無いときに、待たずにすぐ止まるため。既定の3秒待つと、cronの無いサーバーで
// アクセスを契機に動かすとき、そのたびに処理の枠を余分に使う
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--sleep=0'])->everyMinute()->withoutOverlapping();
