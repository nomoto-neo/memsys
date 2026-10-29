<?php

namespace Neobit\Core\Http\Controllers;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Neobit\Core\Http\PublicFiles;
use Neobit\Core\Http\PublicScriptResponse;
use Symfony\Component\HttpFoundation\Request as BaseRequest;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * 公開ディレクトリのHTMLと古いPHPを返すフォールバックのルート。どのルートにも当たらないときにだけ通る。
 * 探し方と返さないものは、Neobit\Core\Http\PublicFilesにある。
 * webのグループ（セッション・CSRFの確かめ・入力をそろえる処理）は通さない。古い.phpは自分でセッションを使い、
 * フォームをPOSTで受けるため。
 */
class PublicFileController
{
    public function __invoke(Request $request): BaseResponse
    {
        $path = rawurldecode($request->getPathInfo());

        // 管理画面のURLの下は、Laravelのルートだけ
        $admin = trim((string) config('neobit.admin_path'), '/');
        $relative = trim($path, '/');
        if ($admin !== '' && ($relative === $admin || str_starts_with($relative, $admin.'/'))) {
            abort(404);
        }

        // ディレクトリなのに末尾の/が無ければ、/を付けたURLへ移す。ページの中の相対のリンクを正しく解くため
        if (! str_ends_with($path, '/') && PublicFiles::isDirectory($path)) {
            $query = $request->getQueryString();

            return redirect()->away($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().'/'.($query !== null ? '?'.$query : ''), 301);
        }

        $file = PublicFiles::file($path);
        if ($file === null) {
            abort(404);
        }

        // 古い.phpは、入口のindex.phpが動かす
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            return new PublicScriptResponse($file);
        }

        // HTMLは中身をそのまま返す。Content-Typeにcharsetを足さない。Shift_JISのページも、ページのmetaのとおりに読ませるため
        $response = new class((string) file_get_contents($file)) extends Response
        {
            public function prepare(BaseRequest $request): static
            {
                parent::prepare($request);
                $this->headers->set('Content-Type', 'text/html');

                return $this;
            }
        };

        // 変わっていなければ304を返す
        $response->setLastModified(new DateTime('@'.filemtime($file)));
        $response->isNotModified($request);

        return $response;
    }
}
