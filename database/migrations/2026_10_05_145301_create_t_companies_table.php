<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 企業会員の企業（App\Models\Company）。企業の情報だけを持ち、ログインはしない。
 * ログインするのは、企業に属する担当者（t_company_users）。設計は docs/member-types-spec.md。
 *
 * - code       企業ID。ログイン画面で入力する。新しく登録した企業にはidと同じ番号を入れ、
 *              既存のシステムから移した企業には今までのログインIDを入れるので、文字の列にしている
 * - status     申請中・承認済み・停止（App\Enums\CompanyStatus）。承認済みの企業だけがログインできる
 * - prefecture 都道府県コード（code/prefectures.csvのキー）
 * - staff_memo スタッフが書き残す管理メモ。企業の側には見せない
 * - staff_id   管理画面から最後に更新したスタッフのid
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_companies', function (Blueprint $table) {
            $table->id();
            // 登録の直後は空で、idが決まってから入れる
            $table->string('code', 50)->nullable()->unique();
            $table->string('name');
            $table->string('kana')->nullable();
            $table->string('representative')->nullable();
            $table->string('zip', 8)->nullable();
            $table->unsignedTinyInteger('prefecture')->nullable();
            $table->string('address')->nullable();
            $table->string('tel', 20);
            $table->string('url')->nullable();
            $table->string('status', 20)->index();
            $table->text('staff_memo')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_companies');
    }
};
