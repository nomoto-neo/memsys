<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 集団への招待1件分の記録。招待のメールのリンクから、本人が仲間として登録する。
 * どの集団への招待かは、種類とid（team_type・team_id）で持つ。企業会員の担当者の招待なら、企業。
 * 誰が招待したかも、種類とid（inviter_type・inviter_id）で持つ。招待を作ったときに入れ、
 * その後は変えない。送り直せるのは、招待した本人だけ。
 * 登録が済んだ招待と、取り消した招待は、行を消す。期限の切れた招待は使われない。
 * 発行・照合・取り消しは、Neobit\Core\Team\TeamInvitationManagerが行う。
 */
class TeamInvitation extends Model
{
    protected $table = 'team_invitations';

    protected $fillable = [
        // 招待のメールの宛先。登録した人のメールアドレスになる
        'email',
        'token_hash',
        'expires_at',
        'inviter_type',
        'inviter_id',
    ];

    /** token_hashは照合にしか使わないので、配列やJSONにしたときに出ないよう隠す */
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'inviter_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /** 招待した先の集団。Neobit\Core\Team\TeamInvitationOwnerを付けたモデル */
    public function team(): MorphTo
    {
        return $this->morphTo();
    }

    /** 招待した人。仲間か、管理画面のスタッフ。その人がもういなければnull */
    public function inviter(): MorphTo
    {
        return $this->morphTo();
    }

    /** その人が、この招待を送った本人か。送り直せるのは、本人だけ */
    public function isSentBy(?Model $person): bool
    {
        return $person !== null
            && $this->inviter_type === $person->getMorphClass()
            && $this->inviter_id === (int) $person->getKey();
    }

    /**
     * 仲間が送った招待か。招待した人が、招待から登録される人と同じ種類の人のとき。
     * 管理画面のスタッフが送った招待は、仲間の側の画面とメールでは「運営」と出し、氏名を出さない
     */
    public function isSentByPeer(): bool
    {
        $account = $this->team::invitedAccountClass();

        return $this->inviter_type === (new $account())->getMorphClass();
    }
}
