<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ニュースの写真と添付ファイルの並び順の列に、display_orderが残っていればseqに変える。
 * 2つのテーブルを作るマイグレーションと足すマイグレーションは、並び順の列をseqに直したので、
 * 直す前の版を流した環境では、列がdisplay_orderのまま残るため。直した後の版で作った環境では何もしない。
 */
return new class extends Migration
{
    /** 並び順の列を確かめるテーブル */
    private const TABLES = [
        't_news_photos',
        't_news_attachments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasColumn($tableName, 'display_order')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('display_order', 'seq');
            });
        }
    }

    /** 元に戻すと、直した後の版で作った環境と食い違うので、戻さない */
    public function down(): void
    {
    }
};
