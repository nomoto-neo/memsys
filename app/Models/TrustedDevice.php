<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ログインの2段階目を省ける、信頼済みの端末1台分の記録。会員の「このデバイスを記憶する」と、
 * スタッフの「この端末を信頼する」の両方に使う。
 *
 * 認証の仕組みのためのデータなので、会員とスタッフで1つのテーブルを共通に使い、
 * 誰の端末かは2つの列で表す。判定・発行・取り消しは、App\Support\TrustedDeviceManagerが行う。
 */
class TrustedDevice extends Model
{
    protected $table = 'trusted_devices';

    protected $fillable = [
        'token_hash',
        'expires_at',
    ];

    // token_hashは照合にしか使わないので、配列やJSONにしたときに出ないよう隠す
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // この端末を信頼したアカウント（MemberまたはStaff）。
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
