<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use App\Models\OperationLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * 操作ログの、1日ぶんの報告。app:report-operation-logsコマンドが、前の日の分をメールで送る。
 * 件数の多い時間帯や、ふだんと違う使われ方に、管理者が画面を開かなくても気付けるようにするため。
 *
 * ■ 気になる点
 * 次のどれかに当たるものを「気になる点」として挙げる。数は、このクラスの定数で決めている。
 * - 1人が、1時間に詳細をVIEWS_PER_HOUR件以上開いた
 * - 1人が、1日にCSVをCSV_DOWNLOADS_PER_DAY回以上ダウンロードした
 * - ログインの失敗が、1日にLOGIN_FAILURES_PER_DAY件以上あった
 * - 回数の制限（AdminRequestLimit）に達した人がいた
 * メールは、気になる点があった日だけ送る。毎日届くと読まれなくなり、肝心の日に埋もれるため。
 *
 * ■ 載せるもの
 * 気になる点、操作の種類ごとの件数、操作の多い人（OperationLogStats::topOperators()）。
 * 操作ログと同じく、氏名のほかの個人情報と、変更の前後の値は載せない。
 */
final class OperationLogReport
{
    /** 1人が1時間に開いた詳細の件数。これ以上なら気になる点にする */
    private const VIEWS_PER_HOUR = 50;

    /** 1人が1日にCSVをダウンロードした回数。これ以上なら気になる点にする */
    private const CSV_DOWNLOADS_PER_DAY = 5;

    /** 1日のログインの失敗の件数。これ以上なら気になる点にする */
    private const LOGIN_FAILURES_PER_DAY = 10;

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
        foreach (OperationLogAction::cases() as $action) {
            if (isset($countsByAction[$action->value])) {
                $countLines[] = $action->label().'　'.number_format($countsByAction[$action->value]).'件';
            }
        }

        // ---- 気になる点 ----
        // 1時間に開いた詳細の件数が多い人と、1日のCSVのダウンロードが多い人。人と時間帯ごとに数える
        $viewsByHour = $ofDay()->toBase()
            ->where('action', OperationLogAction::View->value)
            ->selectRaw('operator_type, operator_id, '.self::hourExpression($query).' as hour, count(*) as total')
            ->groupBy('operator_type', 'operator_id', 'hour')
            ->havingRaw('count(*) >= ?', [self::VIEWS_PER_HOUR])
            ->orderBy('hour')
            ->get();

        $csvByOperator = $ofDay()->toBase()
            ->where('action', OperationLogAction::CsvDownload->value)
            ->selectRaw('operator_type, operator_id, count(*) as total')
            ->groupBy('operator_type', 'operator_id')
            ->havingRaw('count(*) >= ?', [self::CSV_DOWNLOADS_PER_DAY])
            ->get();

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
                .'件開いています（目安：1時間に'.self::VIEWS_PER_HOUR.'件）';
        }
        foreach ($csvByOperator as $row) {
            $notable[] = '・'.$label($row).' が、CSVを'.number_format($row->total)
                .'回ダウンロードしています（目安：1日に'.self::CSV_DOWNLOADS_PER_DAY.'回）';
        }
        $loginFailures = $countsByAction[OperationLogAction::LoginFailed->value] ?? 0;
        if ($loginFailures >= self::LOGIN_FAILURES_PER_DAY) {
            $notable[] = '・ログインの失敗が'.number_format($loginFailures).'件あります（目安：1日に'.self::LOGIN_FAILURES_PER_DAY.'件）';
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

    /** 日時から「時」を取り出すSQL。書き方がDBによって違う */
    private static function hourExpression(Builder $query): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%H', created_at)"
            : "date_format(created_at, '%H')";
    }
}
