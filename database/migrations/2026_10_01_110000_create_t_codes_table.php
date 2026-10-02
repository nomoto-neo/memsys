<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DBで管理するコード表（管理画面の「項目見出し一覧」で編集する）。
 *
 * code/*.csvのコード表と同じ形の情報を、コード表の種類（type）ごとに持つ。
 * - type：コード名（App\Enums\CodeTypeの値。例：gender）
 * - code：コード値。数字でも文字でもよいので文字列で持つ。数字だけの値は、
 *   読み出すときにint型にする（前ゼロを除く。CSVと同じ）
 * - name：表示名。改行は「\n」の2文字のまま保存し、読み出すときに改行にする
 *   （CSVと同じ）。空欄でもよい
 * - sort_order：表示順（0から）
 * - staff_id：最後に更新したスタッフ
 *
 * 更新は、そのコード表の行をすべて消して、画面の順に入れ直す
 * （Admin\CodeController::update()）。ほかのデータはコード値で参照し、
 * 行のidは使わない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_codes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('name')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();

            $table->unique(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_codes');
    }
};
