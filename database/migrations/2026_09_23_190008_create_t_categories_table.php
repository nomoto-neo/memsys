<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ニュースカテゴリーのマスタテーブル。
     *
     * display_orderは、一覧画面のドラッグ並び替え（「並び替えて更新」
     * ボタン）で保存する表示順。作成時はCategoryController::store()が
     * 「今ある中でいちばん大きいdisplay_order + 1」を自動採番するので、
     * 新しく作ったカテゴリーは常に一覧の末尾に追加される。
     */
    public function up(): void
    {
        Schema::create('t_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_categories');
    }
};
