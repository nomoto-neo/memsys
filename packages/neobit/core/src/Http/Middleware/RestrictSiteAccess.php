<?php

namespace Neobit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Neobit\Core\Security\Gate;
use Neobit\Core\Security\GateState;
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
 * 制限の間も通すパスは、bootstrap/app.phpがexcept()で渡す。管理画面（設定のneobit.admin_pathの下）は、
 * 管理画面の制限（RestrictAdminAccess）だけを掛けるので、渡さなくてもいつも通す。
 * Apacheが直接返すファイル（public/build/・public/storage/）は、ここを通らないので止まらない。
 * コマンドも通らないので、スケジューラーとキューは、制限の間も動く。
 *
 * 判断は、入口の見張りと同じもの（Neobit\Core\Security\Gate）を使う。見張りが掛かっていない環境や、
 * 見張りの状態のファイルが古いときの保険になる。
 */
class RestrictSiteAccess
{
    /** メンテナンス中の画面のビュー。入口の見張りが返す画面も、このビューから作る（GateState） */
    public const VIEW = 'neobit::maintenance';

    // 制限の間も、IPアドレスに関係なく通すパス。「mail/unsubscribe」「up」の形
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

    /**
     * 制限の間も通すパスの全部。except()で渡したものに、管理画面の下を足す。入口の見張りにも書き出す（GateState）。
     *
     * @return string[]
     */
    public static function exceptPaths(): array
    {
        $admin = GateState::adminPath();

        return [...self::$except, $admin, $admin.'/*'];
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::opensSite(GateState::build(), $request->decodedPath(), (string) $request->ip())) {
            return $next($request);
        }

        return response()->view(self::VIEW, [], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
