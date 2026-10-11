<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

/**
 * ログインした人に、パスキーの登録を1回だけ勧める。パスキーは入口が目立たないと使われないので、
 * パスワードでログインした直後に、画面の上のメッセージ欄で案内する。
 *
 * ■ 出す条件
 * - パスキーを使うサイトである。その人のパスキーの一覧画面のルートがある。ルートは、ルートの一覧から
 *   探す（passkeyRoute()）
 * - その人が、パスキーを1つも持っていない
 * - このブラウザで、まだ案内を見せていない
 * パスキーでログインした人には出さない。呼ぶのがパスワードでのログインの確定のときだけなので、
 * 自然にそうなる（App\Support\TwoFactorLogin）。
 *
 * ■ 「見せた」の覚え方
 * DBには何も持たず、ブラウザのCookieで覚える。Cookieには、その人のパスワードと認証アプリの
 * 登録日時から作った照合用の値を入れる。パスワードを変えたときと、認証アプリを登録し直したときは
 * 値が変わるので、次のログインでもう1回案内が出る。どちらのときもパスキーが消されているので、
 * 登録し直してもらうのにちょうどよい。
 * 覚える単位はブラウザごとなので、別の端末では、そちらでも1回出る。パスキーを1つでも登録すれば、
 * 以後はどの端末でも出ない。
 *
 * ■ 案内の出し方
 * 次に開く画面で1回だけ出すよう、一覧画面のルート名をセッションに仮置きする。出すのは
 * レイアウトのメッセージ欄（resources/views/_passkey_suggest.blade.php）。仮置きした時点で
 * 「見せた」と数える。バックアップコードの画面では出さず、その次の画面へ持ち越す（hold()）。
 */
final class PasskeySuggestion
{
    /** 案内を出す印として、パスキーの一覧画面のルート名を仮置きするセッションのキー */
    public const SESSION_KEY = 'passkeySuggest';

    /** Cookieを残す日数。5年。見せた印を、長く覚えておくため */
    private const COOKIE_DAYS = 365 * 5;

    /** パスワードでのログインが確定したところで呼ぶ。条件に合えば、次の画面に案内を出す。 */
    public static function afterLogin(Request $request, LoginAccount $account): void
    {
        $passkeyRoute = self::passkeyRoute($account::loginGuard());

        // パスキーを使わないサイトか、もうパスキーを持っている人には出さない
        if ($passkeyRoute === null || $account->passkeys()->exists()) {
            return;
        }

        // このブラウザで、今のパスワードと認証アプリの登録になってから、もう見せたか
        $cookieName = $account::loginType().'_passkey_suggested';
        $fingerprint = self::fingerprint($account);

        if ($request->cookie($cookieName) === $fingerprint) {
            return;
        }

        $request->session()->flash(self::SESSION_KEY, $passkeyRoute);

        // 有効期間は「分」単位。引数に名前を付けて渡さないこと（App\Support\TrustedDeviceManager::remember()参照）
        Cookie::queue($cookieName, $fingerprint, self::COOKIE_DAYS * 24 * 60);
    }

    /**
     * 案内を、この画面では出さずに、次の画面へ持ち越す。バックアップコードを1回だけ見せる画面で呼ぶ。
     * 控えてもらうことに集中してほしい画面なので、案内はその次に出す。
     * 画面には、戻り値をholdPasskeySuggestとして渡す。レイアウトが、それを見て案内を出さない。
     */
    public static function hold(Request $request): bool
    {
        $request->session()->keep([self::SESSION_KEY]);

        return true;
    }

    /**
     * そのガードの人の、パスキーの一覧画面のルート名。無ければnull。
     *
     * ルートの一覧から、パスキーの一覧（App\Support\PasskeyManagement::passkeyIndex()）を開くルートのうち、
     * そのガードでのログインを求めているものを探す。ルート名は、パスキーを扱うコントローラーの設定に
     * 書いてある。同じ名前を2か所に持たないよう、ここで探す。
     * パスキーを使わないサイトでは、ルートが無いのでnullになる。
     * どのガードの画面かは、ルートに付いているauthのミドルウェアで見分ける（auth:adminならadmin）。
     * App\Support\LoginRedirectと同じ見分け方。
     */
    private static function passkeyRoute(string $guard): ?string
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->getActionMethod() === 'passkeyIndex'
                && in_array('auth:'.$guard, $route->gatherMiddleware(), true)) {
                return $route->getName();
            }
        }

        return null;
    }

    /**
     * Cookieに入れる照合用の値。誰の、どのパスワードと認証アプリの登録のときに見せたかを表す。
     * パスワードのハッシュ値から作るので、そのままは入れず、アプリの鍵で署名した値にする。
     */
    private static function fingerprint(LoginAccount $account): string
    {
        $source = implode('|', [
            $account->getMorphClass(),
            $account->getKey(),
            $account->getAuthPassword(),
            $account->totp_confirmed_at?->timestamp,
        ]);

        return hash_hmac('sha256', $source, (string) config('app.key'));
    }
}
