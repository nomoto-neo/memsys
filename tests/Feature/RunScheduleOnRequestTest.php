<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Neobit\Core\Operations\RequestScheduler;
use Neobit\Core\Operations\TemporaryDataCleaner;
use Tests\TestCase;

/**
 * cronの動かないサーバーで、サイトへのアクセスを契機にスケジューラーの予定を動かす仕組み
 * （Neobit\Core\Operations\RequestScheduler・Neobit\Core\Http\Middleware\RunScheduleOnRequest）と、
 * 片付けを1本だけにするロック（TemporaryDataCleaner::allExclusively()）。
 */
class RunScheduleOnRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cleanup.failed_job_days' => 7]);
    }

    /** 保存期間を過ぎた、送れなかったジョブを1件入れる。片付けが動いたかを、これが消えたかで見る */
    private function oldFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now()->subDays(100),
        ]);
    }

    /** routes/console.phpの予定に、動いた回数を数える1時間ごとの予定を足す。 */
    private function countingHourlyEvent(): void
    {
        app(ConsoleKernel::class)->bootstrap();
        app(Schedule::class)->call(fn () => Cache::increment('test_runs'))->hourly()->name('数える予定');
    }

    public function test_due_event_runs_once_on_the_first_access_after_its_time(): void
    {
        $this->countingHourlyEvent();

        // 初めて見る予定は、動かさずに時刻を覚えるだけ
        $this->travelTo(now()->setTime(10, 30));
        RequestScheduler::runDueEvents();
        $this->assertNull(Cache::get('test_runs'));

        // 予定の時刻（11時）を過ぎるまでは動かない
        $this->travelTo(now()->setTime(10, 45));
        RequestScheduler::runDueEvents();
        $this->assertNull(Cache::get('test_runs'));

        // 11時ちょうどにアクセスが無くても、過ぎて最初のアクセスで動く
        $this->travelTo(now()->setTime(11, 20));
        RequestScheduler::runDueEvents();
        $this->assertSame(1, Cache::get('test_runs'));

        // 12時と13時の2回分たまっていても、動くのは1回だけ
        $this->travelTo(now()->setTime(13, 30));
        RequestScheduler::runDueEvents();
        $this->assertSame(2, Cache::get('test_runs'));
    }

    public function test_event_whose_condition_fails_does_not_run(): void
    {
        app(ConsoleKernel::class)->bootstrap();
        app(Schedule::class)->call(fn () => Cache::increment('test_runs'))->hourly()->when(fn () => false)->name('動かない予定');

        $this->travelTo(now()->setTime(10, 30));
        RequestScheduler::runDueEvents();
        $this->travelTo(now()->setTime(11, 30));
        RequestScheduler::runDueEvents();

        $this->assertNull(Cache::get('test_runs'));
    }

    public function test_scheduled_command_runs_in_the_same_process(): void
    {
        // routes/console.phpの、1時間ごとの一時データの片付け（Schedule::command()）
        $this->oldFailedJob();

        $this->travelTo(now()->setTime(10, 30));
        RequestScheduler::runDueEvents();
        $this->assertSame(1, DB::table('failed_jobs')->count());

        $this->travelTo(now()->setTime(11, 5));
        RequestScheduler::runDueEvents();
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_queued_job_is_processed_on_access(): void
    {
        config(['queue.default' => 'database']);

        // 毎分のキューのワーカーの予定。初めの回は時刻を覚えるだけで、1分たった後のアクセスで送る。
        // ジョブは、時刻を移した後に積む。積んだ時刻より前には送られないため
        $this->travelTo(now()->setTime(10, 30, 10));
        dispatch(fn () => Cache::put('job_done', true));
        RequestScheduler::runDueEvents();
        $this->assertFalse(Cache::has('job_done'));

        $this->travelTo(now()->setTime(10, 31, 20));
        RequestScheduler::runDueEvents();
        $this->assertTrue(Cache::get('job_done'));
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_access_runs_the_schedule_only_without_cron(): void
    {
        // cronが動くサーバーでは、アクセスを契機に何も動かさない
        config(['neobit.cron_enabled' => true]);
        $this->get('/')->assertOk();
        $this->assertFalse(Cache::has('neobit_request_schedule_check'));

        // cronが動かないサーバーでは、応答を返した後に予定を見る
        config(['neobit.cron_enabled' => false]);
        $this->get('/')->assertOk();
        $this->assertTrue(Cache::has('neobit_request_schedule_check'));
    }

    public function test_cleanup_is_skipped_while_another_cleanup_holds_the_lock(): void
    {
        $this->oldFailedJob();

        // ほかの片付けが動いている間は、取りやめてnullを返す
        $counts = Cache::lock('neobit_temporary_data_cleanup', 60)->get(fn () => TemporaryDataCleaner::allExclusively());

        $this->assertNull($counts);
        $this->assertSame(1, DB::table('failed_jobs')->count());

        // ロックが外れれば片付ける
        $this->assertSame(1, TemporaryDataCleaner::allExclusively()['保存期間を過ぎた、送れなかったジョブ']);
    }

    public function test_cleanup_command_skips_while_another_cleanup_holds_the_lock(): void
    {
        $this->oldFailedJob();

        Cache::lock('neobit_temporary_data_cleanup', 60)->get(function () {
            $this->artisan('app:cleanup-temporary-data')
                ->expectsOutput('ほかの片付けが動いているので、今回は取りやめました。')
                ->assertSuccessful();
        });

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }
}
