<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コード表の並び順の列の名前を、sort_orderからseqに変える。
 * 並び順の列の名前を、案件のテーブルと揃えるため。
 * 新しく入れたプロジェクトは、最初からseqで作られるので、sort_orderがあるときだけ変える。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('code_tables', 'sort_order')) {
            return;
        }

        Schema::table('code_tables', function (Blueprint $table) {
            $table->renameColumn('sort_order', 'seq');
        });
    }

    public function down(): void
    {
        Schema::table('code_tables', function (Blueprint $table) {
            $table->renameColumn('seq', 'sort_order');
        });
    }
};
