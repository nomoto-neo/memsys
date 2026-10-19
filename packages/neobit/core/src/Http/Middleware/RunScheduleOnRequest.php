<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Operations\RequestScheduler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * cronの動かないサーバーで、サイトへのアクセスを契機にスケジューラーの予定を動かすミドルウェア。
 * config('neobit.cron_enabled')がfalseのときだけ、Neobit\Core\Operations\RequestSchedulerを呼ぶ。
 *
 * 予定は、応答を返した後（terminate）に動かす。PHP-FPMでは、応答を送り終えてから動くので、
 * アクセスした人を待たせない。どのURLへのアクセスでも動くよう、bootstrap/app.phpで全体の
 * ミドルウェアに足す。
 */
class RunScheduleOnRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** 応答を返した後に、時刻の過ぎた予定を動かす。 */
    public function terminate(Request $request, Response $response): void
    {
        // cronが動くサーバーでは、cronのschedule:runに任せる
        if (config('neobit.cron_enabled')) {
            return;
        }

        // 予定には、キューのワーカーのように長く動くものがある。応答は送った後なので、
        // PHPの時間の上限と、ブラウザが接続を切ったことで止まらないようにする
        set_time_limit(0);
        ignore_user_abort(true);

        // 例外が起きても、ログに残すだけにする。応答は送った後なので、アクセスした人には見せない
        try {
            RequestScheduler::runDueEvents();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
