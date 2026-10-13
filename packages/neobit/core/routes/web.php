<?php

use Illuminate\Support\Facades\Route;
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
