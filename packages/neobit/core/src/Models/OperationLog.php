<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\Casts\OperationLogActionCast;

/**
 * 操作ログ。Neobit\Core\Operations\OperationRecorderが、1回の操作ごとに1行作る。
 * 誰が・いつ・どこから・何に・何をしたかを残す。氏名などの個人情報と、変更の前後の値は持たない。
 * 行は足すだけで更新しないので、updated_atは持たない。
 */
class OperationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'operation_logs';

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
        'action' => OperationLogActionCast::class,
        'target_id' => 'integer',
        'changed_fields' => 'array',
        // 日本語を\u4f1aのような表記に変えずに、そのまま保存する。一覧の検索で、補足を部分一致で探せるようにするため
        'detail' => 'json:unicode',
    ];

    /**
     * コード表 operation_log_action（Neobit\Core\Code\CodeTableが自分で持つ）。操作の値 => 名前。
     * config('neobit.operation_log_actions')の列挙型を書いた順に合わせ、フレームワークの操作と
     * サイトの操作を、1つの選択肢の一覧にする。
     */
    public static function actionOptions(): array
    {
        $options = [];
        foreach (config('neobit.operation_log_actions', []) as $class) {
            foreach ($class::cases() as $case) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }

    /**
     * コード表 operation_log_model（Neobit\Core\Code\CodeTableが自分で持つ）。モデルの種類の名前 => 画面の名前。
     * 操作した人と対象の種類（operator_type・target_type）を、画面やメールで名前にするのに使う。
     * config('neobit.operation_log_models')の列挙型を書いた順に合わせる。値はenforceMorphMap()の名前と同じにする。
     * 書いていない種類は一覧に無いので、code_label()が種類の名前のまま出す。
     */
    public static function modelOptions(): array
    {
        $options = [];
        foreach (config('neobit.operation_log_models', []) as $class) {
            foreach ($class::cases() as $case) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }

    /**
     * 画面に出すための、今の名前。[種類 => [id => 名前]]で返す。
     * 名前は、操作した人と対象のモデルが、operationLogNames()で返す
     * （OperationLogNamedModelを実装したもの）。
     * 操作ログには名前を持たせていないので、渡された行の操作した人と対象を、種類ごとに
     * まとめて1回ずつで読む。退会した会員のように、もう行が無いものは出ない。
     *
     * @param  iterable<object>  $logs  操作ログの行。operator_type・operator_idを持つ集計の行でもよい
     */
    public static function modelNames(iterable $logs): array
    {
        // 名前を引ける種類（モーフの別名）と、そのモデルのクラス
        $readers = [];
        foreach (Relation::morphMap() as $type => $class) {
            if (is_subclass_of($class, OperationLogNamedModel::class)) {
                $readers[$type] = $class;
            }
        }

        $ids = array_fill_keys(array_keys($readers), []);

        foreach ($logs as $log) {
            // 集計の行のように、対象を持たないものも渡せる
            foreach ([[$log->operator_type, $log->operator_id], [$log->target_type ?? null, $log->target_id ?? null]] as [$type, $id]) {
                if (isset($ids[$type]) && $id !== null) {
                    $ids[$type][] = $id;
                }
            }
        }

        $names = [];
        foreach ($readers as $type => $class) {
            $names[$type] = $ids[$type] !== [] ? $class::operationLogNames(array_unique($ids[$type])) : collect();
        }

        return $names;
    }

    /**
     * 操作した人を「スタッフID:3　氏名」の形にした文字。誰もログインしていない操作は「訪問者」。
     * $namesはmodelNames()の結果。メールのように、Bladeで組み立てないところで使う
     */
    public static function operatorLabel(?string $type, ?int $id, array $names): string
    {
        if ($type === null) {
            return '訪問者';
        }

        // 退会した会員のように、もう行が無い人は氏名が出ない
        $name = $names[$type][$id] ?? '';

        return code_label('operation_log_model', $type, $type).'ID:'.$id.($name !== '' ? '　'.$name : '');
    }

    /**
     * 件数や内訳を持っている、別の記録。[操作ログのid => 記録]で返す。
     * CSVのダウンロードと取り込みは、それぞれの記録（CsvDownloadLog・CsvImportLog）、
     * 一斉メールの送信は、送信の記録（BulkMail）。操作ログには件数を持たせていないので、
     * 画面に件数と内訳を出すときに、渡された行の分をまとめて読む。
     *
     * @param  iterable<self>  $logs
     * @return array<int, OperationLogRelatedRecord>
     */
    public static function relatedRecords(iterable $logs): array
    {
        // 相手の記録のクラスごとに、[操作ログのid => 相手の記録のid]を集める
        $ids = [];

        foreach ($logs as $log) {
            [$class, $id] = $log->relatedKey();

            if ($id !== null) {
                $ids[$class][$log->id] = $id;
            }
        }

        $related = [];

        foreach ($ids as $class => $idsOfLogs) {
            $records = $class::query()->whereIn('id', $idsOfLogs)->get()->keyBy('id');

            // 相手の記録が消えていれば、その行には何も出さない
            foreach ($idsOfLogs as $logId => $id) {
                if (isset($records[$id])) {
                    $related[$logId] = $records[$id];
                }
            }
        }

        return $related;
    }

    /**
     * この行の件数や内訳を持っている記録の、モデルのクラスとid。持たない操作なら[null, null]。
     * どの記録を持つかは、操作の種類の列挙型が決める。
     *
     * @return array{0: class-string<Model>|null, 1: int|null}
     */
    public function relatedKey(): array
    {
        return $this->action->relatedKey($this);
    }

    /**
     * 件数を1行にまとめた文字。一覧の画面と、操作ログのCSVで使う。
     * $relatedはrelatedRecords()で読んだ、この行の相手の記録。無ければ空の文字。
     */
    public function countSummary(?OperationLogRelatedRecord $related): string
    {
        return $related?->operationLogSummary() ?? '';
    }

    /**
     * 操作した人になれる種類（モーフの別名）。ログインできるモデル（config('auth.providers')の
     * モデル）の別名で、認証の設定の順に返す。
     *
     * @return string[]
     */
    public static function operatorTypes(): array
    {
        $types = [];
        foreach (config('auth.providers') as $provider) {
            if (isset($provider['model'])) {
                $types[] = Relation::getMorphAlias($provider['model']);
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * CSVのダウンロードと取り込みの、CSVの名前。何に対する操作かなので、画面では対象の欄に出す。
     * ほかの操作ならnull
     */
    public function csvName(): ?string
    {
        $isCsv = in_array($this->action, [OperationLogAction::CsvDownload, OperationLogAction::CsvImport], true);

        return $isCsv ? ($this->detail['name'] ?? null) : null;
    }

    /**
     * 補足のうち、画面に出す文字。CSVの記録のidのように、ほかのテーブルとつなぐための値と、
     * 対象の欄に出すCSVの名前は出さない
     */
    public function detailText(): string
    {
        $shown = array_filter(
            $this->detail ?? [],
            fn (string $key) => ! str_ends_with($key, '_log_id') && ! ($key === 'name' && $this->csvName() !== null),
            ARRAY_FILTER_USE_KEY,
        );

        return implode(' ', $shown);
    }
}
