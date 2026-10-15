<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Security\AccessRestriction;
use Symfony\Component\HttpFoundation\Response;

/**
 * サイト（訪問者画面）を、決めたIPアドレスからだけ開けるようにするミドルウェア。公開前のデモの運用と、
 * メンテナンスの間に使う。制限を掛けるか・通すIPアドレスは、管理画面の「アクセス制限」で決める
 * （Neobit\Core\Security\AccessRestriction）。
 *
 * 入ってよいIPアドレスのほかから開かれたら、メンテナンス中の画面
 * （neobit::maintenance）を503で返す。503にするのは、一時的に止めていることを
 * 検索エンジンに伝えて、登録を消されないようにするため。
 * 画面はDBもセッションも使わない1枚のHTMLなので、DBを止めている間も出せる。
 * ルートが無いURLでも同じ画面を出すので、bootstrap/app.phpで、全体のミドルウェアに足している。
 *
 * 制限の間も通すパスは、bootstrap/app.phpがexcept()で渡す。
 * Apacheが直接返すファイル（public/build/・public/storage/）は、ここを通らないので止まらない。
 * コマンドも通らないので、スケジューラーとキューは、制限の間も動く。
 */
class RestrictSiteAccess
{
    /** メンテナンス中の画面のビュー */
    private const VIEW = 'neobit::maintenance';

    // 制限の間も、IPアドレスに関係なく通すパス。「mail/unsubscribe」「admin/*」の形
    /** @var string[] */
    private static array $except = [];

    /**
     * 制限の間も通すパスを決める。bootstrap/app.phpから呼ぶ。
     *
     * @param  string[]  $paths
     */
    public static function except(array $paths): void
    {
        self::$except = $paths;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::$except) || AccessRestriction::allowsSite($request)) {
            return $next($request);
        }

        return response()->view(self::VIEW, [], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
