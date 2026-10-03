<?php

use App\Console\Commands\CleanupTemporaryData;
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
