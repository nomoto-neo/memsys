<?php

use Illuminate\Support\Facades\Route;
use Neobit\Core\Http\Controllers\PublicFileController;
use Neobit\Core\Http\Controllers\RobotsController;
use Neobit\Core\Http\Controllers\SitemapController;
use Neobit\Core\Http\Controllers\UploadedFileController;

// ログインした人だけが見られるアップロードファイルと、アップロード直後の一時ファイル
// （Neobit\Core\Http\Controllers\UploadedFileController）。会員・スタッフのどちらのガードでも使い、
// 訪問者のお問い合わせの一時ファイルにも使うので、authのミドルウェアは付けない。
Route::middleware('web')->group(function () {
    Route::get('/uploads/tmp/{filename}', [UploadedFileController::class, 'tmp'])->name('uploads.tmp');
    Route::get('/uploads/{type}/{id}/{field}/{filename}', [UploadedFileController::class, 'show'])
        ->whereNumber('id')
        ->where('field', '[a-z0-9_]+')
        ->name('uploads.show');
});

// 検索エンジンへの案内。XMLのサイトマップと、巡回のしかた（robots.txt）。中身は設定のseoで決める
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('seo.robots');

// どのルートにも当たらないとき、公開ディレクトリのHTMLと古いPHPを返す（Neobit\Core\Http\Controllers\PublicFileController）。
// 古い.phpはフォームをPOSTで受けるので、どのメソッドも受け、webのグループ（セッション・CSRF）は通さない
Route::any('{path}', PublicFileController::class)->where('path', '.*')->fallback()->name('public-files');
