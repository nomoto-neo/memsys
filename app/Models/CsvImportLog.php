<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CSV取り込みの記録（App\Support\CsvImportが、取り込みが確定するたびに1行作る）。
 * 誰が・いつ・何のCSVを・どのファイルから・何件取り込んだかを残す。
 * operator_idは、取り込んだときにログインしていた人のid。
 *
 * 行は追記するだけで更新しないので、updated_atは持たない。
 */
class CsvImportLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 't_csv_import_logs';

    protected $fillable = [
        'name',
        'operator_id',
        'filename',
        'encoding',
        'row_count',
        'inserted_count',
        'updated_count',
        'unchanged_count',
        'ip',
    ];

    protected $casts = [
        'operator_id' => 'integer',
        'row_count' => 'integer',
        'inserted_count' => 'integer',
        'updated_count' => 'integer',
        'unchanged_count' => 'integer',
    ];
}
