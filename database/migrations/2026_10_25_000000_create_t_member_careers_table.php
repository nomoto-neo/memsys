<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会員の経歴（学歴・職歴）。1会員に0〜複数の、年・月・内容の組。履歴書のPDFの学歴・職歴の欄に出す。
 *
 * 子テーブル（Neobit\Core\Crud\ChildTables）の、最初に並べる枠の数（initial）の見本。
 * 保存のたびに行を消して画面の順に入れ直す（delete_insert）ので、idの順が画面の順になり、並び順の列は持たない。
 * ほかの子テーブルと同じく、外部キー制約（constrained()）は付けない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_member_careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_member_careers');
    }
};
