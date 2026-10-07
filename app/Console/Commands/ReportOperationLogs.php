<?php

namespace App\Console\Commands;

use App\Mail\TemplatedMail;
use App\Support\OperationLogReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 操作ログの報告のコマンド。php artisan app:report-operation-logsで動く。
 *
 * 報告の中身はApp\Support\OperationLogReportにあり、このコマンドはそれをメールで送るだけ。
 * スケジューラーから毎朝呼ばれ、前の日の分を、気になる点があったときだけ送る。
 * 宛先は.envのOPERATION_REPORT_TO（カンマ区切りで複数書ける）。空なら送らない。
 *
 * ■ 手で動かすとき
 * --date=2026-10-04  その日の分を作る。書かなければ前の日
 * --always           気になる点が無くても送る。文面や宛先を確かめるときに使う
 */
#[Signature('app:report-operation-logs {--date= : 報告する日（書かなければ前の日）} {--always : 気になる点が無くても送る}')]
#[Description('操作ログの1日ぶんの報告を、気になる点があればメールで送る')]
class ReportOperationLogs extends Command
{
    public function handle(): int
    {
        $date = $this->option('date') !== null ? Carbon::parse($this->option('date')) : today()->subDay();

        $report = OperationLogReport::for($date);

        // 気になる点が無ければ送らない
        if ($report['notable'] === '' && ! $this->option('always')) {
            $this->info($report['date'].'の操作ログに、気になる点はありませんでした。メールは送りません。');

            return self::SUCCESS;
        }

        $recipients = array_filter(array_map('trim', explode(',', (string) config('logging.operation_report_to'))));

        if ($recipients === []) {
            $this->warn('宛先（OPERATION_REPORT_TO）が設定されていないので、メールは送りません。');

            return self::SUCCESS;
        }

        try {
            Mail::send(new TemplatedMail('operation_log_report', $report + [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => implode(', ', $recipients),
                'app_name' => config('app.site_name'),
                'url' => route('admin.operation-logs.index'),
            ]));
        } catch (Throwable $e) {
            // 送れなかったことはログに残す。エラーの通知が設定されていれば、開発者に届く
            Log::error('ReportOperationLogs: 操作ログの報告のメールを送れませんでした。', ['message' => $e->getMessage()]);
            $this->error('メールを送れませんでした。'.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($report['date'].'の操作ログの報告を送りました。');

        return self::SUCCESS;
    }
}
