<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ニュース記事の本体テーブル。
     *
     * disp_flgは表示／非表示フラグ（1=表示、0=非表示）。新規登録時はデフォルトで
     * false（非表示）にしてあるので、下書きの状態で保存してから、
     * 内容を確認した上で表示に切り替える、という運用ができる。
     *
     * article_dateはdate型（時刻を持たない日付のみ）。訪問者側の
     * 並び順（新しい順）や年度プルダウンの抽出は、すべてこの列を基準にする。
     *
     * bodyはCKEditor・summernoteいずれかが出力する生のHTMLをそのまま
     * 保存する（HtmlSanitizerで許可リストを通した後の内容）。任意項目
     * なので、本文無し（タイトルと日付だけ）の記事も登録できる。
     *
     * list_image・list_image_originは一覧用画像。DBに保存するのは
     * ファイル名だけで、実際の保存先ディレクトリはAjaxFileUploadトレイトの
     * 規則（news/{news_id}/list_image/）に沿って組み立てる。
     */
    public function up(): void
    {
        Schema::create('t_news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('article_date');
            $table->boolean('disp_flg')->default(false);
            $table->timestamps();
            $table->string('list_image')->nullable();
            $table->string('list_image_origin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_news');
    }
};
