<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Http\PublicFiles;
use Neobit\Core\Seo\RedirectResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理画面の「リダイレクト」で登録した古いURLを、新しいURLへ移すミドルウェア。当て方はNeobit\Core\Seo\RedirectResolver。
 * 登録に当たらず、URLの末尾が/なら、/を外したURLへ301で移す。
 *
 * ルートの無い古いURL（今は404になるもの）にも当てるので、bootstrap/app.phpで、全体のミドルウェアに足す。
 * 末尾の/を外す働きは、Laravelの決まりではpublic/.htaccessが持つ。そこで外すと、末尾が/の古いURLを
 * 登録しても当たらないので、.htaccessからは消してここで受け持つ。
 * 移すのはGETとHEADだけ。フォームの送信を移すと、送った中身が消えるため。
 * 設定のseo.redirectsがfalseなら、登録は見ずに、末尾の/を外す働きだけをする。キャッシュの置き場所がDBのとき、
 * 一覧を読むだけでも毎回DBを引くので、使わないサイトでは読まないようにするため。
 */
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        // 登録した古いURL。%の符号を戻した形（日本語のまま）で比べる
        if (config('neobit.seo.redirects')) {
            $redirect = RedirectResolver::find(rawurldecode($request->getPathInfo()), (string) $request->getQueryString());
            if ($redirect !== null) {
                RedirectResolver::hit($redirect['id']);

                // サイトの中のパスは、自分で絶対のURLにする。redirect()->to()は末尾の/を外すため
                $location = RedirectResolver::encodeLocation($redirect['location']);
                if (str_starts_with($location, '/')) {
                    $location = $request->getSchemeAndHttpHost().$request->getBaseUrl().$location;
                }

                return redirect()->away($location, $redirect['status_code']);
            }
        }

        // 末尾の/を外したURLへ移す。トップページの/と、公開ディレクトリの実在するディレクトリのURLは外さない。
        // ディレクトリのHTMLの中の相対のリンクは、末尾の/があって正しく解けるため（Neobit\Core\Http\PublicFiles）
        $path = $request->getPathInfo();
        if ($path !== '/' && str_ends_with($path, '/') && ! PublicFiles::isDirectory(rawurldecode($path))) {
            $query = $request->getQueryString();

            return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().rtrim($path, '/').($query !== null ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
