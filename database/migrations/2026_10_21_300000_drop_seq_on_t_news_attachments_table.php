<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 添付ファイルの並び順の列（seq）を消す。
 * 添付ファイルの組は、保存のたびに行を消して画面の順に入れ直す（delete_insert）ので、idの順が画面の順になり、
 * 並び順の列が要らないため。列が無い環境では何もしない。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('t_news_attachments', 'seq')) {
            return;
        }

        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->dropColumn('seq');
        });
    }

    public function down(): void
    {
        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->unsignedInteger('seq')->default(0)->after('news_id');
        });

        // 記事ごとのidの順を、並び順の番号にする
        $order = [];
        foreach (DB::table('t_news_attachments')->orderBy('news_id')->orderBy('id')->get(['id', 'news_id']) as $row) {
            $order[$row->news_id] = ($order[$row->news_id] ?? -1) + 1;
            DB::table('t_news_attachments')->where('id', $row->id)->update(['seq' => $order[$row->news_id]]);
        }
    }
};
