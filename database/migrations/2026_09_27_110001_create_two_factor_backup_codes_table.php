<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2段階認証（TOTP）のバックアップコードのテーブル。
 *
 * trusted_devicesと同じく、認証の仕組みを支えるデータなので、t_を付けない
 * 汎用のテーブルにし、誰のコードかは authenticatable_type・
 * authenticatable_id の2列で表す（今2段階認証を使うのはスタッフ＝'staff'のみ）。
 *
 * - code_hash：コードそのものは保存せず、パスワードと同じ考え方で
 *   ハッシュ化して保存する（BackupCodeGeneratorのコメント参照）。
 * - used_at：使用済みかどうか（1回使ったコードは二度と使えない）。
 *
 * 外部キー制約は付けられないので、アカウントを消してもこの行は自動では
 * 消えない。スタッフの通常の削除は論理削除（行が残る）なので、バックアップ
 * コードもそのまま残し、復元すれば元どおり使える。
 *
 * スタッフ用の t_staff_backup_codes が既にある環境では、中身をこのテーブルへ
 * 移してから削除する（登録済みのバックアップコードはそのまま使える）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_backup_codes', function (Blueprint $table) {
            $table->id();
            // indexの名前は自動で付けると
            // "two_factor_backup_codes_authenticatable_type_authenticatable_id_index"
            // （68文字）になり、MariaDB・MySQLの識別子の上限（64文字）を超えるので、
            // 短い名前を指定している。
            $table->morphs('authenticatable', 'two_factor_backup_codes_authenticatable_index');
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('t_staff_backup_codes')) {
            DB::table('two_factor_backup_codes')->insertUsing(
                ['authenticatable_type', 'authenticatable_id', 'code_hash', 'used_at', 'created_at', 'updated_at'],
                DB::table('t_staff_backup_codes')->select([
                    DB::raw("'staff'"), 'staff_id', 'code_hash', 'used_at', 'created_at', 'updated_at',
                ])
            );

            Schema::drop('t_staff_backup_codes');
        }
    }

    public function down(): void
    {
        // スタッフ用に分けたテーブルは作り直さない。
        Schema::dropIfExists('two_factor_backup_codes');
    }
};
