<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Security\AccessRestriction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理画面を、決めたIPアドレスからだけ開けるようにするミドルウェア。事務所などの決まった場所からしか
 * 使わないサイトで使う。管理画面のIPアドレスは、管理画面の「アクセス制限」と.envのADMIN_ALLOWED_IPSで
 * 決める（Neobit\Core\Security\AccessRestriction）。1つも無ければ、制限しない。
 *
 * 入ってよいIPアドレスのほかから開かれたら、ログイン画面も含めて404を返す。
 * 403にしないのは、そこに管理画面があることを知らせないため。
 * 攻撃で行が増え続けないよう、止めたアクセスは操作ログに残さない。
 * routes/web.phpで、管理画面のルートのグループに、admin.ipの名前で掛けている。
 * サイトの制限（RestrictSiteAccess）とは互いに影響しない。管理画面の下のURLは、サイトの制限から外してある。
 */
class RestrictAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AccessRestriction::allowsAdmin($request)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
