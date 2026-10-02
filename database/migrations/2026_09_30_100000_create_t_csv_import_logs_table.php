<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CSV取り込みの記録（App\Models\CsvImportLog）。
 *
 * ダウンロードの記録（t_csv_download_logs）と同じく、業務の側で参照したり、
 * 古い行を消したりするデータなので、t_の付くテーブルにしている。
 * 件数は、処理だけのモード（CsvImportMode::Process）ではrow_countだけが入り、
 * 追加・更新・変更なしは0になる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_csv_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('filename');
            $table->string('encoding', 20);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_csv_import_logs');
    }
};
