<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 集団への招待1件分の記録。招待のメールのリンクから、本人が仲間として登録する。
 * どの集団への招待かは、種類とid（team_type・team_id）で持つ。企業会員の担当者の招待なら、企業。
 * 登録が済んだ招待と、取り消した招待は、行を消す。期限の切れた招待は使われない。
 * 発行・照合・取り消しは、App\Support\TeamInvitationManagerが行う。
 */
class TeamInvitation extends Model
{
    protected $table = 'team_invitations';

    protected $fillable = [
        // 招待のメールの宛先。登録した人のメールアドレスになる
        'email',
        'token_hash',
        'expires_at',
    ];

    /** token_hashは照合にしか使わないので、配列やJSONにしたときに出ないよう隠す */
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /** 招待した先の集団。App\Support\TeamInvitationOwnerを付けたモデル */
    public function team(): MorphTo
    {
        return $this->morphTo();
    }
}
