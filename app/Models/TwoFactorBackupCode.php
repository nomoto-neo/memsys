<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 2段階認証（TOTP）のバックアップコード1件＝1レコード。1つのアカウントにつき
 * BackupCodeGenerator::CODE_COUNT件を持つ。
 *
 * 信頼済み端末（TrustedDevice）と同じく、認証の仕組みを支えるデータなので
 * t_の付かない汎用のテーブル（two_factor_backup_codes）にし、誰のコードかは
 * authenticatable_type・authenticatable_id の2列で表す。今2段階認証を
 * 使っているのはスタッフ（'staff'）だけ。
 *
 * 使用済みかどうかはused_atの有無で判定する（複数件のうち個別の1件だけを
 * 「使用済み」にする用途なので、論理削除（SoftDeletes）ではなく専用の
 * カラムにしている）。
 *
 * code_hashは$hiddenにしているが、そもそもこのモデルを画面へそのまま
 * 渡す場面は無い想定（一致確認はBackupCodeGenerator::verifyAndConsume()の
 * 中で完結する）。
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

    /** このコードを持つアカウント（今はStaffのみ）。 */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
