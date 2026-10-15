<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Security\IpBlocker;
use Symfony\Component\HttpFoundation\Response;

/**
 * IPブロック管理で止めているIPアドレスからのアクセスに、何も処理せず404を返すミドルウェア。
 * 止めていないアクセスは、応答を見て、存在しないURLなどへのアクセスを数える（Neobit\Core\Security\IpBlocker）。
 *
 * ルートの無いURL（/.envや/wp-login.phpなど）も止めるので、bootstrap/app.phpで、全体の
 * ミドルウェアに足す。プロキシの後ろでも本当のIPアドレスで判断できるよう、信頼するプロキシ
 * （TrustProxies）より後ろに通す。
 * 403ではなく404にするのは、止められていることを相手に伝えないため。返すのは画面を使わない
 * 短い文字で、セッションもDBも使わないので、大量に叩かれても軽く済む。
 */
class RejectBlockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        // 止めているIPアドレスには、何も処理せず404を返す
        if (IpBlocker::isBlocked((string) $request->ip())) {
            return response('Not Found', Response::HTTP_NOT_FOUND);
        }

        $response = $next($request);

        // 存在しないURLなどへのアクセスを数え、目安を超えたら自動で止める
        IpBlocker::countMiss($request, $response);

        return $response;
    }
}
