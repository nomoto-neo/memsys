<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CSVダウンロードの記録（App\Models\CsvDownloadLog）。
 *
 * 業務の側で参照したり、古い行を消したりするデータなので、t_の付くテーブルにしている。
 * operator_idは、ダウンロードしたときにログインしていた人のid（管理画面ならスタッフのid）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_csv_download_logs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('conditions')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_csv_download_logs');
    }
};
