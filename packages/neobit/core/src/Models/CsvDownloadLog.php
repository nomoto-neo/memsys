<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CSVダウンロードの記録。Neobit\Core\Csv\CsvDownloadが、1回のダウンロードごとに1行作る。
 * 誰が・いつ・何のCSVを・どの検索条件で・何件出したかを残す。
 * 行は足すだけで更新しないので、updated_atは持たない。
 */
class CsvDownloadLog extends Model implements OperationLogRelatedRecord
{
    public const UPDATED_AT = null;

    protected $table = 'csv_download_logs';

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

    /** 操作ログに出す件数 */
    public function operationLogSummary(): string
    {
        return number_format($this->row_count).'件';
    }
}
