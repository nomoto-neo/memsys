<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IPブロック管理（Neobit\Core\Security\IpBlocker）で、アクセスを止めているIPアドレス。
 * 1行が1つの止め方で、解除したら行を消す。いつ誰が止めて解除したかは、操作ログに残る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_blocks', function (Blueprint $table) {
            $table->id();
            // IPアドレス1つか、192.0.2.0/24のようなまとまり
            $table->string('ip', 64)->unique();
            // 自動で止めたものならtrue、管理画面から登録したものならfalse
            $table->boolean('auto')->default(false);
            $table->string('reason')->nullable();
            // 自動で止めたときの、数える時間の中のアクセスの回数
            $table->unsignedInteger('hits')->nullable();
            // 止めるのをやめる日時。nullなら期限なし
            $table->dateTime('blocked_until')->nullable()->index();
            // 管理画面から登録したスタッフの、登録した時点の名前
            $table->string('registered_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_blocks');
    }
};
