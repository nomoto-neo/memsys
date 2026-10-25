<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;
use Neobit\Core\Seo\Seo;

/**
 * 固定ページの、訪問者の側の表示。/aboutus や /service/price のように、URLの名前（slug）がそのままパスになる。
 *
 * ルートは、routes/web.phpのいちばん最後に置いてある。ほかの全部の画面のURLを先に当てて、
 * どれにも当たらなかった1区切りのURLだけを、ここで受けるため。
 */
class PageController extends Controller
{
    /** 1件の表示（GET /{slug}）。表示にしていないページと、無い名前は404 */
    public function show(string $slug): View
    {
        $page = Page::visible()->where('slug', $slug)->firstOrFail();

        // タイトル・説明文・OGPを、このページから出す（レイアウトのneobit::_seo_head）
        Seo::for($page);

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
