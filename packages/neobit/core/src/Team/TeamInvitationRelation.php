<?php

namespace Neobit\Core\Team;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Neobit\Core\Models\TeamInvitation;

/**
 * 招待を受け付ける集団のモデルに付ける、招待の関連。TeamInvitationOwnerのinvitations()が入る。
 * 招待は、どの集団のものかを、種類とid（team_type・team_id）で持つ。
 */
trait TeamInvitationRelation
{
    /** この集団への招待。期限の切れたものも含む（Neobit\Core\Team\TeamInvitationManager） */
    public function invitations(): MorphMany
    {
        return $this->morphMany(TeamInvitation::class, 'team');
    }
}
