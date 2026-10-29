<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Security\GateState;
use Neobit\Core\Security\IpBlocker;
use Symfony\Component\HttpFoundation\Response;

/**
 * IPブロック管理で止めているIPアドレスからのアクセスに、何も処理せず404を返すミドルウェア。
 * 止めていないアクセスは、応答を見て、存在しないURLなどへのアクセスを数える（Neobit\Core\Security\IpBlocker）。
 *
 * ルートの無いURL（/.envや/wp-login.phpなど）も止めるので、bootstrap/app.phpで、全体の
 * ミドルウェアに足す。プロキシの後ろでも本当のIPアドレスで判断できるよう、信頼するプロキシ
 * （TrustProxies）より後ろに通す。
 * 403ではなく404にし、画面もルートの無いURLを開いたときと同じ404の画面にする。止められていることを
 * 相手に気付かせないため。ルートの無いURLへの404と同じ重さで、DBは使わない。
 *
 * 入口の見張り（Neobit\Core\Security\Gate）が先に同じ判断をするので、ここは見張りが掛かっていないときの保険になる。
 * 見張りの状態のファイルが無いか、.envを変えて古くなっていれば、ここで書き出し直す（GateState::refreshIfStale()）。
 */
class RejectBlockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        // 入口の見張りの状態が無いか古ければ、書き出し直す
        GateState::refreshIfStale();

        // 止めているIPアドレスには、何も処理せず、ふつうの404の画面を返す
        if (IpBlocker::isBlocked((string) $request->ip())) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $response = $next($request);

        // 存在しないURLなどへのアクセスを数え、目安を超えたら自動で止める
        IpBlocker::countMiss($request, $response);

        return $response;
    }
}
