<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_newsに公開範囲（members_only）を足す。falseなら一般公開、trueなら会員限定。
 *
 * 会員限定の記事は、ログインしていない人には一覧にも詳細にも出さない
 * （App\Models\News::visibleTo()参照）。すでにある記事は一般公開にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_news', function (Blueprint $table) {
            $table->boolean('members_only')->default(false)->after('disp_flg');
        });
    }

    public function down(): void
    {
        Schema::table('t_news', function (Blueprint $table) {
            $table->dropColumn('members_only');
        });
    }
};
