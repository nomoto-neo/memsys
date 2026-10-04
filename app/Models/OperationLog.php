<?php

namespace App\Models;

use App\Enums\OperationLogAction;
use App\Enums\OperationLogSubject;
use Illuminate\Database\Eloquent\Model;

/**
 * 操作ログ。App\Support\OperationRecorderが、1回の操作ごとに1行作る。
 * 誰が・いつ・どこから・何に・何をしたかを残す。氏名などの個人情報と、変更の前後の値は持たない。
 * 行は足すだけで更新しないので、updated_atは持たない。
 */
class OperationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 't_operation_logs';

    protected $fillable = [
        'operator_type',
        'operator_id',
        'action',
        'target_type',
        'target_id',
        'changed_fields',
        'detail',
        'ip',
        'device',
    ];

    protected $casts = [
        'operator_id' => 'integer',
        'action' => OperationLogAction::class,
        'target_id' => 'integer',
        'changed_fields' => 'array',
        'detail' => 'array',
    ];

    /**
     * 画面に出すための、スタッフと会員の今の氏名。[種類 => [id => 氏名]]で返す。
     * 操作ログには氏名を持たせていないので、渡された行の操作した人と対象を、種類ごとに
     * まとめて1回ずつで読む。削除したスタッフも名前を出せるよう、削除済みも含めて探す。
     * 退会した会員は行が無いので出ない。
     *
     * @param  iterable<self>  $logs
     */
    public static function subjectNames(iterable $logs): array
    {
        $ids = [
            OperationLogSubject::Staff->value => [],
            OperationLogSubject::Member->value => [],
        ];

        foreach ($logs as $log) {
            foreach ([[$log->operator_type, $log->operator_id], [$log->target_type, $log->target_id]] as [$type, $id]) {
                if (isset($ids[$type]) && $id !== null) {
                    $ids[$type][] = $id;
                }
            }
        }

        return [
            OperationLogSubject::Staff->value => Staff::withTrashed()
                ->whereIn('id', $ids[OperationLogSubject::Staff->value])
                ->pluck('name', 'id'),
            OperationLogSubject::Member->value => Member::query()
                ->whereIn('id', $ids[OperationLogSubject::Member->value])
                ->pluck('name', 'id'),
        ];
    }

    // 補足のうち、画面に出す文字。CSVの記録のidのように、ほかのテーブルとつなぐための値は出さない
    public function detailText(): string
    {
        $shown = array_filter(
            $this->detail ?? [],
            fn (string $key) => ! str_ends_with($key, '_log_id'),
            ARRAY_FILTER_USE_KEY,
        );

        return implode(' ', $shown);
    }
}
