<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理画面の「リダイレクト」で登録する、古いURLから新しいURLへの移し方（Neobit\Core\Models\Redirect）。
 * 1行が1つの移し方。当てるのはNeobit\Core\Http\Middleware\ApplyRedirectsで、.htaccessは書き換えない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            // 古いURLのパス。末尾に*を書くと、その下の全部に当たる。大文字と小文字、末尾の/の有無を別物にするため、
            // 照合順序は文字をそのまま比べるutf8mb4_bin
            $table->string('old_url', 500)->collation('utf8mb4_bin')->unique();
            // 新しいURL。/から始まるサイトの中のパスか、http(s)://から始まる外のURL
            $table->string('new_url', 1000);
            // 301（恒久）か302（一時）
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true);
            // 当たった回数と、最後に当たった日時。使われなくなったものを片付けるため
            $table->unsignedInteger('hits')->default(0);
            $table->dateTime('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
