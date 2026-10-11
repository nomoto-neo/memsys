<?php

namespace App\Support;

use App\Enums\TwoFactorMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * その人の2段階目の方式で、本人かどうかを確かめる。ログインの2段階目と、ログイン中に本人を
 * もう一度確かめる操作（パスキーの登録の前、バックアップコードの作り直し、認証アプリの解除）で使う。
 * 設計は docs/two-factor-spec.md。
 *
 * 確かめ方は、モデルの定数 TWO_FACTOR_METHOD で決まる。
 * - Email  メールで送った確認コード。先にsend()で送っておく
 * - Totp   認証アプリのコード。バックアップコードはverifyBackupCode()
 * - None   パスワードの再入力。ログインの2段階目には使わない（2段階目そのものを求めないため）
 *
 * 呼ぶ側は方式で場合分けをしなくてよい。画面の出し分けだけ、method()を見る。
 *
 *     $challenge = new TwoFactorChallenge($account);
 *     $challenge->send($request, TwoFactorChallenge::PURPOSE_LOGIN);
 *     $challenge->verify($request, TwoFactorChallenge::PURPOSE_LOGIN, $code);
 */
final class TwoFactorChallenge
{
    /**
     * 使い道。メールの確認コードは、使い道ごとに別のコードになる。ある使い道で送ったコードを、
     * 別の画面で使い回せないようにするため（App\Support\MemberVerificationCode）
     */
    public const PURPOSE_LOGIN = MemberVerificationCode::PURPOSE_LOGIN;

    public const PURPOSE_PASSKEY = MemberVerificationCode::PURPOSE_PASSKEY;

    public function __construct(private readonly LoginAccount $account)
    {
    }

    /** この人の方式 */
    public function method(): TwoFactorMethod
    {
        return $this->account::twoFactorMethod();
    }

    /**
     * 照合の失敗を数える試行制限。種類と人ごとに1本で、どの画面から入力しても同じカウンターになる。
     * 当てようとしている秘密が同じだから。認証アプリのコードとバックアップコードも分けない。
     * 片方ずつ上限まで試せる、ということが無いようにするため。
     */
    public function throttle(Request $request): LoginThrottle
    {
        return new LoginThrottle($this->account::loginType().'-two-factor', $request->ip(), $this->account->getKey());
    }

    /**
     * 確かめるための準備。メールの方式なら、確認コードを作って送る。ほかの方式は何もしない。
     * 送れなかったときはfalseを返す。
     */
    public function send(Request $request, string $purpose): bool
    {
        if ($this->method() !== TwoFactorMethod::Email) {
            return true;
        }

        return (new MemberVerificationCode($this->account::class))->issue($request, $this->account, $purpose);
    }

    /** メールの方式で、まだ有効な確認コードを送ってあるか。ほかの方式では、いつもfalse */
    public function hasSent(Request $request, string $purpose): bool
    {
        return $this->method() === TwoFactorMethod::Email
            && (new MemberVerificationCode($this->account::class))->hasPending($request, $purpose, $this->account);
    }

    /** 入力された値を、その人の方式で照合する */
    public function verify(Request $request, string $purpose, string $input): bool
    {
        // メール：送った確認コード。コードを送った相手が、この人かも確かめる
        if ($this->method() === TwoFactorMethod::Email) {
            $verified = (new MemberVerificationCode($this->account::class))->verify($request, $purpose, $input);

            return $verified !== null && $verified->is($this->account);
        }

        // 認証アプリ：登録が済んでいる人の、今のコード
        if ($this->method() === TwoFactorMethod::Totp) {
            return $this->account->hasTwoFactorConfirmed()
                && (new TwoFactorAuthenticator())->verifyCode($this->account->totpSecret(), $input);
        }

        // なし：パスワードの再入力
        return Hash::check($input, (string) $this->account->getAuthPassword());
    }

    /**
     * バックアップコードを照合する。通ったコードは使用済みになる。
     * 認証アプリの方式で、登録が済んでいる人だけが使える。
     */
    public function verifyBackupCode(string $code): bool
    {
        return $this->method() === TwoFactorMethod::Totp
            && $this->account->hasTwoFactorConfirmed()
            && (new BackupCodeGenerator())->verifyAndConsume($this->account, $code);
    }
}
