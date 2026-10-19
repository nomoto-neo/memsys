<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会員ではない人の、お知らせメール（メルマガ）を受け取るかどうか。メールアドレスごとに1行。
 *
 * 会員の「受け取るか」は会員のテーブル（t_members.notice_mail）で持ち、ここには持たない。
 * 同じアドレスの「受け取るか」を、どの時点でも1か所だけで持つため（App\Services\NewsletterSubscription）。
 * ここに行ができるのは、メルマガだけの申し込み、会員ではない人の配信停止、退会の3つ。
 * 受け取ると決めた日時とIPアドレスは、特定電子メール法の同意の記録として残す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('email')->unique();
            $table->unsignedTinyInteger('notice_mail');
            $table->string('source', 30);
            $table->dateTime('subscribed_at')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('notice_mail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_newsletter_subscribers');
    }
};
