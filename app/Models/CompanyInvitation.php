<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 企業会員の担当者の招待1件分の記録。招待のメールのリンクから、本人が担当者として登録する。
 * 登録が済んだ招待と、取り消した招待は、行を消す。期限の切れた招待は使われない。
 * 発行・照合・取り消しは、App\Support\CompanyInvitationManagerが行う。
 */
class CompanyInvitation extends Model
{
    protected $table = 't_company_invitations';

    protected $fillable = [
        'company_id',
        // 招待のメールの宛先。登録した担当者のメールアドレスになる
        'email',
        'token_hash',
        'expires_at',
    ];

    /** token_hashは照合にしか使わないので、配列やJSONにしたときに出ないよう隠す */
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /** 招待した先の企業 */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
