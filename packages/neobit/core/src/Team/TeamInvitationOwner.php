<?php

namespace Neobit\Core\Team;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 招待を受け付ける集団のモデルが持つもの。企業のように、仲間を招待して増やしていく集団に付ける。
 * 招待の発行・照合・取り消しを行うTeamInvitationManagerは、集団をこの型で受け取り、
 * 企業などの特定のモデルを名指ししない。
 * invitations()は、TeamInvitationRelationトレイトを付ければ入る。invitedAccountClass()は、
 * 集団のモデルに書く。
 *
 *     class Company extends Model implements TeamInvitationOwner
 *     {
 *         use TeamInvitationRelation;
 *
 *         public static function invitedAccountClass(): string
 *         {
 *             return CompanyUser::class;
 *         }
 *     }
 */
interface TeamInvitationOwner
{
    /** この集団への招待。期限の切れたものも含む */
    public function invitations(): MorphMany;

    /**
     * 招待から登録される人のモデル。招待のメールのテンプレートと、登録の画面のルートを、
     * このモデルの種類の名前から決める（「種類の名前_invitation」と「種類の名前.invitation.show」）。
     *
     * @return class-string<LoginAccount>
     */
    public static function invitedAccountClass(): string;
}
