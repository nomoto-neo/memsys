<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CSV取り込みの記録。Neobit\Core\Csv\CsvImportが、取り込みが確定するたびに1行作る。
 * 誰が・いつ・何のCSVを・どのファイルから・何件取り込んだかを残す。
 * 行は足すだけで更新しないので、updated_atは持たない。
 */
class CsvImportLog extends Model implements OperationLogRelatedRecord
{
    public const UPDATED_AT = null;

    protected $table = 'csv_import_logs';

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

    /** 操作ログに出す件数と内訳 */
    public function operationLogSummary(): string
    {
        return number_format($this->row_count).'行（追加 '.number_format($this->inserted_count)
            .'・更新 '.number_format($this->updated_count)
            .'・変更なし '.number_format($this->unchanged_count).'）';
    }
}
