<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ニュース記事に複数付けられる添付ファイル。
     *
     * list_imageが「1記事につき1枚」の単純な列なのに対し、こちらは
     * 「1記事につき0〜複数」を持てる子テーブル（1対多。1つの添付
     * ファイルが複数の記事に属することは無い）。
     *
     * filenameに保存するのはファイル名だけで、保存先ディレクトリは
     * list_imageと同じ規則(news/{news_id}/attach/)で組み立てる。
     * 保存のたびに「このnews_idの行を全削除してから、残す分だけ
     * 作り直す」という運用にしているため、display_orderのような
     * 並び順専用カラムは持たせていない(再登録した順=INSERTした順=
     * idの昇順が、そのまま画面上の並び順になる)。
     *
     * t_members.staff_idやt_news_categoryと同じく、このプロジェクトの
     * 慣例に沿ってDBレベルの外部キー制約(constrained())はあえて
     * 付けていない。
     */
    public function up(): void
    {
        Schema::create('t_news_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id');
            $table->string('filename');
            $table->string('original_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_news_attachments');
    }
};
