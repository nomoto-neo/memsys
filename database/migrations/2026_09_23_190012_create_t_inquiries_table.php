<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 問い合わせフォーム（/contact）からの送信内容。
     *
     * メール送信だけでなくDBにも記録を残す（メール送信が届く前・失敗した
     * 場合でも、送信内容自体は残る）。送信の都度スタッフへメール通知するので、
     * 一覧画面は今のところ用意していない（将来、admin側に一覧を足す場合も
     * このテーブルをそのまま使える）。
     *
     * zipはハイフンを含む"123-4567"の形で正規化して保存する
     * （ContactController::normalizeZip()参照）。
     *
     * prefectureはt_members.prefectureと同じくcode/prefectures.csvの
     * キー（都道府県コード）。unsignedTinyIntegerで揃えている。
     *
     * attach_file・attach_file_originは、t_news.list_image・
     * list_image_originと同じ、App\Support\AjaxFileUploadトレイトが
     * 前提にする単数アップロード欄の形（ファイル名のみを保存し、
     * 保存先ディレクトリはApp\Support\UploadFilePathの規則で組み立てる）。
     */
    public function up(): void
    {
        Schema::create('t_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kana');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('zip', 8)->nullable();
            $table->unsignedTinyInteger('prefecture')->nullable();
            $table->string('city')->nullable();
            $table->string('address_other')->nullable();
            $table->text('body')->nullable();
            $table->string('attach_file')->nullable();
            $table->string('attach_file_origin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_inquiries');
    }
};
