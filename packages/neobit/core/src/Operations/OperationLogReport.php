<?php

namespace Neobit\Core\Operations;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;

/**
 * 操作ログの、1日ぶんの報告。app:report-operation-logsコマンドが、前の日の分をメールで送る。
 * 件数の多い時間帯や、ふだんと違う使われ方に、管理者が画面を開かなくても気付けるようにするため。
 *
 * ■ 気になる点
 * 次のどれかに当たるものを「気になる点」として挙げる。目安の数は、サイトの規模で変わるので、
 * config('logging.operation_report_thresholds')に持つ。0にした項目は見ない。
 * - 1人が、1時間に詳細をviews_per_hour件以上開いた
 * - 1人が、1日にCSVをcsv_downloads_per_day回以上ダウンロードした
 * - ログインの失敗が、1日にlogin_failures_per_day件以上あった
 * - 回数の制限（AdminRequestLimit）に達した人がいた
 * メールは、気になる点があった日だけ送る。毎日届くと読まれなくなり、肝心の日に埋もれるため。
 *
 * ■ 載せるもの
 * 気になる点、操作の種類ごとの件数、操作の多い人（OperationLogStats::topOperators()）。
 * 操作ログと同じく、氏名のほかの個人情報と、変更の前後の値は載せない。
 */
final class OperationLogReport
{
    /**
     * $dateの1日ぶんの報告を作る。メールのテンプレート（operation_log_report）に渡す値のうち、
     * 宛先などを除いた中身を返す。notableが空の文字なら、気になる点は無い。
     *
     * @return array{date: string, notable: string, counts: string, operators: string}
     */
    public static function for(CarbonInterface $date): array
    {
        $from = $date->copy()->startOfDay();
        $to = $from->copy()->addDay();

        $query = OperationLog::query();
        $ofDay = fn (): Builder => OperationLog::query()->where('created_at', '>=', $from)->where('created_at', '<', $to);

        // ---- 操作の種類ごとの件数。0件の種類は載せない ----
        $countsByAction = $ofDay()->toBase()
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($total) => (int) $total);

        $countLines = ['全体　'.number_format($countsByAction->sum()).'件'];
        foreach (code_table('operation_log_action') as $value => $label) {
            if (isset($countsByAction[$value])) {
                $countLines[] = $label.'　'.number_format($countsByAction[$value]).'件';
            }
        }

        // ---- 気になる点 ----
        // 目安の数。設定に無い項目と0の項目は、見ない
        $viewsPerHour = self::threshold('views_per_hour');
        $csvDownloadsPerDay = self::threshold('csv_downloads_per_day');
        $loginFailuresPerDay = self::threshold('login_failures_per_day');

        // 1時間に開いた詳細の件数が多い人と、1日のCSVのダウンロードが多い人。人と時間帯ごとに数える
        $viewsByHour = collect();
        if ($viewsPerHour > 0) {
            $viewsByHour = $ofDay()->toBase()
                ->where('action', OperationLogAction::View->value)
                ->selectRaw('operator_type, operator_id, '.self::hourExpression($query).' as hour, count(*) as total')
                ->groupBy('operator_type', 'operator_id', 'hour')
                ->havingRaw('count(*) >= ?', [$viewsPerHour])
                ->orderBy('hour')
                ->get();
        }

        $csvByOperator = collect();
        if ($csvDownloadsPerDay > 0) {
            $csvByOperator = $ofDay()->toBase()
                ->where('action', OperationLogAction::CsvDownload->value)
                ->selectRaw('operator_type, operator_id, count(*) as total')
                ->groupBy('operator_type', 'operator_id')
                ->havingRaw('count(*) >= ?', [$csvDownloadsPerDay])
                ->get();
        }

        $throttled = $ofDay()->toBase()
            ->where('action', OperationLogAction::Throttled->value)
            ->selectRaw('operator_type, operator_id, count(*) as total')
            ->groupBy('operator_type', 'operator_id')
            ->get();

        // 操作の多い人。スタッフと会員の上位と、訪問者
        $topOperators = OperationLogStats::topOperators($query, $from, $to);

        // 氏名は操作ログに持たせていないので、ここまでに出てきた人の今の氏名をまとめて引く
        $names = OperationLog::subjectNames(
            $viewsByHour->concat($csvByOperator)->concat($throttled)->concat($topOperators)
        );
        $label = fn (object $row): string => OperationLog::operatorLabel($row->operator_type, $row->operator_id, $names);

        $notable = [];
        foreach ($viewsByHour as $row) {
            $notable[] = '・'.$label($row).' が、'.((int) $row->hour).'時台に詳細を'.number_format($row->total)
                .'件開いています（目安：1時間に'.$viewsPerHour.'件）';
        }
        foreach ($csvByOperator as $row) {
            $notable[] = '・'.$label($row).' が、CSVを'.number_format($row->total)
                .'回ダウンロードしています（目安：1日に'.$csvDownloadsPerDay.'回）';
        }
        $loginFailures = $countsByAction[OperationLogAction::LoginFailed->value] ?? 0;
        if ($loginFailuresPerDay > 0 && $loginFailures >= $loginFailuresPerDay) {
            $notable[] = '・ログインの失敗が'.number_format($loginFailures).'件あります（目安：1日に'.$loginFailuresPerDay.'件）';
        }
        foreach ($throttled as $row) {
            $notable[] = '・'.$label($row).' が、管理画面の回数の制限に達しています';
        }

        // ---- 操作の多い人。全体の件数の後ろに、見たり持ち出したりした件数を0件のものを除いて添える ----
        $operatorLines = [];
        foreach ($topOperators as $row) {
            $parts = array_filter([
                $row->views > 0 ? '詳細の閲覧 '.number_format($row->views) : null,
                $row->csv_downloads > 0 ? 'CSVダウンロード '.number_format($row->csv_downloads) : null,
                $row->pdfs > 0 ? 'PDF出力 '.number_format($row->pdfs) : null,
            ]);

            $operatorLines[] = $label($row).'　'.number_format($row->total).'件'
                .($parts !== [] ? '（'.implode('・', $parts).'）' : '');
        }

        return [
            'date' => $from->format('Y-m-d'),
            'notable' => implode("\n", $notable),
            'counts' => implode("\n", $countLines),
            'operators' => $operatorLines !== [] ? implode("\n", $operatorLines) : '操作はありません。',
        ];
    }

    /** 気になる点の、目安の数。設定に無ければ0で、その項目は見ない */
    private static function threshold(string $name): int
    {
        return (int) config('logging.operation_report_thresholds.'.$name);
    }

    /** 日時から「時」を取り出すSQL。書き方がDBによって違う */
    private static function hourExpression(Builder $query): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%H', created_at)"
            : "date_format(created_at, '%H')";
    }
}
