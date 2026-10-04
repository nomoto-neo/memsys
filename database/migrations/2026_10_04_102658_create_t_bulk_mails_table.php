<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一斉メールの送信の記録。送るたびに1行作る。
 *
 * 宛先は個人情報なので保存しない。宛先はCSVから読んで、1通ずつキューのジョブに積むだけで、
 * 送り終えたジョブは消える。ここに残すのは件名・本文・添付ファイルと件数だけ。
 * 送信中の進み具合はbatch_idのジョブのバッチ（job_batchesテーブル）から読む。送り終えたら
 * 送れた件数と失敗した件数をここに写すので、バッチの行を片付けても履歴の件数は残る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_bulk_mails', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('attach')->nullable();
            $table->string('attach_origin')->nullable();
            $table->string('csv_filename');
            $table->unsignedInteger('recipient_count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20);
            $table->string('batch_id', 36)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_bulk_mails');
    }
};
