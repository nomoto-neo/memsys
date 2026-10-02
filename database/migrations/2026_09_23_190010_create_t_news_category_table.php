<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ニュース記事とカテゴリーの中間テーブル（多対多）。
     *
     * 1件の記事が複数のカテゴリーに属し、1件のカテゴリーにも複数の記事が
     * 属せるようにするための、news_id/category_idの組み合わせだけを持つ
     * 橋渡し役のテーブル。t_members.staff_idと同じく、このプロジェクトでは
     * DBレベルの外部キー制約（constrained()）はあえて付けていない。
     * その代わり、同じ組み合わせが重複登録されないようunique制約だけ
     * 付けている。
     *
     * 自前のid列やタイムスタンプは持たない（news_id・category_idの
     * 組み合わせ自体が1行の意味のすべてで、「いつ紐付けたか」を
     * 記録する必要が無いため）。
     */
    public function up(): void
    {
        Schema::create('t_news_category', function (Blueprint $table) {
            $table->foreignId('news_id');
            $table->foreignId('category_id');
            $table->unique(['news_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_news_category');
    }
};
