<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ニュースの添付ファイル（t_news_attachments）を、繰り返しの組（Neobit\Core\Crud\RepeatableGroup）の形に揃える。
 *
 * アップロードの欄の決まりは「名前」（保存したファイル名）と「名前_origin」（表示名）なので、
 * filenameをfile、original_nameをfile_originに変える。組の行の順を画面の順で持つよう、seqを足す。
 * fileはNULLを許す。
 * 今の行の順（idの順）を、そのままseqに入れる。ファイルの置き場所と名前は変わらない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->renameColumn('filename', 'file');
            $table->renameColumn('original_name', 'file_origin');
            $table->unsignedInteger('seq')->default(0)->after('news_id');
        });

        // 組の行は、一時ファイルが片付けで消えていたときなどにファイルの無い行になりうるので、NULLを許す（写真と同じ）
        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->string('file')->nullable()->change();
        });

        // 今の行の順（記事ごとのidの順）を、並び順の番号にする
        $order = [];
        foreach (DB::table('t_news_attachments')->orderBy('news_id')->orderBy('id')->get(['id', 'news_id']) as $row) {
            $order[$row->news_id] = ($order[$row->news_id] ?? -1) + 1;
            DB::table('t_news_attachments')->where('id', $row->id)->update(['seq' => $order[$row->news_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->string('file')->nullable(false)->change();
        });

        Schema::table('t_news_attachments', function (Blueprint $table) {
            $table->dropColumn('seq');
            $table->renameColumn('file', 'filename');
            $table->renameColumn('file_origin', 'original_name');
        });
    }
};
