<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 操作ログと、CSVのダウンロード・取り込みの記録のテーブルから、名前の頭のt_を外す。
 *
 * どれも、誰が何をしたかを残すための、フレームワークの側の記録で、業務のデータではない。
 * trusted_devices・two_factor_secretsなどと同じく、t_を付けない共通のテーブルにそろえる。
 * 名前を変えるだけなので、それまでの行はそのまま残る。
 */
return new class extends Migration
{
    /** 変える前の名前 => 変えた後の名前 */
    private const TABLES = [
        't_operation_logs' => 'operation_logs',
        't_csv_download_logs' => 'csv_download_logs',
        't_csv_import_logs' => 'csv_import_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $from => $to) {
            Schema::rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $from => $to) {
            Schema::rename($to, $from);
        }
    }
};
