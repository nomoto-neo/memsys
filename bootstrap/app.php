<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 非ログイン時の飛び先：管理画面配下(admin.*)だけadmin.loginへ、それ以外はloginへ
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('admin.*')
            ? route('admin.login')
            : route('login'));

        // ログイン済みなのにゲスト専用画面（ログイン画面など）に来たときの飛び先も、
        // 同じ考え方で管理画面配下(admin.*)ならadmin.dashboardへ、それ以外はmypageへ
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('admin.*')
            ? route('admin.dashboard')
            : route('mypage'));

        // 'manager'という短い名前で、ルート定義からEnsureStaffIsManagerを
        // 呼べるようにする登録。routes/web.php側のRoute::middleware('manager')が
        // これを見に行く（詳しくはEnsureStaffIsManager::class参照）。
        $middleware->alias([
            'acl.manager' => \App\Http\Middleware\EnsureStaffIsManager::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
