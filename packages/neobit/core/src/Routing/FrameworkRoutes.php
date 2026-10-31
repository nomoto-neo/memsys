<?php

namespace Neobit\Core\Routing;

use Closure;
use Illuminate\Support\Facades\Route;
use Neobit\Core\Http\Controllers\AccessRestrictionController;
use Neobit\Core\Http\Controllers\BasicAuthController;
use Neobit\Core\Http\Controllers\CodeTableController;
use Neobit\Core\Http\Controllers\FileWatchController;
use Neobit\Core\Http\Controllers\IpBlockController;
use Neobit\Core\Http\Controllers\OperationLogController;
use Neobit\Core\Http\Controllers\RedirectController;
use Neobit\Core\Http\Controllers\SeoSettingsController;

/**
 * フレームワークの画面のルートを、1行で書くためのクラス。フレームワークの側でURLを足しても、案件のルートを直さなくて済むようにするため。
 *
 * 機能を使うかどうかは案件ごとに決めるので、パッケージの側では読み込まない。案件が、管理者だけが入れるグループの中で、
 * 使う画面のメソッドをURLを渡して呼ぶ。名前の頭は渡したURLから作る（/redirectsならredirects.）。
 * コントローラーは、呼ばれたルートの名前から、組になるほかの名前を作る。
 */
class FrameworkRoutes
{
    /** 項目見出し一覧（DBで管理するコード表 code_tables の編集） */
    public static function codes(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [CodeTableController::class, 'index'])->name('index');
            Route::patch('/', [CodeTableController::class, 'update'])->name('update');
        });
    }

    /** IPブロック管理（アクセスを止めるIPアドレスの一覧と登録） */
    public static function ipBlocks(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [IpBlockController::class, 'index'])->name('index');
            Route::post('/', [IpBlockController::class, 'store'])->name('store');
            Route::patch('/{ipBlock}', [IpBlockController::class, 'update'])->name('update');
            Route::delete('/{ipBlock}', [IpBlockController::class, 'destroy'])->name('destroy');
        });
    }

    /** アクセス制限（サイトと管理画面の、それぞれの制限と開けるIPアドレス） */
    public static function accessRestriction(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [AccessRestrictionController::class, 'index'])->name('index');
            Route::patch('/', [AccessRestrictionController::class, 'update'])->name('update');
        });
    }

    /** ベーシック認証（訪問者画面と管理画面のそれぞれに、掛けるかとユーザー）。{area}はsite（訪問者画面）かadmin（管理画面） */
    public static function basicAuth(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [BasicAuthController::class, 'index'])->name('index');
            Route::patch('/{area}', [BasicAuthController::class, 'update'])->whereIn('area', ['site', 'admin'])->name('update');
            Route::post('/{area}/users', [BasicAuthController::class, 'store'])->whereIn('area', ['site', 'admin'])->name('store');
            Route::delete('/users/{basicAuthUser}', [BasicAuthController::class, 'destroy'])->name('destroy');
        });
    }

    /** 改ざん監視（今の状況と、変更内容の承認） */
    public static function fileWatch(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [FileWatchController::class, 'index'])->name('index');
            Route::post('/approve', [FileWatchController::class, 'approve'])->name('approve');
        });
    }

    /** SEO設定（記事を持たないページのSEOと、サイト全体の既定） */
    public static function seoSettings(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [SeoSettingsController::class, 'index'])->name('index');
            Route::patch('/', [SeoSettingsController::class, 'update'])->name('update');
        });
    }

    /** リダイレクト（古いURLから新しいURLへの移し方の一覧・追加・編集・削除と、CSV） */
    public static function redirects(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [RedirectController::class, 'index'])->name('index');
            Route::post('/', [RedirectController::class, 'store'])->name('store');
            // csvとcsv-importは、{redirect}のURLより先に書く
            Route::get('/csv', [RedirectController::class, 'csv'])->name('csv');
            Route::get('/csv-import', [RedirectController::class, 'csvImport'])->name('csv-import');
            Route::post('/csv-import/confirm', [RedirectController::class, 'csvImportConfirm'])->name('csv-import.confirm');
            Route::post('/csv-import/execute', [RedirectController::class, 'csvImportExecute'])->name('csv-import.execute');
            Route::patch('/{redirect}', [RedirectController::class, 'update'])->name('update');
            Route::delete('/{redirect}', [RedirectController::class, 'destroy'])->name('destroy');
        });
    }

    /** 操作ログの一覧・検索（Neobit\Core\Operations\OperationRecorderが書いた記録を見るだけ） */
    public static function operationLogs(string $url): void
    {
        self::group($url, function () {
            Route::get('/', [OperationLogController::class, 'index'])->name('index');
            Route::post('/', [OperationLogController::class, 'storeSearchCondition'])->name('search');
            Route::get('/csv', [OperationLogController::class, 'csv'])->name('csv');
            // 1日ごとの棒を押したとき。今の検索条件のまま、その1日に絞る
            Route::post('/day', [OperationLogController::class, 'searchDay'])->name('day');
        });
    }

    /** 渡したURLを頭にし、そこから作った名前の頭を付けて、ルートをまとめる */
    private static function group(string $url, Closure $routes): void
    {
        Route::prefix($url)->name(str_replace('/', '.', trim($url, '/')).'.')->group($routes);
    }
}
