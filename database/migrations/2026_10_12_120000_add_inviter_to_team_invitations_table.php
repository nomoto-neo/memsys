<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 集団への招待（team_invitations）に、招待した人を足す。
 *
 * 身に覚えのない招待があったときに、誰が送ったかを確かめられるようにするため。
 * 招待した人は、種類とid（inviter_type・inviter_id）で持つ。仲間（企業の担当者など）のことも、
 * 管理画面のスタッフのこともある。招待を作ったときに入れ、その後は変えない。
 * 送り直せるのは、招待した本人だけ。
 * それまでの招待は、招待した人が分からないので、空のままにする。
 * 入れるのは、App\Support\TeamInvitationManager。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('inviter_type')->nullable()->after('team_id');
            $table->unsignedBigInteger('inviter_id')->nullable()->after('inviter_type');
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn(['inviter_type', 'inviter_id']);
        });
    }
};
