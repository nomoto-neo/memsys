<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Neobit\Core\Http\Controllers\PublicFileController;
use Neobit\Core\Seo\Seo;
use Symfony\Component\HttpFoundation\Response;

/**
 * 固定ページの、訪問者の側の表示。/aboutus や /service/price のように、URLの名前（slug）がそのままパスになる。
 *
 * ルートは、routes/web.phpのいちばん最後に置いてある。ほかの全部の画面のURLを先に当てて、
 * どれにも当たらなかった1区切りのURLだけを、ここで受けるため。
 * 固定ページが無ければ、公開ディレクトリのHTMLと古いPHP（Neobit\Core\Http\Controllers\PublicFileController）を探す。
 * このルートが受けるURLは、フレームワークのフォールバックのルートまで届かないため。
 */
class PageController extends Controller
{
    /** 1件の表示（GET /{slug}）。表示にしていないページと、無い名前は、公開ディレクトリのファイルを探し、それも無ければ404 */
    public function show(Request $request, string $slug): View|Response
    {
        $page = Page::visible()->where('slug', $slug)->first();
        if ($page === null) {
            return app(PublicFileController::class)($request);
        }

        // タイトル・説明文・OGPを、このページから出す（レイアウトのneobit::_seo_head）
        Seo::for($page);

        // 入れ子のslug（company/history）なら、公開している親のページをパンくずの段にする。
        // 親は1回でまとめて探し、非公開や無いページの段は飛ばす
        $segments = explode('/', $slug);
        $parentSlugs = [];
        for ($depth = 1; $depth < count($segments); $depth++) {
            $parentSlugs[] = implode('/', array_slice($segments, 0, $depth));
        }
        if ($parentSlugs !== []) {
            $parents = Page::visible()->whereIn('slug', $parentSlugs)->get()->keyBy('slug');
            foreach ($parentSlugs as $parentSlug) {
                if ($parents->has($parentSlug)) {
                    Seo::breadcrumb($parents[$parentSlug]->title, route('pages.show', $parentSlug));
                }
            }
        }

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
