<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Passkeys\Passkey as PackagePasskey;

/**
 * パスキー1件分の記録。ログインするモデル（会員・スタッフなど）で、1つのpasskeysテーブルを共通に使う。
 *
 * パスキーのパッケージのモデルを継承し、持ち主の表し方だけを変えている。パッケージは
 * user_id列で1種類のユーザーを指すが、このモデルは2つの列で、どのモデルかを指す。
 * パッケージにこのモデルを使わせる設定は、AppServiceProviderで行っている。
 */
class Passkey extends PackagePasskey
{
    protected $table = 'passkeys';

    /**
     * このパスキーの持ち主（ログインするモデル）。パッケージが$passkey->userで参照するので、
     * 名前はuserのままにしている。論理削除したスタッフのパスキーではnullになり、
     * ログインに使えない。
     */
    public function user(): MorphTo
    {
        return $this->morphTo(name: 'authenticatable');
    }

    /** $ownerのパスキーかどうか。$ownerは、パスキーを持てるログインのモデル（HasPasskeysを使うもの） */
    public function belongsToOwner(Model $owner): bool
    {
        return $this->authenticatable_type === $owner->getMorphClass()
            && (int) $this->authenticatable_id === (int) $owner->getKey();
    }
}
