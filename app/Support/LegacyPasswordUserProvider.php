<?php

namespace App\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * 会員のパスワードの照合。既存のシステムから移した会員の、古い方式のパスワードを受け付け、
 * 通ったときに今の方式へ置き換える。それ以外は、Laravelの標準の照合と同じ。
 * config/auth.phpの会員のプロバイダーで、driverをeloquent-legacyにして使う
 * （登録はAppServiceProvider）。
 *
 * ■ 移すとき
 * 既存のシステムのハッシュ値（MD5など）を、wrap()で今の方式（bcrypt）でもう一度ハッシュ値にして、
 * legacy_passwordの列に入れる。passwordの列は空にしておく。古い方式のハッシュ値は短い時間で
 * 破られるので、新しいシステムのDBには、そのままでは置かない。
 *
 * ■ ログインのとき
 * passwordが空で、legacy_passwordに値がある会員は、古い方式で照合する。
 * config('members.種類の名前.legacy_passwords')に書いたクラスを、書いた順に試す。それぞれで、
 * 入力されたパスワードを古い方式のハッシュ値にし、それをlegacy_passwordと今の方式で照合する。
 * どれかで通ったら、入力されたパスワードを今の方式でpasswordに保存し、legacy_passwordを消す。
 * 次からは、標準の照合になる。
 *
 * ここに入れているのは、パスワードを確かめる所の全部に、1か所で効かせるためである。
 * ログインのコントローラーには、何も書かない。
 *
 * ■ legacy_passwordが残っている会員
 * まだ一度もログインしていない会員である。パスワードを変えるか再設定すると、legacy_passwordは
 * 消える（App\Support\PasswordChange）。
 */
final class LegacyPasswordUserProvider extends EloquentUserProvider
{
    /**
     * 既存のシステムのハッシュ値を、DBに置ける形にする。データを移すときに、1件ずつ呼ぶ。
     * 今の方式（bcrypt）は、わざと時間がかかる。件数が多いときは、取り込みとは別に進める
     */
    public static function wrap(string $legacyHash): string
    {
        return Hash::make($legacyHash);
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        // 今の方式のパスワードを持っている人と、会員でない人は、標準の照合
        if (! $user instanceof MemberAccount || ! empty($user->getAuthPassword()) || empty($user->legacy_password)) {
            return parent::validateCredentials($user, $credentials);
        }

        if (! is_string($plain) || $plain === '') {
            return false;
        }

        // 古い方式を、設定に書いた順に試す
        foreach ((array) config('members.'.$user::memberType().'.legacy_passwords', []) as $class) {
            if (! $this->hasher->check((new $class())->hash($plain, $user), $user->legacy_password)) {
                continue;
            }

            // 通った。今の方式に置き換えて、古い方式の値を消す
            $user->forceFill([
                'password' => $this->hasher->make($plain),
                'legacy_password' => null,
            ])->save();

            return true;
        }

        return false;
    }
}
