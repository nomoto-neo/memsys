<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * ログインが完了した後の移動先。ログインしていない人がログインの必要な画面を開くと、
 * Laravelが開こうとしたURLをセッションのurl.intendedに記録してからログイン画面へ回すので、
 * ログインが完了したら、その画面へ戻す。記録が無ければ、既定の画面（管理画面TOP・マイページ）。
 *
 * ■ 会員と管理画面で、記録が1つしか無い
 * url.intendedは、会員（webガード）と管理画面（adminガード）で同じ1つのキー。そのまま
 * redirect()->intended()を使うと、同じブラウザで両方を使ったときに、会員のログイン後に
 * 管理画面のURLへ（またはその逆へ）飛んでしまう。そこで、記録されたURLがどのルートに
 * 当たるかを調べ、ログインした側の画面（管理画面ならadmin.*のルート、会員ならそれ以外）の
 * ときだけ戻り先にする。管理画面かどうかの見分け方は、bootstrap/app.phpのログイン画面への
 * 振り分け（routeIs('admin.*')）と同じ。
 *
 * 戻り先にしなかった記録は消さずに残す（後でもう一方の側がログインしたときに使えるように）。
 * 使った記録は消す。戻すのはパスや問い合わせの部分だけで、ホスト名は付けない。
 *
 * ■ 呼ぶところ
 * ログインが完了したところ（2段階目が通った、2段階目を省いた、パスキーで通った）で呼ぶ。
 * 途中（パスワードが合って2段階目へ進むとき）では呼ばない（呼ぶと記録が消えるため）。
 */
final class LoginRedirect
{
    // 記録を置くセッションのキー（Laravelが決めているもの）。
    private const INTENDED_SESSION_KEY = 'url.intended';

    // 管理画面のルート名の頭。
    private const ADMIN_ROUTE_PREFIX = 'admin.';

    /**
     * スタッフ（管理画面）のログイン後の移動先。
     */
    public static function forStaff(): string
    {
        return self::intendedUrl(admin: true) ?? route('admin.dashboard');
    }

    /**
     * 会員のログイン後の移動先。
     */
    public static function forMember(): string
    {
        return self::intendedUrl(admin: false) ?? route('mypage');
    }

    /**
     * 記録されたURLが、管理画面（$adminがtrue）または会員側（false）の画面なら、そのURL
     * （パスと問い合わせの部分）を返して記録を消す。そうでなければnullを返し、記録は残す。
     */
    private static function intendedUrl(bool $admin): ?string
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

        if (str_starts_with((string) $route->getName(), self::ADMIN_ROUTE_PREFIX) !== $admin) {
            return null;
        }

        session()->forget(self::INTENDED_SESSION_KEY);

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}
