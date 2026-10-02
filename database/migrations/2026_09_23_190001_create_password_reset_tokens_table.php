<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel標準のパスワードリセット用テーブル（Laravelの初期スキャフォールドに
 * 含まれるもの）。
 *
 * 会員のパスワード再設定は、このテーブルを使うリンク方式ではなく、
 * メールで送る確認コードをセッションで完結させる方式
 * （App\Support\MemberVerificationCode）なので、現時点では
 * どのモデル・どのコントローラーからも参照していない。
 * Laravel標準のテーブルとして残してある。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
