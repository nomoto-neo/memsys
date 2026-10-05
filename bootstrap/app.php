<?php

use App\Http\Middleware\EnsureCompanyIsApproved;
use App\Http\Middleware\EnsureStaffIsManager;
use App\Http\Middleware\NormalizeInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Psr\Log\LogLevel;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 非ログイン時の飛び先：管理画面(admin.*)はadmin.loginへ、企業会員の画面(company.*)は
        // company.loginへ、それ以外はloginへ
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->routeIs('admin.*') => route('admin.login'),
            $request->routeIs('company.*') => route('company.login'),
            default => route('login'),
        });

        // ログイン済みなのにゲスト専用画面（ログイン画面など）に来たときの飛び先も、同じ考え方で分ける
        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->routeIs('admin.*') => route('admin.dashboard'),
            $request->routeIs('company.*') => route('company.mypage'),
            default => route('mypage'),
        });

        // 入力された文字の、全角と半角の揺らぎをそろえる（App\Support\InputNormalizer）。
        // 画面のルートの全部に掛ける。コントローラーの定数を見て、そろえない項目を決めるので、
        // 全体のミドルウェアではなく、ルートが決まった後に通るwebのグループに足す
        $middleware->web(append: [NormalizeInput::class]);

        // 'manager'という短い名前で、ルート定義からEnsureStaffIsManagerを
        // 呼べるようにする登録。routes/web.php側のRoute::middleware('manager')が
        // これを見に行く（詳しくはEnsureStaffIsManager::class参照）。
        $middleware->alias([
            'acl.manager' => EnsureStaffIsManager::class,
            // 企業会員の画面を、承認済みの企業の担当者だけに使わせる
            'company.approved' => EnsureCompanyIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 処理されなかった例外は、処理が止まった障害としてcriticalで記録する。
        // Log::error()で書く運用の支障と分けて、エラーの通知の範囲を選べるようにするため
        $exceptions->level(Throwable::class, LogLevel::CRITICAL);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
