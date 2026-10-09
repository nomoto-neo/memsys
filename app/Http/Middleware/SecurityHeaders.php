<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ブラウザに守り方を伝えるヘッダーを、全部の応答に付けるミドルウェア。
 *
 * ほかのサイトへの埋め込みを使った攻撃（クリックジャッキング）などを、ブラウザの側で防がせる。
 * エラーの画面やファイルのダウンロードにも付けるので、bootstrap/app.phpで、
 * 全体のミドルウェアに足している。
 *
 * HTTPSだけで開かせる指定（Strict-Transport-Security）は、HTTPSで届いたリクエストにだけ返す。
 * HTTPで動かす手元の開発環境では出ないので、環境ごとの切り替えは要らない。
 */
class SecurityHeaders
{
    /** どの応答にも付けるヘッダー */
    private const HEADERS = [
        // ほかのサイトの<iframe>の中に、このサイトの画面を出させない
        'X-Frame-Options' => 'SAMEORIGIN',
        // ファイルの種類を、ブラウザに中身から決めつけさせない
        'X-Content-Type-Options' => 'nosniff',
        // ほかのサイトへ移るときは、開いていたURLのうちドメインまでしか渡さない
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * HTTPSで届いたときだけ付けるヘッダー。ブラウザはこの秒数（1年）の間、このドメインを
     * HTTPSだけで開く。サブドメインは、HTTPで動かしているものがあると開けなくなるので含めない
     */
    private const HSTS = 'max-age=31536000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        // HTTPSかどうかは、信頼するプロキシを置いていれば、そのプロキシのヘッダーから決まる
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
