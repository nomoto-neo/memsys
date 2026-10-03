<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 2段階認証のバックアップコード1件分の記録。1人につき、決まった数のコードを持つ。
 *
 * 信頼済みの端末と同じく、認証の仕組みのためのデータなので、t_の付かない共通のテーブルにし、
 * 誰のコードかは2つの列で表す。今これを使っているのはスタッフだけ。
 * 使ったコードは、used_atに日時を入れて使用済みにする。
 */
class TwoFactorBackupCode extends Model
{
    protected $table = 'two_factor_backup_codes';

    protected $fillable = [
        'code_hash',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    // このコードを持つアカウント（今はStaffのみ）。
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
