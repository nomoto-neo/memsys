<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 企業の担当者（t_company_users）のメールアドレスを、同じ企業の中で重ならないようにする。
 *
 * 確認コードとパスワードの再設定のメールが、本人だけに届くようにするため。
 * 別の企業の担当者と同じアドレスは、使ってよい。2つの企業の担当者を兼ねる人がいるため。
 * メールアドレスが空の担当者は、何人いてもよい。既存のシステムから移した担当者は、
 * 初回のログインで登録する。
 * 既存のシステムのデータに、同じ企業で同じアドレスの担当者がいるときは、持ち込む前に直す。
 * 1人を残して、ほかの人の分を空にしておけば、その人たちは初回のログインで登録する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_company_users', function (Blueprint $table) {
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('t_company_users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
        });
    }
};
