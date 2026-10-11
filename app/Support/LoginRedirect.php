<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * ログインが完了した後の移動先。ログインの必要な画面を開いてログイン画面へ回された人を、
 * ログインの後で、開こうとしていた画面へ戻す。開こうとしていた画面が無ければ、マイページか
 * 管理画面のTOPへ移す。
 *
 * ■ 戻り先は、ログインの種類（ガード）ごとに別々に覚える
 * Laravel標準のredirect()->intended()は、戻り先を1つしか覚えない。複数の種類のログイン画面を
 * 同じブラウザで使うと、会員のログインの後に管理画面のURLへ飛ぶ、ということが起きる。
 * そこで、戻り先をガードごとのセッションのキーに覚え、ログインしたガードの分だけを使う。
 * ほかのガードの戻り先には触らないので、それぞれのログインの後に、それぞれの画面へ戻る。
 *
 * ■ どのガードの画面か
 * 開こうとした画面のルートに付いているauthのミドルウェアで見分ける（auth:adminならadmin、
 * auth:webならweb）。ルートの名前の付け方には頼らない。
 * そのため、ログインの必要なルートには、ガードの名前を省かずに書く（authだけにしない）。
 * authだけのルートは既定のガードとして扱うが、既定のガードは、auth:adminのように名前を書いた
 * ルートを通ると、そのリクエストの間は書き換わる。省かずに書いておけば、取り違えない。
 *
 * ■ 呼ぶところ
 * - remember()：未ログインの人をログイン画面へ回すところ（bootstrap/app.phpのredirectGuestsTo）
 * - forStaff()・forMember()：ログインが完了したところ。2段階目が通ったとき、2段階目を省いたとき、
 *   パスキーで通ったとき。パスワードが合って2段階目へ進むときに呼ぶと、戻り先が消えてしまうので
 *   呼ばない
 */
final class LoginRedirect
{
    /** 戻り先を置くセッションのキーの頭。後ろにガードの名前が付く。例：login_intended.admin */
    private const SESSION_KEY_PREFIX = 'login_intended.';

    /** 管理画面のスタッフのガード。 */
    private const STAFF_GUARD = 'admin';

    /**
     * 未ログインの人が開こうとした画面を、その画面のガードの戻り先として覚える。
     * 覚えるのは、画面を開くリクエスト（GET）だけ。フォームの送信先へは戻せないため。
     * 覚えるのはパスと問い合わせの部分だけで、ホスト名は持たない。
     */
    public static function remember(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->route() === null) {
            return;
        }

        // そのルートが、どのガードのログインを求めているか。authのミドルウェアから読む。
        // 「auth」だけなら既定のガード、「auth:admin」ならadmin
        $guard = null;
        foreach ($request->route()->gatherMiddleware() as $middleware) {
            if ($middleware === 'auth') {
                $guard = config('auth.defaults.guard');
            } elseif (is_string($middleware) && str_starts_with($middleware, 'auth:')) {
                $guard = substr($middleware, strlen('auth:'));
            }
        }

        if ($guard === null) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        $query = $request->getQueryString();

        $request->session()->put(self::SESSION_KEY_PREFIX.$guard, $query ? $path.'?'.$query : $path);
    }

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

    /** そのガードの戻り先を取り出す。1回使ったら消す。無ければnull */
    private static function intendedUrl(string $guard): ?string
    {
        $url = session()->pull(self::SESSION_KEY_PREFIX.$guard);

        return is_string($url) && $url !== '' ? $url : null;
    }
}
