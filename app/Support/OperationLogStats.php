<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use App\Enums\OperationLogSubject;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 操作ログ（t_operation_logs）の件数の集計。件数の多い時間帯や、操作の多い人に気付けるようにする。
 * 管理画面の操作ログの一覧の上に出すグラフと表、1日1回の報告のメール（OperationLogReport）で使う。
 *
 * どのメソッドも、対象を絞った操作ログのクエリを受け取り、そこに期間の条件を足して数える。
 * 渡したクエリは書き換えない。例：操作を「詳細の閲覧」に絞ったクエリを渡せば、閲覧だけの件数になる。
 */
final class OperationLogStats
{
    // 操作の多い人として出す人数。スタッフと会員のそれぞれについて、この人数まで
    public const TOP_COUNT = 3;

    /**
     * 1時間ごとの件数。$fromの時から24時間ぶんを、古い順に返す。
     *
     * @return array<int, array{label: string, count: int, percent: int}>
     */
    public static function hourly(Builder $query, CarbonInterface $from): array
    {
        $from = $from->copy()->startOfHour();

        $counts = self::countsBy($query, 'Y-m-d H', $from, $from->copy()->addHours(24));

        $bars = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = $from->copy()->addHours($i);
            $bars[] = ['label' => $hour->format('G'), 'count' => $counts[$hour->format('Y-m-d H')] ?? 0];
        }

        return self::withPercent($bars);
    }

    /**
     * 1日ごとの件数。$fromの日から$days日ぶんを、古い順に返す。
     *
     * @return array<int, array{date: string, label: string, count: int, percent: int}>
     */
    public static function daily(Builder $query, CarbonInterface $from, int $days): array
    {
        $from = $from->copy()->startOfDay();

        $counts = self::countsBy($query, 'Y-m-d', $from, $from->copy()->addDays($days));

        $bars = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $bars[] = ['date' => $day->format('Y-m-d'), 'label' => $day->format('n/j'), 'count' => $counts[$day->format('Y-m-d')] ?? 0];
        }

        return self::withPercent($bars);
    }

    /**
     * 操作の多い人。$fromから$toの手前までの件数を、スタッフの上位・会員の上位・訪問者の順に返す。
     * スタッフと会員はTOP_COUNT人まで。訪問者は、誰もログインしていない操作をまとめた1行。
     * 1件も無い種類は返さない。
     *
     * @return Collection<int, object{operator_type: ?string, operator_id: ?int, total: int, views: int, csv_downloads: int, pdfs: int}>
     */
    public static function topOperators(Builder $query, CarbonInterface $from, CarbonInterface $to): Collection
    {
        // 人ごとの、全体の件数と、情報を見たり持ち出したりする操作の件数
        $base = (clone $query)->toBase()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw(
                'operator_type, operator_id, count(*) as total'
                .', sum(case when action = ? then 1 else 0 end) as views'
                .', sum(case when action = ? then 1 else 0 end) as csv_downloads'
                .', sum(case when action = ? then 1 else 0 end) as pdfs',
                [OperationLogAction::View->value, OperationLogAction::CsvDownload->value, OperationLogAction::Pdf->value],
            )
            ->groupBy('operator_type', 'operator_id')
            ->orderByDesc('total')
            ->orderBy('operator_id');

        $rows = collect();

        foreach (OperationLogSubject::OPERATORS as $subject) {
            $rows = $rows->concat((clone $base)->where('operator_type', $subject->value)->limit(self::TOP_COUNT)->get());
        }

        return $rows->concat((clone $base)->whereNull('operator_type')->get())
            ->map(function (object $row) {
                // 件数は、DBによって文字で返ってくるので、数にそろえる
                foreach (['total', 'views', 'csv_downloads', 'pdfs'] as $column) {
                    $row->{$column} = (int) $row->{$column};
                }

                return $row;
            });
    }

    /**
     * $fromから$toの手前までの件数を、日時の書式でまとめて数える。[まとめた日時 => 件数]で返す。
     * $formatはPHPのdate()の書式で、'Y-m-d H'（1時間ごと）か'Y-m-d'（1日ごと）。
     *
     * @return array<string, int>
     */
    public static function countsBy(Builder $query, string $format, CarbonInterface $from, CarbonInterface $to): array
    {
        // 日時を書式でまとめるSQL。書き方がDBによって違う
        $sqlFormat = str_replace(['Y', 'm', 'd', 'H'], ['%Y', '%m', '%d', '%H'], $format);
        $bucket = $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('{$sqlFormat}', created_at)"
            : "date_format(created_at, '{$sqlFormat}')";

        return (clone $query)->toBase()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw("{$bucket} as bucket, count(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    // 棒の高さ。いちばん多い棒を100として、それぞれの割合（%）を足す。全部が0件なら、どれも0
    private static function withPercent(array $bars): array
    {
        $max = max(array_column($bars, 'count'));

        return array_map(
            fn (array $bar) => $bar + ['percent' => $max > 0 ? (int) round($bar['count'] / $max * 100) : 0],
            $bars,
        );
    }
}
