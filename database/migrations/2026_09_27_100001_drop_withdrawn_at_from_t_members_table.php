<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_members.withdrawn_at（退会日時）を削除する。
 *
 * 退会は会員の行を物理削除する方式（MypageController::destroy()）なので、
 * 退会日時を行に記録する場面が無い。退会の記録は、氏名・メールアドレスを
 * 含まない形でログ（storage/logs/member-日付.log）に残す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->dropColumn('withdrawn_at');
        });
    }

    public function down(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->timestamp('withdrawn_at')->nullable()->after('birthdate');
        });
    }
};
