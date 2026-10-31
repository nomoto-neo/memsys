<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use Neobit\Core\Rules\SlugRule;

/**
 * 固定ページのルート：訪問者向け。/aboutus のように、URLの名前（t_pages.slug）がそのままパスになる。
 * 名前には / を含められる（/service/price）。
 * bootstrap/app.phpが、ほかのどのルートファイルよりも後に読む。どのルートにも当たらなかったURLだけを
 * ここで受けるため。先に読むと、/login や /news より先に当たってしまう。
 * ほかの画面のURLと同じ名前のページは、管理画面の検証で作れないようにしてある（NotReservedPathRule）。
 * 名前の形は、管理画面の検証と同じパターン（SlugRule）。食い違うと、登録できるのに開けないページができる
 */
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', SlugRule::NESTED_PATTERN)
    ->name('pages.show');
