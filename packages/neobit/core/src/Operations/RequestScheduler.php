<?php

namespace Neobit\Core\Operations;

use Cron\CronExpression;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * cronの動かないサーバーで、サイトへのアクセスを契機に、スケジューラーの予定を動かす。
 * Neobit\Core\Http\Middleware\RunScheduleOnRequestが、応答を返した後に呼ぶ。
 *
 * ■ schedule:runとの違い
 * 予定はroutes/console.phpに書いたものをそのまま読み、動かし方だけを変える。
 * - 予定ごとに前に動いた時刻を覚えておき、それより後に予定の時刻が1回でもあれば動かす。
 *   schedule:runは、動いたその1分の予定しか見ないので、その1分にアクセスが無いと飛ばされるため。
 *   たまった回数分ではなく、1回だけ動かす
 * - Schedule::command()の予定は、別のプロセスを起動せず、同じプロセスの中でArtisan::call()で動かす。
 *   PHP-FPMの中ではphpのパスが違ったり、プロセスを起動する関数が止められていたりするため
 * - 初めて見る予定は、動かさずに今の時刻を覚えるだけにする。サーバーへ送った直後の最初の
 *   アクセスで、毎朝の報告などが時刻に関係なく動かないようにするため
 *
 * ■ 動かさないもの
 * - Schedule::exec()のシェルのコマンド。別のプロセスが要るので、警告のログを残して飛ばす
 * - runInBackground()は効かない。同じプロセスの中で、予定を順に動かす
 */
final class RequestScheduler
{
    /** 予定を見る間隔（秒）。スケジューラーと同じ1分ごと */
    private const CHECK_SECONDS = 60;

    /** 予定を見る間隔を数える、キャッシュのキー */
    private const CHECK_KEY = 'neobit_request_schedule_check';

    /** 予定を動かすのを1本だけにするロックの名前 */
    private const LOCK_KEY = 'neobit_request_schedule';

    /**
     * ロックを持ち続けてよい秒数。途中で処理が止まっても、この時間がたてばロックが外れ、
     * 次のアクセスで予定を動かせる。
     */
    private const LOCK_SECONDS = 600;

    /** 予定ごとに前に動いた時刻を覚える、キャッシュのキーの頭 */
    private const LAST_RUN_KEY = 'neobit_request_schedule_last_run:';

    /**
     * 前に見てから1分たっていれば、時刻の過ぎた予定を動かす。
     * 同時に来たアクセスのうち、予定を動かすのは1本だけにする。
     */
    public static function runDueEvents(): void
    {
        // 1分たっていなければ何もしない。addはキーが無いときだけ書き込めるので、通るのは1本だけ
        if (! Cache::add(self::CHECK_KEY, true, self::CHECK_SECONDS)) {
            return;
        }

        Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)->get(function () {
            // routes/console.phpの予定を読み込む。画面のリクエストでは、コンソールのカーネルを起動するまで読まれない
            app(ConsoleKernel::class)->bootstrap();

            foreach (app(Schedule::class)->events() as $event) {
                // 1つの予定で例外が起きても、ログに残して、ほかの予定は動かす
                try {
                    self::runIfDue($event);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    /** 前に動いてから予定の時刻が過ぎていれば、1つの予定を動かす。 */
    private static function runIfDue(Event $event): void
    {
        $key = self::LAST_RUN_KEY.$event->mutexName();
        $lastRun = Cache::get($key);
        $now = Carbon::now();

        // 初めて見る予定は、今の時刻を覚えるだけにする
        if ($lastRun === null) {
            Cache::forever($key, $now->getTimestamp());

            return;
        }

        // 前に動いた後に、予定の時刻が無ければ動かさない
        if (self::previousDueTime($event, $now)->getTimestamp() <= $lastRun) {
            return;
        }

        // メンテナンス中は動かさない。時刻を覚えないので、終わった後のアクセスで動く
        if (app()->isDownForMaintenance() && ! $event->runsInMaintenanceMode()) {
            return;
        }

        // 動かす前に時刻を覚える。長く掛かる予定が、次のアクセスで重ねて動かないようにするため
        Cache::forever($key, $now->getTimestamp());

        // 環境やwhen()などの条件に合わなければ、この回は動かさない
        if (! $event->runsInEnvironment(app()->environment()) || ! $event->filtersPass(app())) {
            return;
        }

        self::run($event);
    }

    /** 今の時刻より前で、いちばん新しい予定の時刻。今がちょうど予定の時刻なら、今。 */
    private static function previousDueTime(Event $event, Carbon $now): Carbon
    {
        $date = $event->timezone ? $now->copy()->setTimezone($event->timezone) : $now->copy();

        return Carbon::instance((new CronExpression($event->expression))->getPreviousRunDate($date, 0, true));
    }

    /** 1つの予定を、同じプロセスの中で動かす。 */
    private static function run(Event $event): void
    {
        // Schedule::call()・Schedule::job()の予定は、もともと同じプロセスの中で動く
        if ($event instanceof CallbackEvent) {
            $event->run(app());

            return;
        }

        // Schedule::command()の予定は、「php artisan」の後ろのコマンドを取り出して動かす
        $prefix = ConsoleApplication::formatCommandString('');
        if (! str_starts_with((string) $event->command, $prefix)) {
            // Schedule::exec()のシェルのコマンドは、別のプロセスが要るので動かさない
            Log::warning('cronの無いサーバーでは、シェルのコマンドの予定は動かしません。', ['command' => $event->command]);

            return;
        }

        // withoutOverlapping()の予定は、前の回が終わっていなければ動かさない
        if ($event->withoutOverlapping && ! $event->mutex->create($event)) {
            return;
        }

        try {
            $event->callBeforeCallbacks(app());
            Artisan::call(substr($event->command, strlen($prefix)));
            $event->callAfterCallbacks(app());
        } finally {
            if ($event->withoutOverlapping) {
                $event->mutex->forget($event);
            }
        }
    }
}
