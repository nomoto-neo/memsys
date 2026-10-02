<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会員テーブル。
 *
 * - kana・phone・birthdate・prefectureは任意項目。prefectureは
 *   都道府県コード（code/prefectures.csvのキー）をunsignedTinyIntegerで持つ。
 * - withdrawn_at：退会フラグ(true/false)ではなく、退会日時そのものを
 *   持たせる設計。null = 在籍中。
 * - staff_id：/adminから最後にこの会員情報を更新したスタッフのid。
 *   foreignId()でStaff::idと型は揃えているが、constrained()による
 *   外部キー制約は付けていない（このプロジェクトの他のテーブルも
 *   まだ外部キー制約を使っておらず、今回だけ導入するほどの必要性が
 *   無いと判断したため。t_staffs側は論理削除にしているので、行が
 *   物理的に消えて参照が壊れる、という制約が本来防ぎたい事故もそもそも
 *   起きない）。nullableなのは、/adminから一度も更新されていない会員
 *   （自己登録のまま）ではstaff_idが無い状態を正しく表現するため。
 * - remember_token：Auth::attempt()の「ログイン状態を保持する」
 *   (remember me)機能が使う専用カラム。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kana')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unsignedTinyInteger('prefecture')->nullable();
            $table->foreignId('staff_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_members');
    }
};
