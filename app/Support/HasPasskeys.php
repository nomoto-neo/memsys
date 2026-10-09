<?php

namespace App\Support;

use App\Models\Passkey;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

/**
 * パスキーを持てるモデルに付けるトレイト。会員とスタッフに付けている。
 * モデルには、implements PasskeyUserとuse HasPasskeysの2つを書く。
 *
 * パッケージにも同じ役割のトレイトがあるが、passkeysテーブルのuser_id列を前提に
 * しているので使わない。
 *
 * passkeys()はパッケージの決まりでHasManyを返す必要があるので、morphMany()は使えない。
 * そこでHasManyでidを結び付け、withAttributes()で持ち主の種類を足している。検索の条件にも
 * 作るときの値にもなるので、morphMany()と同じ行を読み書きできる。
 *
 * 端末に出す名前は、モデルに同じ名前のメソッドを書けば変えられる。スタッフでは
 * 「管理画面：」を付けている。
 */
trait HasPasskeys
{
    /** このアカウントのパスキー。持ち主の種類とidで結び付ける */
    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class, 'authenticatable_id')
            ->withAttributes(['authenticatable_type' => $this->getMorphClass()]);
    }

    /** パスキーを1つでも登録しているか */
    public function hasPasskeysEnabled(): bool
    {
        return $this->passkeys()->exists();
    }

    /**
     * 端末の中でどのアカウントのパスキーかを見分ける、ユーザーハンドルという値。
     * 個人情報を含まず変わらない値にするため、テーブル名とidから作ったハッシュ値にする。
     * テーブル名を含めるのは、会員3番とスタッフ3番を同じ値にしないため。
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

    /** 端末のパスキー選択画面などに出るアカウント名。会員は、ログインに使う値 */
    public function getPasskeyUsername(): string
    {
        if ($this instanceof MemberAccount) {
            return $this->loginId();
        }

        return (string) ($this->getAttribute('email') ?? $this->getAuthIdentifier());
    }
}
