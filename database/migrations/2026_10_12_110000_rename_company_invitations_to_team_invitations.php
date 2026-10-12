<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 担当者の招待のテーブルを、企業に限らない、集団への招待のテーブルにする。
 *
 * - 名前をt_company_invitationsからteam_invitationsに変える。招待は、認証のための一時的な値で、
 *   業務のデータではないので、trusted_devicesなどと同じく、t_を付けない共通のテーブルにする
 * - どの集団への招待かを、company_idではなく、種類とid（team_type・team_id）で持つ。
 *   trusted_devicesなどのauthenticatable_type・authenticatable_idと同じ持ち方
 * それまでの招待は、企業（種類の名前はcompany）への招待として引き継ぐ。
 * 発行・照合・取り消しは、App\Support\TeamInvitationManagerが行う。
 */
return new class extends Migration
{
    /** それまでの招待の、集団の種類の名前。AppServiceProviderのenforceMorphMap()に書いた、企業の名前 */
    private const COMPANY_TYPE = 'company';

    public function up(): void
    {
        Schema::rename('t_company_invitations', 'team_invitations');

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('team_type')->nullable()->after('id');
            $table->unsignedBigInteger('team_id')->nullable()->after('team_type');
        });

        // それまでの招待を、企業への招待として引き継ぐ
        DB::table('team_invitations')->update([
            'team_type' => self::COMPANY_TYPE,
            'team_id' => DB::raw('company_id'),
        ]);

        Schema::table('team_invitations', function (Blueprint $table) {
            // 索引の名前は、テーブルの名前を変える前のまま残っている
            $table->dropIndex('t_company_invitations_company_id_index');
            $table->dropColumn('company_id');

            $table->string('team_type')->nullable(false)->change();
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
            $table->index(['team_type', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
        });

        // 企業のほかの集団への招待は、元の形では持てないので消す
        DB::table('team_invitations')->where('team_type', '!=', self::COMPANY_TYPE)->delete();
        DB::table('team_invitations')->update(['company_id' => DB::raw('team_id')]);

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropIndex(['team_type', 'team_id']);
            $table->dropColumn(['team_type', 'team_id']);

            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index('company_id', 't_company_invitations_company_id_index');
        });

        Schema::rename('team_invitations', 't_company_invitations');
    }
};
