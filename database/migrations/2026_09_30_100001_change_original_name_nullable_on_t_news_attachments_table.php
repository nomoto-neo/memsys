<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 添付ファイルの表示名（元のファイル名）を、空（NULL）でも保存できるようにする。
 *
 * 表示名が分からないとき（CSV取り込みで表示名の列が空欄など）は、ランダムな
 * 保存ファイル名を代わりに入れても意味が無いので、空のままにする。
 * 表示する側は、空なら「添付ファイル1」のような文字を出す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        // 空の行が残っていると元に戻せないので、先に保存ファイル名を入れておく
        DB::table('t_news_attachments')->whereNull('original_name')->update(['original_name' => DB::raw('filename')]);

        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->string('original_name')->nullable(false)->change();
        });
    }
};
