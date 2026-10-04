<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一斉メールの文面。よく送る案内の件名と本文を登録しておき、送るときに選んで使う。
 * titleは管理画面で見分けるための名前で、メールには出ない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_bulk_mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_bulk_mail_templates');
    }
};
