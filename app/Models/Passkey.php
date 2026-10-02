<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Passkeys\Passkey as PackagePasskey;

/**
 * パスキー1件分の記録（会員・スタッフ共通のpasskeysテーブル）。
 *
 * laravel/passkeysパッケージの処理（登録・照合のActionクラス）は、
 * パッケージのPasskeyモデルを受け取り・返す作りになっているので、
 * それを継承して、持ち主の表し方だけを変えている。
 * - パッケージ：user_id列で、1種類のユーザーモデルを指す
 * - このモデル：authenticatable_type・authenticatable_idの2列で、
 *   MemberまたはStaffを指す（trusted_devicesと同じ）
 *
 * パッケージにこのモデルを使わせる設定は、AppServiceProviderの
 * Passkeys::usePasskeyModel()で行っている。
 *
 * 認証器の名前（$passkey->authenticator。例："Google Password Manager"）は、
 * パッケージのモデルが、credentialに入っているAAGUIDから引いて返す。
 */
class Passkey extends PackagePasskey
{
    protected $table = 'passkeys';

    /**
     * このパスキーの持ち主（MemberまたはStaff）。
     *
     * パッケージの処理が$passkey->userで持ち主を参照するので、名前はuserのまま
     * にしている。論理削除したスタッフのパスキーでは、Staffモデルの
     * 「削除済みを除く」条件が掛かってnullになる（＝ログインに使えない）。
     */
    public function user(): MorphTo
    {
        return $this->morphTo(name: 'authenticatable');
    }

    /** $ownerのパスキーかどうか。 */
    public function belongsToOwner(Member|Staff $owner): bool
    {
        return $this->authenticatable_type === $owner->getMorphClass()
            && (int) $this->authenticatable_id === (int) $owner->getKey();
    }
}
