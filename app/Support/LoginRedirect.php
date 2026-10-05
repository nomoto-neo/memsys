<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * ログインが完了した後の移動先。ログインの必要な画面を開いた人は、Laravelが開こうとしたURLを
 * セッションのurl.intendedに記録してからログイン画面へ回すので、ログインしたらその画面へ戻す。
 * 記録が無ければマイページか管理画面のTOPへ移す。
 *
 * ■ 会員と管理画面で記録が1つしか無い
 * url.intendedは会員と管理画面で同じ1つのキー。redirect()->intended()をそのまま使うと、
 * 同じブラウザで両方を使ったときに、会員のログインの後に管理画面のURLへ飛んでしまう。
 * そこで記録されたURLがどのルートかを調べ、ログインした側の画面のときだけ戻り先にする。
 * 管理画面はadmin.*のルートで、bootstrap/app.phpのログイン画面への振り分けと同じ見分け方。
 * 戻り先にしなかった記録は、もう一方の側がログインしたときに使えるよう消さずに残す。
 * 戻すのはパスと問い合わせの部分だけで、ホスト名は付けない。
 *
 * ■ 呼ぶところ
 * ログインが完了したところで呼ぶ。2段階目が通ったとき、2段階目を省いたとき、パスキーで通ったとき。
 * パスワードが合って2段階目へ進むときに呼ぶと、記録が消えてしまうので呼ばない。
 */
final class LoginRedirect
{
    // 記録を置くセッションのキー。Laravelが決めているもの。
    private const INTENDED_SESSION_KEY = 'url.intended';

    // 管理画面のルート名の頭。
    private const ADMIN_ROUTE_PREFIX = 'admin.';

    // 管理画面のスタッフのログイン後の移動先。
    public static function forStaff(): string
    {
        return self::intendedUrl(admin: true) ?? route('admin.dashboard');
    }

    /**
     * 会員のログイン後の移動先。開こうとしていた画面が無ければ、その種類のマイページ。
     *
     * @param  class-string<MemberAccount>  $memberClass  ログインした会員のモデル
     */
    public static function forMember(string $memberClass): string
    {
        return self::intendedUrl(admin: false) ?? route($memberClass::memberRoute('mypage'));
    }

    // 記録されたURLがログインした側の画面なら、パスと問い合わせの部分を返して記録を消す。
    // そうでなければnullを返して記録は残す。$adminがtrueなら管理画面、falseなら会員の側。
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
