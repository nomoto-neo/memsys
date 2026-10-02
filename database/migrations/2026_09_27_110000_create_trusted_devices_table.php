<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 信頼済み端末のテーブル（会員の「このデバイスを記憶する」・管理ログインの
 * 「この端末を信頼する」の両方で使う）。
 *
 * 業務のデータではなく認証の仕組みを支えるデータなので、Laravel自身の
 * sessions・cacheテーブルと同じく、t_を付けない汎用のテーブルにしている。
 * 会員用・スタッフ用で分けず、誰の端末かは authenticatable_type・
 * authenticatable_id の2列で表す（$table->morphs()。typeには
 * AppServiceProviderで決めた'member'・'staff'が入る）。
 *
 * - token_hash：ブラウザのCookieに持たせたランダムな文字列（平文）を
 *   Hash::make()した値。DBの中身だけからCookieの値を復元することはできない。
 * - expires_at：信頼の期限。これを過ぎた行は照合に使われない。
 *
 * どのテーブルの行を指すかが列の値で変わるので、外部キー制約は付けられない
 * （アカウントを消しても、この行は自動では消えない）。そのため、退会
 * （MypageController::destroy()）・スタッフの削除（StaffController::destroy()）
 * では、TrustedDeviceManager::forgetAll()で明示的に消している。
 *
 * 会員用・スタッフ用に分けていた t_member_trusted_devices・
 * t_staff_trusted_devices が既にある環境では、中身をこのテーブルへ移してから
 * 削除する（新しく作る環境では、どちらも無いので何もしない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        $this->moveRows('t_member_trusted_devices', 'member', 'member_id');
        $this->moveRows('t_staff_trusted_devices', 'staff', 'staff_id');
    }

    public function down(): void
    {
        // 会員用・スタッフ用に分けたテーブルは作り直さない。
        Schema::dropIfExists('trusted_devices');
    }

    private function moveRows(string $oldTable, string $type, string $idColumn): void
    {
        if (! Schema::hasTable($oldTable)) {
            return;
        }

        DB::table('trusted_devices')->insertUsing(
            ['authenticatable_type', 'authenticatable_id', 'token_hash', 'expires_at', 'created_at', 'updated_at'],
            DB::table($oldTable)->select([
                DB::raw("'{$type}'"), $idColumn, 'token_hash', 'expires_at', 'created_at', 'updated_at',
            ])
        );

        Schema::drop($oldTable);
    }
};
