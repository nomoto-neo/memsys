<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * ログイン・確認コード入力などの「認証の失敗回数」による試行制限。
 *
 * 失敗したときだけhit()で数え、成功したらclear()でそのアカウントの回数を
 * 消す。成功した操作は回数に含めないので、正しく入力できている人が
 * 制限に掛かることは無い。
 *
 * カウンターは次の2本を同時に持ち、どちらか一方でも上限に達したら止める。
 * - IPアドレス単位（上限20回）：1つのIPから多数のアカウントを総当たりする攻撃を止める
 * - アカウント単位（上限5回）：IPを変えながら1つのアカウントを狙う攻撃を止める
 * アカウント単位のカウンターがあるため、他人のメールアドレス・ログインIDを
 * 知っている第三者がわざと失敗を重ねると、その本人が一時的に（最大60秒）
 * ログインできなくなる。この締め出しは短時間で済むため、総当たりへの
 * 耐性の方を優先している。
 *
 * ■ scope（どの認証か）を必ず指定させる理由
 * RateLimiterのカウンターは、キーの文字列だけで区別される。会員ログインと
 * 管理ログインのように別々の認証で同じ形のキー（例："login:ip:192.0.2.1"）を
 * 使うと、互いの失敗回数が合算されてしまう。また会員のidとスタッフのidは
 * どちらも1から始まる連番なので、idをそのままキーにすると「会員3番」と
 * 「スタッフ3番」が同じカウンターを共有してしまう。キーの組み立てを
 * このクラスに集め、scopeを必ず先頭に付けることで、こうした衝突を防いでいる。
 *
 * scopeの値は、試行制限を掛ける側のコントローラーがTHROTTLE_SCOPEという定数で
 * 持つ（例：private const THROTTLE_SCOPE = 'member-login';）。何に制限を掛けるかは
 * コントローラーごとに決まることなので、このクラスには一覧を持たせない。認証の画面を
 * 増やしても、このクラスは変えずに済む。値は、ほかのコントローラーと重ならない名前に
 * すること（重なると、別々の認証の失敗回数が合算される）。使われている値は、
 * app/Http/ControllersをTHROTTLE_SCOPEで検索すれば一覧できる。
 *
 * エラーの返し方（ValidationExceptionを投げるか、redirect()->withErrors()で
 * 返すか）は画面ごとに違うので、このクラスでは扱わない。isBlocked()で判定し、
 * blockedMessage()で文言だけ受け取って、返し方は呼び出し側のコントローラーが決める。
 */
class LoginThrottle
{
    private const MAX_ATTEMPTS_PER_IP = 20;

    private const MAX_ATTEMPTS_PER_ACCOUNT = 5;

    /** 最初の失敗から数えて、この秒数が経つとカウンターが消える。 */
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

        // メールアドレス・ログインIDは大文字小文字を変えただけで別のキーに
        // ならないよう、小文字に揃えてから使う。
        $this->accountKey = "{$scope}:account:" . mb_strtolower((string) $account);
    }

    /**
     * IP・アカウントのどちらかが上限に達しているか。
     * RateLimiter::tooManyAttempts()は回数を読むだけで、数を増やさない。
     */
    public function isBlocked(): bool
    {
        return RateLimiter::tooManyAttempts($this->ipKey, self::MAX_ATTEMPTS_PER_IP)
            || RateLimiter::tooManyAttempts($this->accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT);
    }

    /**
     * 再試行できるようになるまでの秒数。上限に達している方のカウンターだけを
     * 見る（上限に達していない方の残り時間まで含めると、実際より長い秒数を
     * 案内してしまうため）。
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

        // 期限ちょうどの瞬間に0秒と表示されないよう、最低1秒にしておく。
        return max(1, $seconds);
    }

    /**
     * 画面に出す文言。$whatには「ログイン」「確認コード」のような、
     * 何の試行かを表す言葉を渡す。
     */
    public function blockedMessage(string $what): string
    {
        return "{$what}の試行回数が多すぎます。{$this->availableIn()}秒後に再試行してください。";
    }

    /**
     * 認証に失敗した回数を1つ数える。
     *
     * 回数の期限（DECAY_SECONDS）は、最初の失敗の時点から数える
     * （2回目以降のhit()では期限は延びない）。また、制限中はそもそも照合まで
     * 進まずhit()も呼ばないので、制限中に送信を繰り返しても締め出し時間は
     * 延びない。
     */
    public function hit(): void
    {
        RateLimiter::hit($this->ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($this->accountKey, self::DECAY_SECONDS);
    }

    /**
     * 認証に成功したら、そのアカウントの回数を消す。
     *
     * IPアドレスの回数は消さず、DECAY_SECONDSが経って自然に消えるのを待つ。
     * 成功のたびにIPの回数まで消してしまうと、攻撃者が自分で会員登録した
     * アカウントに時々正しくログインするだけで、IPの回数を何度でも
     * 0に戻せてしまい、「1つのIPから多数のアカウントを総当たりする攻撃を
     * 止める」というIP単位のカウンターの役目が果たせなくなるため。
     */
    public function clear(): void
    {
        RateLimiter::clear($this->accountKey);
    }
}
