<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2段階認証の認証アプリ（TOTP）の登録のテーブル。
 *
 * two_factor_backup_codes・trusted_devicesと同じく、認証の仕組みを支えるデータなので、t_を付けない
 * 共通のテーブルにし、誰の登録かは authenticatable_type・authenticatable_id の2列で表す。
 * 1人につき1行で、認証アプリの登録が済んだ人にだけ行がある。登録を解除したら行を消す。
 *
 * - secret：認証アプリと共有する秘密鍵。モデルの側で暗号化して保存する（App\Models\TwoFactorSecret）
 * - confirmed_at：登録が済んだ日時
 *
 * 秘密鍵は、それまで業務のテーブル（t_staffs・t_members・t_company_users）の列に持っていた。
 * 認証の仕組みのためだけの列を業務のテーブルに置かないよう、このテーブルへ移し、元の列は消す。
 * それまでの登録は移さない。登録済みだった人は、次のログインでQRコードから登録し直す。
 */
return new class extends Migration
{
    /** 認証アプリの列を持っていた業務のテーブル */
    private const OLD_TABLES = ['t_staffs', 't_members', 't_company_users'];

    public function up(): void
    {
        Schema::create('two_factor_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->text('secret');
            $table->timestamp('confirmed_at');
            $table->timestamps();

            // 1人につき1行。同じ人の行が2つできないようにする
            $table->unique(['authenticatable_type', 'authenticatable_id'], 'two_factor_secrets_authenticatable_unique');
        });

        // 業務のテーブルから、認証アプリの列を消す
        foreach (self::OLD_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['totp_secret', 'totp_confirmed_at']);
            });
        }
    }

    public function down(): void
    {
        // 列は作り直すが、登録は戻さない
        foreach (self::OLD_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('totp_secret')->nullable();
                $table->timestamp('totp_confirmed_at')->nullable();
            });
        }

        Schema::dropIfExists('two_factor_secrets');
    }
};
