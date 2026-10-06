<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_membersに、お知らせメールを受け取るかどうか（notice_mail）を足す。
 *
 * 値はApp\Enums\NoticeMail（1＝受け取る、0＝受け取らない）。既定は「受け取る」なので、
 * 今いる会員は全員「受け取る」になる。一覧の抽出の条件に使うので、索引を付ける。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->unsignedTinyInteger('notice_mail')->default(1)->after('prefecture')->index();
        });
    }

    public function down(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->dropColumn('notice_mail');
        });
    }
};
