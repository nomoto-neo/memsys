<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * ログインや確認コードの入力など、認証の失敗回数による試行制限。
 *
 * 失敗したときだけhit()で数え、成功したらclear()でそのアカウントの回数を消す。
 * 正しく入力できている人が制限に掛かることは無い。
 *
 * カウンターは2本持ち、どちらかが上限に達したら止める。
 * - IPアドレスごと：上限20回。1つのIPから多くのアカウントを総当たりする攻撃を止める
 * - アカウントごと：上限5回。IPを変えながら1つのアカウントを狙う攻撃を止める
 * 他人がわざと失敗を重ねると本人も最大60秒ログインできなくなるが、短い時間で済むので、
 * 総当たりへの強さの方を優先している。
 *
 * ■ どの認証かを表すscopeを必ず指定させる理由
 * カウンターはキーの文字列だけで区別される。会員ログインと管理ログインで同じ形のキーを
 * 使うと、失敗回数が合算されてしまう。会員とスタッフのidはどちらも1からの連番なので、
 * idだけでは会員3番とスタッフ3番が同じカウンターになる。そこでキーの頭に必ずscopeを付ける。
 *
 * scopeの値は、制限を掛けるコントローラーがTHROTTLE_SCOPEという定数で持つ。このクラスには
 * 一覧を持たせないので、認証の画面を増やしてもこのクラスは変えずに済む。ほかと重ならない
 * 名前にすること。使われている値は、app/Http/ControllersをTHROTTLE_SCOPEで検索すると分かる。
 *
 * エラーの返し方は画面ごとに違うので、このクラスでは扱わない。isBlocked()で判定し、
 * blockedMessage()で文言だけを受け取って、返し方はコントローラーが決める。
 */
class LoginThrottle
{
    private const MAX_ATTEMPTS_PER_IP = 20;

    private const MAX_ATTEMPTS_PER_ACCOUNT = 5;

    // 最初の失敗から数えて、この秒数が経つとカウンターが消える。
    private const DECAY_SECONDS = 60;

    private string $ipKey;

    private string $accountKey;

    /**
     * @param  string  $scope    呼び出し側のコントローラーのTHROTTLE_SCOPE
     * @param  string  $ip       $request->ip()
     * @param  string|int  $account  メールアドレス・ログインID・idなど、アカウントを区別する値
     */
    public function __construct(string $scope, string $ip, string|int $account)
    {
        $this->ipKey = "{$scope}:ip:{$ip}";

        // 大文字・小文字を変えただけで別のキーにならないよう、小文字にそろえる
        $this->accountKey = "{$scope}:account:".mb_strtolower((string) $account);
    }

    // IPとアカウントのどちらかが上限に達しているか。回数を読むだけで増やさない
    public function isBlocked(): bool
    {
        return RateLimiter::tooManyAttempts($this->ipKey, self::MAX_ATTEMPTS_PER_IP)
            || RateLimiter::tooManyAttempts($this->accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT);
    }

    /**
     * もう一度試せるようになるまでの秒数。上限に達している方のカウンターだけを見る。
     * 達していない方の残り時間まで含めると、実際より長い秒数を案内してしまうため。
     */
    public function availableIn(): int
    {
        $seconds = 0;

        if (RateLimiter::tooManyAttempts($this->ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            $seconds = max($seconds, RateLimiter::availableIn($this->ipKey));
        }

        if (RateLimiter::tooManyAttempts($this->accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT)) {
            $seconds = max($seconds, RateLimiter::availableIn($this->accountKey));
        }

        // 期限ちょうどに「0秒後」と出ないよう、最低1秒にする
        return max(1, $seconds);
    }

    // 画面に出す文言。$whatには「ログイン」「確認コード」のような何の試行かを表す言葉を渡す。
    public function blockedMessage(string $what): string
    {
        return "{$what}の試行回数が多すぎます。{$this->availableIn()}秒後に再試行してください。";
    }

    /**
     * 認証に失敗した回数を1つ数える。回数の期限は最初の失敗から数え、2回目からは延びない。
     * 制限中は照合まで進まずここも呼ばないので、送信を繰り返しても締め出しは延びない。
     */
    public function hit(): void
    {
        RateLimiter::hit($this->ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($this->accountKey, self::DECAY_SECONDS);
    }

    /**
     * 認証に成功したら、そのアカウントの回数を消す。IPアドレスの回数は消さず、期限が来て
     * 消えるのを待つ。成功のたびにIPの回数も消すと、攻撃者が自分のアカウントで時々ログイン
     * するだけで回数を0に戻せてしまい、IPごとのカウンターが役に立たなくなるため。
     */
    public function clear(): void
    {
        RateLimiter::clear($this->accountKey);
    }
}
