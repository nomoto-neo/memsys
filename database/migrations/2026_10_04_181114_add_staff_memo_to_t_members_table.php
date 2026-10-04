<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_membersに管理メモ（staff_memo）を足す。
 *
 * スタッフが、問い合わせへの対応の経緯などを書き残す欄。管理画面でだけ読み書きし、
 * 会員には見せない（マイページ・履歴書のPDF・会員へのメールには出さない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->text('staff_memo')->nullable()->after('photo_origin');
        });
    }

    public function down(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->dropColumn('staff_memo');
        });
    }
};
