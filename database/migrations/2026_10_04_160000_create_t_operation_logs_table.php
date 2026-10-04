<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 操作ログ（App\Models\OperationLog）。誰が・いつ・どこから・何に・何をしたかを1行ずつ残す。
 *
 * 業務の側で参照したり、古い行を消したりするデータなので、t_の付くテーブルにしている。
 * 操作した人と対象は、種類（staff・memberなど。AppServiceProviderのenforceMorphMap()の名前）と
 * idの組で持つ。氏名などの個人情報と、変更の前後の値は持たない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_operation_logs', function (Blueprint $table) {
            $table->id();
            // 操作した人。ログインの失敗のように誰か分からないときは空
            $table->string('operator_type', 30)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            // 操作の種類（App\Enums\OperationLogAction）
            $table->string('action', 30);
            // 操作の対象。CSVのダウンロードのように対象が1件に決まらないときは空
            $table->string('target_type', 30)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            // 更新のときに値が変わった列の名前の一覧。値は入れない
            $table->json('changed_fields')->nullable();
            // 種類ごとの補足。CSVの名前、ログインに失敗したログインIDなど
            $table->json('detail')->nullable();
            $table->string('ip', 45)->nullable();
            // 端末の種類（App\Support\UserAgentLabel。例：iPhone の Safari）
            $table->string('device', 100)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['operator_type', 'operator_id', 'created_at']);
            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_operation_logs');
    }
};
