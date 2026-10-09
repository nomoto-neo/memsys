<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * ログインが完了した後の移動先。ログインの必要な画面を開いた人は、Laravelが開こうとしたURLを
 * セッションのurl.intendedに記録してからログイン画面へ回すので、ログインしたらその画面へ戻す。
 * 記録が無ければ、マイページか管理画面のTOPへ移す。
 *
 * ■ 記録は、会員と管理画面で1つしか無い
 * url.intendedは、どのガードでも同じ1つのキー。redirect()->intended()をそのまま使うと、
 * 同じブラウザで両方を使ったときに、会員のログインの後に管理画面のURLへ飛んでしまう。
 * そこで、記録されたURLがどのガードの画面かを調べ、ログインしたガードの画面のときだけ戻り先にする。
 * どのガードの画面かは、そのルートに付いているauthのミドルウェアで見分ける（auth:adminなら
 * admin、auth:webならweb）。ルートの名前の付け方には頼らない。
 * そのため、ログインの必要なルートには、ガードの名前を省かずに書く（authだけにしない）。
 * authだけのルートは既定のガードとして扱うが、既定のガードは、auth:adminのように名前を書いた
 * ルートを通ると、そのリクエストの間は書き換わる。省かずに書いておけば、取り違えない。
 * 戻り先にしなかった記録は、もう一方の側がログインしたときに使えるよう消さずに残す。
 * 戻すのはパスと問い合わせの部分だけで、ホスト名は付けない。
 *
 * ■ 呼ぶところ
 * ログインが完了したところで呼ぶ。2段階目が通ったとき、2段階目を省いたとき、パスキーで通ったとき。
 * パスワードが合って2段階目へ進むときに呼ぶと、記録が消えてしまうので呼ばない。
 */
final class LoginRedirect
{
    /** 記録を置くセッションのキー。Laravelが決めているもの。 */
    private const INTENDED_SESSION_KEY = 'url.intended';

    /** 管理画面のスタッフのガード。 */
    private const STAFF_GUARD = 'admin';

    /** 管理画面のスタッフのログイン後の移動先。 */
    public static function forStaff(): string
    {
        return self::intendedUrl(self::STAFF_GUARD) ?? route('admin.dashboard');
    }

    /**
     * 会員のログイン後の移動先。開こうとしていた画面が無ければ、その種類のマイページ。
     *
     * @param  class-string<MemberAccount>  $memberClass  ログインした会員のモデル
     */
    public static function forMember(string $memberClass): string
    {
        return self::intendedUrl($memberClass::memberGuard()) ?? route($memberClass::memberRoute('mypage'));
    }

    /**
     * 記録されたURLが、ログインしたガード（$guard）の画面なら、パスと問い合わせの部分を返して
     * 記録を消す。そうでなければnullを返して記録は残す。
     */
    private static function intendedUrl(string $guard): ?string
    {
        $url = session(self::INTENDED_SESSION_KEY);

        if (! is_string($url) || $url === '') {
            return null;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (HttpExceptionInterface) {
            // 今はもう無い画面のURLなど
            return null;
        }

        // そのルートが、どのガードのログインを求めているか。authのミドルウェアから読む。
        // 「auth」だけなら既定のガード、「auth:admin」ならadmin。authが無いルートは戻り先にしない
        $routeGuard = null;
        foreach ($route->gatherMiddleware() as $middleware) {
            if ($middleware === 'auth') {
                $routeGuard = config('auth.defaults.guard');
            } elseif (is_string($middleware) && str_starts_with($middleware, 'auth:')) {
                $routeGuard = substr($middleware, strlen('auth:'));
            }
        }

        if ($routeGuard !== $guard) {
            return null;
        }

        session()->forget(self::INTENDED_SESSION_KEY);

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}
