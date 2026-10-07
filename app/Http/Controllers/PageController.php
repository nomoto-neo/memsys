<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * 固定ページの、訪問者の側の表示。/aboutus のように、URLの名前（slug）がそのままパスになる。
 *
 * ルートは、routes/web.phpのいちばん最後に置いてある。ほかの全部の画面のURLを先に当てて、
 * どれにも当たらなかった1区切りのURLだけを、ここで受けるため。
 */
class PageController extends Controller
{
    // 1件の表示（GET /{slug}）。表示にしていないページと、無い名前は404
    public function show(string $slug): View
    {
        return view('pages.show', [
            'page' => Page::visible()->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
