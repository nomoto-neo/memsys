<?php

namespace App\Models;

use App\Enums\BulkMailStatus;
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
     * 画面に出すための、今の名前。[種類 => [id => 名前]]で返す。
     * スタッフ・個人会員・企業の担当者は氏名、企業は企業名。企業の担当者は、どの企業の人かが
     * 分かるよう、企業名を前に付ける。
     * 操作ログには名前を持たせていないので、渡された行の操作した人と対象を、種類ごとに
     * まとめて1回ずつで読む。削除したスタッフも名前を出せるよう、削除済みも含めて探す。
     * 退会した会員のように、もう行が無いものは出ない。
     *
     * @param  iterable<object>  $logs  操作ログの行。operator_type・operator_idを持つ集計の行でもよい
     */
    public static function subjectNames(iterable $logs): array
    {
        // 名前を引く種類と、その読み方。idの一覧を受け取って、[id => 名前]を返す
        $readers = [
            OperationLogSubject::Staff->value => fn (array $ids) => Staff::withTrashed()->whereIn('id', $ids)->pluck('name', 'id'),
            OperationLogSubject::Member->value => fn (array $ids) => Member::query()->whereIn('id', $ids)->pluck('name', 'id'),
            OperationLogSubject::Company->value => fn (array $ids) => Company::query()->whereIn('id', $ids)->pluck('name', 'id'),
            OperationLogSubject::CompanyUser->value => fn (array $ids) => CompanyUser::query()->with('company')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (CompanyUser $user) => [$user->id => $user->displayName()]),
        ];

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
        foreach ($readers as $type => $read) {
            $names[$type] = $ids[$type] !== [] ? $read(array_unique($ids[$type])) : collect();
        }

        return $names;
    }

    /**
     * 操作した人を「スタッフID:3　氏名」の形にした文字。誰もログインしていない操作は「訪問者」。
     * $namesはsubjectNames()の結果。メールのように、Bladeで組み立てないところで使う
     */
    public static function operatorLabel(?string $type, ?int $id, array $names): string
    {
        if ($type === null) {
            return '訪問者';
        }

        // 退会した会員のように、もう行が無い人は氏名が出ない
        $name = $names[$type][$id] ?? '';

        return code_label('operation_log_subject', $type).'ID:'.$id.($name !== '' ? '　'.$name : '');
    }

    /**
     * 件数や内訳を持っている、別の記録。[操作ログのid => 記録]で返す。
     * CSVのダウンロードと取り込みは、それぞれの記録（CsvDownloadLog・CsvImportLog）、
     * 一斉メールの送信は、送信の記録（BulkMail）。操作ログには件数を持たせていないので、
     * 画面に件数と内訳を出すときに、渡された行の分をまとめて読む。
     *
     * @param  iterable<self>  $logs
     * @return array<int, CsvDownloadLog|CsvImportLog|BulkMail>
     */
    public static function relatedRecords(iterable $logs): array
    {
        // 操作の種類ごとに、[操作ログのid => 相手の記録のid]を集める
        $ids = [
            CsvDownloadLog::class => [],
            CsvImportLog::class => [],
            BulkMail::class => [],
        ];

        foreach ($logs as $log) {
            [$class, $id] = $log->relatedKey();

            if ($id !== null) {
                $ids[$class][$log->id] = $id;
            }
        }

        $related = [];

        foreach ($ids as $class => $idsOfLogs) {
            if ($idsOfLogs === []) {
                continue;
            }

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
     *
     * @return array{0: class-string<Model>|null, 1: int|null}
     */
    public function relatedKey(): array
    {
        return match ($this->action) {
            OperationLogAction::CsvDownload => [CsvDownloadLog::class, $this->detail['csv_download_log_id'] ?? null],
            OperationLogAction::CsvImport => [CsvImportLog::class, $this->detail['csv_import_log_id'] ?? null],
            OperationLogAction::BulkMailSend => [BulkMail::class, $this->target_id],
            default => [null, null],
        };
    }

    /**
     * 件数を1行にまとめた文字。一覧の画面と、操作ログのCSVで使う。
     * $relatedはrelatedRecords()で読んだ、この行の相手の記録。無ければ空の文字。
     */
    public function countSummary(CsvDownloadLog|CsvImportLog|BulkMail|null $related): string
    {
        if ($related instanceof CsvDownloadLog) {
            return number_format($related->row_count).'件';
        }

        if ($related instanceof CsvImportLog) {
            return number_format($related->row_count).'行（追加 '.number_format($related->inserted_count)
                .'・更新 '.number_format($related->updated_count)
                .'・変更なし '.number_format($related->unchanged_count).'）';
        }

        if ($related instanceof BulkMail) {
            // 送信済みと失敗の件数は、送信が終わったときに記録に入る
            $progress = $related->status === BulkMailStatus::Finished
                ? '送信済み '.number_format($related->sent_count).'・失敗 '.number_format($related->failed_count)
                : '送信中';

            return '宛先 '.number_format($related->recipient_count).'件（'.$progress.'）';
        }

        return '';
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
