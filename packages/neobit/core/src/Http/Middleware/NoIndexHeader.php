<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 応答に「X-Robots-Tag: noindex」のヘッダーを付けて、検索エンジンに載せないようにするミドルウェア。
 * 管理画面のルートのグループに付ける。管理画面をrobots.txtに書くと、誰でも読めるrobots.txtから
 * 隠したURLを教えることになるので、こちらで検索に出さないようにする。
 */
class NoIndexHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
