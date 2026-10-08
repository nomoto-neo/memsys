<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 固定ページ（t_pages）。会社概要のように、管理画面で本文を書いて、訪問者の側に
 * 決まったURLで出すページ。
 *
 * - slug：URLの名前。aboutusなら /aboutus で開く。ページごとに違う名前にする
 * - body：本文のHTML。WYSIWYGエディタ（SunEditor）で書く。長い文になるので、
 *   textより大きいmediumtextにしている
 * - disp_flg：訪問者の側に出すかどうか
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 100)->unique();
            $table->mediumText('body')->nullable();
            $table->boolean('disp_flg')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_pages');
    }
};
