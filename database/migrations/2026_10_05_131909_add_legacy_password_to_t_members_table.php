<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_membersに、古い方式のパスワード（legacy_password）を足す。
 *
 * 既存のシステムから移した会員の、古い方式（MD5など）のハッシュ値を、今の方式でもう一度
 * ハッシュ値にしたものを入れる。本人が次にログインしたときに、今の方式のパスワードに置き換えて
 * 空にする（App\Support\LegacyPasswordUserProvider）。新しく登録した会員では、ずっと空。
 *
 * 移した会員は、ログインするまで今の方式のパスワードを持たないので、passwordを空にできるようにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('legacy_password')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->dropColumn('legacy_password');
            $table->string('password')->nullable(false)->change();
        });
    }
};
