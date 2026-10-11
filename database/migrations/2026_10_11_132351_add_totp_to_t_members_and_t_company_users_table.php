<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_membersとt_company_usersに、認証アプリ（TOTP）の列を足す。t_staffsと同じ2つ。
 *
 * - totp_secret：認証アプリと共有する秘密鍵。モデルの側で暗号化して保存する（App\Support\HasTwoFactor）
 * - totp_confirmed_at：登録が済んだ日時。空の間は、まだ登録が済んでいない
 *
 * ログインの2段階目の方式は、モデルの定数で選ぶ（docs/two-factor-spec.md）。方式がTOTPで
 * ないサイトでも、列は標準の構成として持っておく。方式の切り替えを、定数だけで済ませるため。
 */
return new class extends Migration
{
    /** 列を足すテーブル */
    private const TABLES = ['t_members', 't_company_users'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('totp_secret')->nullable();
                $table->timestamp('totp_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['totp_secret', 'totp_confirmed_at']);
            });
        }
    }
};
