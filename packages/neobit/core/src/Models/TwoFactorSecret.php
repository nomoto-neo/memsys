<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 2段階認証の認証アプリ（TOTP）の登録。1人につき1行で、登録が済んだ人にだけ行がある。
 *
 * バックアップコードや信頼済みの端末と同じく、認証の仕組みのためのデータなので、t_の付かない
 * 共通のテーブルにし、誰の登録かは2つの列で表す。業務のテーブルには、認証アプリの列を持たせない。
 * 読み書きは、ログインするモデルのNeobit\Core\Auth\HasTwoFactorを通して行う。
 */
class TwoFactorSecret extends Model
{
    protected $table = 'two_factor_secrets';

    protected $fillable = [
        'secret',
        'confirmed_at',
    ];

    /** 配列やJSONにしたときに出さない項目。秘密鍵を、うっかり出さないように */
    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        // 認証アプリと共有する秘密鍵。DBには暗号化して保存する
        'secret' => 'encrypted',
        'confirmed_at' => 'datetime',
    ];

    /** この登録の持ち主 */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
