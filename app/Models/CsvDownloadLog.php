<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CSVダウンロードの記録（App\Support\CsvDownloadが1回のダウンロードごとに1行作る）。
 * 誰が・いつ・何のCSVを・どの検索条件で・何件出したかを残す。
 * operator_idは、ダウンロードしたときにログインしていた人のid。
 *
 * 行は追記するだけで更新しないので、updated_atは持たない。
 */
class CsvDownloadLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 't_csv_download_logs';

    protected $fillable = [
        'name',
        'operator_id',
        'row_count',
        'conditions',
        'ip',
    ];

    protected $casts = [
        'operator_id' => 'integer',
        'row_count' => 'integer',
        'conditions' => 'array',
    ];
}
