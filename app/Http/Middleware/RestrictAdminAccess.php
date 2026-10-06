<?php

namespace App\Http\Middleware;

use App\Support\AllowedIps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理画面を、.envのADMIN_ALLOWED_IPSに書いたIPアドレスからだけ開けるようにするミドルウェア。
 * 事務所などの決まった場所からしか使わないサイトで使う。書いていなければ、制限しない。
 *
 * 入ってよいIPアドレスのほかから開かれたら、ログイン画面も含めて404を返す。
 * 403にしないのは、そこに管理画面があることを知らせないため。
 * 攻撃で行が増え続けないよう、止めたアクセスは操作ログに残さない。
 * routes/web.phpで、管理画面のルートのグループに、admin.ipの名前で掛けている。
 * サイト全体の制限（RestrictSiteAccess）もあるときは、両方で入ってよいIPアドレスだけが開ける。
 */
class RestrictAdminAccess
{
    // 入ってよいIPアドレスの一覧のある設定の名前
    private const CONFIG_KEY = 'app.admin_allowed_ips';

    public function handle(Request $request, Closure $next): Response
    {
        if (! AllowedIps::allows(self::CONFIG_KEY, $request)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
