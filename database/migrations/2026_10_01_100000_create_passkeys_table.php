<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * パスキーのテーブル（会員・スタッフ共通）。
 *
 * trusted_devices・two_factor_backup_codesと同じく、業務のデータではなく
 * 認証の仕組みを支えるデータなので、t_を付けない汎用のテーブルにしている。
 * 誰のパスキーかは authenticatable_type（'member'・'staff'）と
 * authenticatable_id の2列で表す。
 *
 * laravel/passkeysパッケージにも同じ名前のテーブルを作るマイグレーションが
 * 入っているが、そちらは1種類のユーザー（user_id列）だけを前提にしている。
 * 会員とスタッフの両方で使うため、パッケージのマイグレーションは使わず
 * （php artisan vendor:publishしない）、このマイグレーションで作る。
 *
 * - name：一覧に出す名前。登録したときに、認証器の名前（AAGUIDから）と
 *   OS・ブラウザ名から自動で作る（App\Support\PasskeyManagement::passkeyStore()）
 * - credential_id：ブラウザが送ってくるパスキーのID（base64url）。
 *   ログイン時はこの列でパスキーを探す
 * - credential：公開鍵・署名カウンターなど、照合に使う情報一式（JSON）
 * - last_used_at・last_used_device：最後にログインに使った日時と端末
 *   （OS・ブラウザ名）。一覧で、使っていないパスキーを見分ける手がかりにする
 *
 * 外部キー制約は付けられない（どのテーブルの行を指すかが列の値で変わる）ので、
 * 退会・2段階認証の登録解除では、呼び出し側で明示的に消している。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_device')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
