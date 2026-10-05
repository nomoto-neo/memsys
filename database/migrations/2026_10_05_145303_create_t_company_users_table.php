<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 企業会員の担当者（App\Models\CompanyUser）。企業に属し、ログインする。
 * 2段階目の確認コードは、担当者のメールアドレスに送る。設計は docs/member-types-spec.md。
 *
 * - login_id        担当者ID。その企業の中でだけ重ならない。ログイン画面では、企業IDと一緒に入力する
 * - name・email     既存のシステムから移した企業の最初の担当者では、初回のログインで登録するまで空
 * - password        既存のシステムから移した担当者では、ログインするまで空
 * - legacy_password 既存のシステムの古い方式のパスワードを、今の方式で包んだもの
 *                   （App\Support\LegacyPasswordUserProvider）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_company_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('login_id', 50);
            $table->string('name')->nullable();
            // 同じアドレスを、複数の担当者が使っていてよい（企業の代表アドレスなど）
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('legacy_password')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // 担当者IDは、企業の中でだけ重ならない
            $table->unique(['company_id', 'login_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_company_users');
    }
};
