<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理画面の「ベーシック認証」のユーザー（Neobit\Core\Models\BasicAuthUser）。訪問者画面と管理画面で、別々のユーザーを持つ。
 * 認証するのはApacheで、追加と削除のたびに、範囲ごとにstorage/app/basic-auth/範囲.htpasswdを書き直す
 * （Neobit\Core\Security\BasicAuth）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basic_auth_users', function (Blueprint $table) {
            $table->id();
            // 掛ける範囲。site（訪問者画面）かadmin（管理画面）
            $table->string('area', 10);
            // ユーザー名。Apacheは大文字と小文字を別物にするので、照合順序は文字をそのまま比べるutf8mb4_bin
            $table->string('username', 50)->collation('utf8mb4_bin');
            // bcryptのハッシュ（$2y$）。.htpasswdにそのまま書く
            $table->string('password');
            $table->timestamps();

            // ユーザー名は範囲の中で一意。同じユーザー名でも、範囲が違えば別のユーザー
            $table->unique(['area', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basic_auth_users');
    }
};
