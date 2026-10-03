<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * 訪問者向けのTOPページ。
 *
 * お知らせの最新記事を、絞り込みや並び替え無しで数件だけ出す。全部の記事は
 * お知らせのコーナー（NewsController）で見てもらう（ページの「もっと見る」）。
 */
class TopController extends Controller
{
    // TOPページに出すお知らせの件数。
    private const NEWS_COUNT = 5;

    // TOPページ
    public function index(): View
    {
        // その人に見せてよい記事（会員限定の記事はログイン中の会員にだけ）を、
        // お知らせのコーナーと同じ並び順（記事日付の新しい順）で決まった件数だけ取る
        $newsList = News::visibleTo(Auth::guard('web')->user())
            ->orderByDesc('article_date')
            ->orderByDesc('id')
            ->limit(self::NEWS_COUNT)
            ->get();

        return view('welcome', [
            'newsList' => $newsList,
        ]);
    }
}
