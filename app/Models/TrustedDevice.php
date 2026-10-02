<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ログインの2段階目を省略できる「信頼済み端末」1台分の記録
 * （会員の「このデバイスを記憶する」・管理ログインの「この端末を信頼する」）。
 *
 * 業務のデータではなく認証の仕組みを支えるデータなので、会員用・スタッフ用で
 * テーブルを分けず、1つのtrusted_devicesテーブルで扱う。誰の端末かは
 * authenticatable_type（'member'・'staff'）とauthenticatable_id の2列で表す
 * （Eloquentのポリモーフィックリレーション。'member'・'staff'という短い名前は
 * AppServiceProviderのRelation::enforceMorphMap()で決めている）。
 *
 * 判定・発行・取り消しはApp\Support\TrustedDeviceManagerが行う。
 */
class TrustedDevice extends Model
{
    protected $table = 'trusted_devices';

    protected $fillable = [
        'token_hash',
        'expires_at',
    ];

    // token_hashは検証にしか使わない内部値なので、誤って画面や配列出力に
    // 出てしまわないよう隠す。
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** この端末を信頼したアカウント（MemberまたはStaff）。 */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
