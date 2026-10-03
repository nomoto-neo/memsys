<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_newsに掲載期間（publish_start_at・publish_end_at）を足す。どちらも任意で、
 * 空なら、その側の制限は無い。
 *
 * 掲載期間の外の記事は、訪問者側の一覧・詳細・画像に出さない（App\Models\News::visibleTo()）。
 * 決まった時刻に何かを実行するのではなく、表示するたびに今の時刻と比べるので、
 * スケジューラーは要らない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_news', function (Blueprint $table) {
            $table->dateTime('publish_start_at')->nullable()->after('members_only');
            $table->dateTime('publish_end_at')->nullable()->after('publish_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('t_news', function (Blueprint $table) {
            $table->dropColumn(['publish_start_at', 'publish_end_at']);
        });
    }
};
