<?php

namespace App\Support;

use App\Enums\TwoFactorMethod;
use App\Models\TrustedDevice;
use App\Models\TwoFactorBackupCode;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * ■ テーブルに要る列
 * - totp_secret         認証アプリと共有する秘密鍵。DBには暗号化して保存する
 * - totp_confirmed_at   登録が済んだ日時。秘密鍵があってもここが空の間は、QRコードを
 *                       出しただけで登録はまだ済んでいない
 * 方式がTOTPでないモデルも、列は持っておく。方式の切り替えを、定数だけで済ませるため。
 */
trait HasTwoFactor
{
    /**
     * 秘密鍵の暗号化と、配列やJSONに出さない指定を、モデルごとに書かなくて済むようにする。
     * 書き忘れると秘密鍵が平文で保存されるので、ここで必ず付ける。
     * Eloquentが、モデルを作るたびに呼ぶ。
     */
    public function initializeHasTwoFactor(): void
    {
        $this->mergeCasts([
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
        ]);

        $this->makeHidden('totp_secret');
    }

    /** ログインの2段階目の方式 */
    public static function twoFactorMethod(): TwoFactorMethod
    {
        return static::TWO_FACTOR_METHOD;
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

    /**
     * 認証アプリの登録が済んでいるか。QRコードを読み取り、コードの入力まで済んだら登録済み。
     * 秘密鍵があるだけでは、まだ登録の途中のことがあるので、totp_confirmed_atで判定する。
     */
    public function hasTwoFactorConfirmed(): bool
    {
        return $this->totp_confirmed_at !== null;
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
     */
    public function resetTwoFactor(): void
    {
        DB::transaction(function () {
            $this->totp_secret = null;
            $this->totp_confirmed_at = null;
            $this->save();

            $this->backupCodes()->delete();
            TrustedDeviceManager::for($this)->forgetAll($this);
            $this->passkeys()->delete();
        });
    }
}
