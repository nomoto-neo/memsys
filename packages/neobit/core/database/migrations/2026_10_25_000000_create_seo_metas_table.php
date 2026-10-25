<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 記事ごとのSEOの欄（Neobit\Core\Models\SeoMeta）。どのコーナーの記事でも、このテーブルに1記事1行で持つ。
 * seoable_typeはモデルの短い名前（Relation::enforceMorphMap()）。空欄の項目は、記事から自動で補う（Neobit\Core\Seo\Seo）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 50);
            $table->unsignedBigInteger('seoable_id');
            $table->string('title', 60)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('image')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};
