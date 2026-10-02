<?php

namespace App\Support;

use App\Models\Passkey;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

/**
 * パスキーを持てるモデル（Member・Staff）に付けるトレイト。
 * laravel/passkeysのPasskeyUserインターフェースの実装をまとめている
 * （モデル側は implements PasskeyUser と use HasPasskeys の2つを書く）。
 *
 * パッケージにも同じ役割のPasskeyAuthenticatableトレイトがあるが、
 * そちらはpasskeysテーブルのuser_id列を前提にしているため使わない。
 *
 * ■ passkeys()の戻り値がMorphManyではなくHasManyである理由
 * PasskeyUserインターフェースが、戻り値の型をHasManyと決めている
 * （MorphManyはHasManyの子クラスではないので、この型に合わない）。
 * そこでHasManyで authenticatable_id を結び付け、withAttributes()で
 * authenticatable_type（'member'・'staff'）を足している。withAttributes()は
 * 検索の条件（where）になるのと同時に、このリレーションからcreate()したときに
 * その値を列に入れる。結果として、morphMany()と同じ行を読み書きする。
 *
 * 画面やメールに出す名前（getPasskeyDisplayName()・getPasskeyUsername()）は、
 * モデルごとに変えたい場合、モデル側で同じ名前のメソッドを書けば上書きできる
 * （Staffでは「管理画面：」を付けている）。
 */
trait HasPasskeys
{
    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class, 'authenticatable_id')
            ->withAttributes(['authenticatable_type' => $this->getMorphClass()]);
    }

    public function hasPasskeysEnabled(): bool
    {
        return $this->passkeys()->exists();
    }

    /**
     * WebAuthnの「ユーザーハンドル」。端末の中で、どのアカウントのパスキーかを
     * 区別する値。個人情報を含まず、変わらない値である必要があるので、
     * テーブル名とidから作ったハッシュ値にしている（会員3番とスタッフ3番が
     * 同じ値にならないよう、テーブル名を含める）。パッケージの
     * PasskeyAuthenticatableと同じ作り方。
     */
    public function getPasskeyUserHandle(): string
    {
        return hash_hmac(
            'sha256',
            $this->getTable().'|'.$this->getKey(),
            Config::string('passkeys.user_handle_secret'),
            binary: true,
        );
    }

    /** 端末のパスキー選択画面などに出る表示名。 */
    public function getPasskeyDisplayName(): string
    {
        return (string) $this->getAttribute('name');
    }

    /** 端末のパスキー選択画面などに出るアカウント名。 */
    public function getPasskeyUsername(): string
    {
        return (string) ($this->getAttribute('email') ?? $this->getAuthIdentifier());
    }
}
