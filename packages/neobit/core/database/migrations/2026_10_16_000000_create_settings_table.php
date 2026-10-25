<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理画面から変えられる設定（Neobit\Core\Models\Setting）。キーと値の組で、1つの設定が1行。
 * 設定ファイルと違い、サーバーに入らなくても、管理画面から運用する人が変えられる。
 * 今はアクセス制限（Neobit\Core\Security\AccessRestriction）と、SEO設定（Neobit\Core\Seo\SeoSettings）が使う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // 設定の名前。例：access.site_restricted
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
