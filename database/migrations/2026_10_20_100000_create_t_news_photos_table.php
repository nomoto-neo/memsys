<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ニュース記事の写真。1記事に0〜複数の、画像とキャプションの組。
 *
 * 繰り返しの組（Neobit\Core\Crud\RepeatableGroup）の見本。行のidを保って更新するので、
 * 添付ファイル（t_news_attachments）と違い、行のidはほかから参照してもよい。
 * imageは保存したファイル名、image_originは表示名で、ファイルは記事の保存先（news/000/000012/）に置く。
 * display_orderは画面の並びの順の番号。
 *
 * ほかの子テーブルと同じく、外部キー制約（constrained()）は付けない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_news_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->index();
            $table->string('image')->nullable();
            $table->string('image_origin')->nullable();
            $table->string('caption', 200)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_news_photos');
    }
};
