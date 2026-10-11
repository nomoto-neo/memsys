<?php

namespace App\Support;

use App\Enums\TwoFactorMethod;
use App\Models\TrustedDevice;
use App\Models\TwoFactorBackupCode;
use App\Models\TwoFactorSecret;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ログインするモデルの、2段階認証にかかわる中身。LoginAccountを実装するモデルにuseする。
 * 設計は docs/two-factor-spec.md。
 *
 * ■ モデルに書くもの
 * - 定数 TWO_FACTOR_METHOD   ログインの2段階目の方式。App\Enums\TwoFactorMethodの値
 * 既定の値は無い。書いていないモデルでログインするとエラーになる。書き忘れで
 * 「2段階目なし」になるのを防ぐため。
 *
 * ■ 認証アプリの登録の置き場所
 * 認証アプリの秘密鍵と登録日時は、モデルのテーブルではなく、会員とスタッフで共通の
 * two_factor_secrets に持つ（App\Models\TwoFactorSecret）。バックアップコードや信頼済みの端末と同じ。
 * 業務のテーブルに、認証の仕組みのためだけの列を置かないため。方式の切り替えも、定数だけで済む。
 * 登録が済んだ人にだけ行があり、解除すると行が消える。
 */
trait HasTwoFactor
{
    /**
     * テストで方式を差し替えるための入れ物。モデルの定数は実行中に変えられないので、
     * テストだけがここに入れる。ふだんはnullで、定数が使われる
     */
    private static ?TwoFactorMethod $fakeTwoFactorMethod = null;

    /** ログインの2段階目の方式 */
    public static function twoFactorMethod(): TwoFactorMethod
    {
        return self::$fakeTwoFactorMethod ?? static::TWO_FACTOR_METHOD;
    }

    /** 方式が認証アプリ（TOTP）か。画面の入口の出し分けに使う */
    public static function usesTotp(): bool
    {
        return static::twoFactorMethod() === TwoFactorMethod::Totp;
    }

    /**
     * テストの間だけ、このモデルの方式を差し替える。nullを渡すと元に戻る。
     * テストの後始末で、必ずnullに戻す（tests/Support/FakesTwoFactorMethod）。
     */
    public static function fakeTwoFactorMethod(?TwoFactorMethod $method): void
    {
        self::$fakeTwoFactorMethod = $method;
    }

    /** 2段階認証のバックアップコード */
    public function backupCodes(): MorphMany
    {
        return $this->morphMany(TwoFactorBackupCode::class, 'authenticatable');
    }

    /** 2段階目を省けるように記憶した端末。判定はApp\Support\TrustedDeviceManagerが行う */
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    /** 認証アプリの登録。登録が済んだ人にだけある */
    public function twoFactorSecret(): MorphOne
    {
        return $this->morphOne(TwoFactorSecret::class, 'authenticatable');
    }

    /**
     * 認証アプリの登録が済んでいるか。QRコードを読み取り、コードの入力まで済んだら登録済み。
     * 登録の途中の秘密鍵はセッションに持つだけなので、行があれば登録済み。
     */
    public function hasTwoFactorConfirmed(): bool
    {
        return $this->twoFactorSecret !== null;
    }

    /** 認証アプリの登録が済んだ日時。未登録ならnull */
    public function twoFactorConfirmedAt(): ?Carbon
    {
        return $this->twoFactorSecret?->confirmed_at;
    }

    /** 認証アプリと共有している秘密鍵。未登録ならnull */
    public function totpSecret(): ?string
    {
        return $this->twoFactorSecret?->secret;
    }

    /**
     * 認証アプリを登録する。QRコードを読み取った認証アプリが、正しいコードを出せたと確かめた後に呼ぶ。
     * 登録し直したときは、前の秘密鍵を上書きする。
     */
    public function registerTwoFactor(string $secret): void
    {
        $this->twoFactorSecret()->updateOrCreate([], [
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);

        // 読み込み済みの古い登録を捨てて、次に聞かれたときに今の登録を読み直す
        $this->unsetRelation('twoFactorSecret');
    }

    /** 使っていないバックアップコードの残りの数。詳細画面の案内に使う */
    public function unusedBackupCodesCount(): int
    {
        return $this->backupCodes()->whereNull('used_at')->count();
    }

    /**
     * 認証アプリの登録を解除する。バックアップコード・信頼済み端末・パスキーも無効にする。
     * パスキーでのログインは2段階目を求めないので、残すと登録し直さないまま使えてしまうため。
     * 次のログインで、QRコードの登録からやり直しになる。
     *
     * アカウントを消すときの片付けにも使う。ログインのための記録は、どれもこのモデルのテーブルの
     * 外にあり、外部キー制約も無いので、消す前にここで消しておく。
     */
    public function resetTwoFactor(): void
    {
        DB::transaction(function () {
            $this->twoFactorSecret()->delete();
            $this->unsetRelation('twoFactorSecret');

            $this->backupCodes()->delete();
            TrustedDeviceManager::for($this)->forgetAll($this);
            $this->passkeys()->delete();
        });
    }
}
