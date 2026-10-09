<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CsvEncoding;
use App\Enums\OperationLogAction;
use App\Enums\OperationLogSubject;
use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use App\Support\CsvDownload;
use App\Support\OperationLogStats;
use App\Support\SearchableList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 操作ログの一覧と検索。App\Support\OperationRecorderが書いた記録を見るだけの画面で、
 * 登録・編集・削除は無い。記録を取っていることを全員に見せる必要は無いので、
 * 管理者だけが開ける（routes/web.phpのacl.managerの内側）。
 */
class OperationLogController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // CSVダウンロードの共通処理はCsvDownloadトレイトが提供する。
    // クラス側は csvColumns() と csvCustomColumn() を用意する。
    use CsvDownload;

    // ---- 一覧・検索（SearchableList）の設定 ----

    /** 一覧画面のルート名。セッションキー名の識別子としても使用。 */
    private const INDEX_ROUTE = 'admin.operation-logs.index';

    /** フリーワード検索は使わない。 */
    private const FREE_WORD_COLUMNS = [];

    /** 1ページに表示する件数。前後の操作のつながりを見るので、ほかの一覧より多くしている。 */
    private const PER_PAGE = 50;

    /** 一覧の並び順の選択肢。 */
    private const ORDER_OPTIONS = [
        'created_desc' => [
            'label' => '新しい順',
            'orderBy' => [
                ['created_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'created_asc' => [
            'label' => '古い順',
            'orderBy' => [
                ['created_at', 'asc'],
                ['id', 'asc'],
            ],
        ],
    ];

    /** 一覧の上に出す、1日ごとの棒の日数。 */
    private const STATS_DAYS = 30;

    /**
     * 検索の「操作した人」で、誰もログインしていない訪問者の操作を選ぶときの値。
     * 訪問者の操作は、操作した人の種類が空で残っている。
     */
    private const GUEST = 'guest';

    // ---- 一覧・検索 ----

    /** 一覧・検索 */
    public function index(Request $request): View|RedirectResponse
    {
        $result = $this->buildListData($request, OperationLog::query());

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $logs = $result['paginated'];

        return view('admin.operation_logs.index', [
            // 一覧の上に出す集計。1ページ目にだけ出す。2ページ目から先は一覧を読み進めているので出さない
            'stats' => $logs->currentPage() === 1 ? $this->stats($result['filters']) : null,
            'logs' => $logs,
            // 操作ログには氏名を持たせていないので、このページに出てくるスタッフと会員の、今の氏名を引く
            'names' => OperationLog::subjectNames($logs),
            // CSVと一斉メールの件数と内訳は別の記録が持っているので、このページの分を読む
            'related' => OperationLog::relatedRecords($logs),
            'operatorOptions' => $this->operatorOptions(),
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['orderKey'],
        ]);
    }

    /**
     * 一覧の上に出す集計。今の検索条件で数える。日付の条件だけは外し、期間は集計の側で決める。
     *
     * @return array{hourly: array, daily: array, period: string, days: int, selectedDay: ?string, topOperators: Collection, topNames: array}
     */
    private function stats(array $filters): array
    {
        $query = OperationLog::query();
        $this->setWhere($query, Arr::except($filters, ['date_from', 'date_to']));

        // 1時間ごとの棒の期間。検索で1日だけに絞っていれば、その日の0時から24時間。
        // そうでなければ、今の時を最後にした直近の24時間
        $selectedDay = $this->selectedDay($filters);
        if ($selectedDay !== null) {
            $hourlyFrom = $selectedDay;
            $period = $selectedDay->format('Y-m-d');
        } else {
            $hourlyFrom = now()->startOfHour()->subHours(23);
            $period = '直近24時間';
        }

        // 操作の多い人は、1時間ごとの棒と同じ期間で数える
        $topOperators = OperationLogStats::topOperators($query, $hourlyFrom, $hourlyFrom->copy()->addHours(24));

        return [
            'hourly' => OperationLogStats::hourly($query, $hourlyFrom),
            // 1日ごとの棒は、今日を最後にしたSTATS_DAYS日ぶん
            'daily' => OperationLogStats::daily($query, today()->subDays(self::STATS_DAYS - 1), self::STATS_DAYS),
            // 1時間ごとの棒と、操作の多い人の表の、期間の呼び名
            'period' => $period,
            'days' => self::STATS_DAYS,
            'selectedDay' => $selectedDay?->format('Y-m-d'),
            'topOperators' => $topOperators,
            'topNames' => OperationLog::subjectNames($topOperators),
        ];
    }

    /**
     * 検索で1日だけに絞っていれば、その日の0時。日付の「ここから」と「ここまで」が同じ日のとき。
     * 絞っていないか、2日以上の範囲ならnull
     */
    private function selectedDay(array $filters): ?Carbon
    {
        if (empty($filters['date_from']) || empty($filters['date_to'])) {
            return null;
        }

        $from = Carbon::parse($filters['date_from'])->startOfDay();

        return $from->equalTo(Carbon::parse($filters['date_to'])->startOfDay()) ? $from : null;
    }

    /**
     * 1日ごとの棒を押したとき。今の検索条件はそのままで、日付だけをその1日に絞る。
     * 画面は、今の検索条件をhiddenで一緒に送ってくる
     */
    public function searchDay(Request $request): RedirectResponse
    {
        $day = $request->validate(['day' => ['required', 'date']])['day'];

        $request->merge(['date_from' => $day, 'date_to' => $day]);

        return $this->storeSearchCondition($request);
    }

    /**
     * 検索の「操作した人」の選択肢。値 => 名前。ログインできるスタッフと会員に、訪問者を足す。
     * ニュースなどの対象の種類は、操作した人にはならないので入れない
     */
    private function operatorOptions(): array
    {
        $options = [];
        foreach (OperationLogSubject::OPERATORS as $subject) {
            $options[$subject->value] = $subject->label();
        }

        return $options + [self::GUEST => '訪問者'];
    }

    /**
     * 検索対象項目の検証ルール（SearchableListが要求する）。
     * integer・boolean・Rule::in・Rule::enumのどれかがあれば完全一致、無ければ部分一致
     */
    private function srchRules(): array
    {
        return [
            // 訪問者（self::GUEST）だけは、列の値と比べられないのでapplyCustomSearch()で処理
            'operator_type' => ['nullable', Rule::in(array_keys($this->operatorOptions()))],
            'operator_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(OperationLogAction::class)],
            'target_type' => ['nullable', Rule::in(code_keys('operation_log_subject'))],
            'target_id' => ['nullable', 'integer'],
            'ip' => ['nullable', 'string', 'max:45'],
            // 日付の範囲（カラムではないのでapplyCustomSearch()で処理）
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }

    /** イレギュラーな検索条件の追加処理。 */
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        // 訪問者の操作は、操作した人の種類が空の行
        if ($key === 'operator_type' && $value === self::GUEST) {
            $query->whereNull('operator_type');

            return true;
        }

        // 日付の範囲は、その日の始まりと終わりの時刻で比べる
        if ($key === 'date_from') {
            $query->where('created_at', '>=', Carbon::parse($value)->startOfDay());

            return true;
        }

        if ($key === 'date_to') {
            $query->where('created_at', '<=', Carbon::parse($value)->endOfDay());

            return true;
        }

        return false;
    }

    // ---- CSVダウンロード ----

    /** CSVダウンロード（一覧の今の検索条件・並び順で全件） */
    public function csv(): StreamedResponse
    {
        // 出すのは、このダウンロードより前の操作まで。downloadCsv()は書き出しの前に、
        // このダウンロード自身の操作ログを書く。それも出すと、件数がまだ決まっていないので
        // 「0件」のCSVダウンロードとして載ってしまう
        $lastId = OperationLog::max('id') ?? 0;

        return $this->downloadCsv(
            query: OperationLog::query()->where('id', '<=', $lastId),
            name: '操作ログ',
            encoding: CsvEncoding::Utf8Bom,
            header: true,
            escapeFormula: true,
        );
    }

    /** CSVに出す項目。氏名は操作ログに持たせていないので、種類とidで出す */
    private function csvColumns(): array
    {
        $subjects = code_table('operation_log_subject');

        return [
            '日時' => 'created_at|date:Y/m/d H:i:s',
            '操作した人の種類' => ['operator_type', $subjects],
            '操作した人のID' => 'operator_id',
            '操作' => '@action',
            // CSVのダウンロードと取り込みは、CSVの名前を出す
            '対象の種類' => '@target',
            '対象のID' => 'target_id',
            '変わった項目' => '@changed_fields',
            '補足' => '@detail',
            'IPアドレス' => 'ip',
            '端末' => 'device',
        ];
    }

    /** csvColumns()で「@名前」と書いた項目の値 */
    private function csvCustomColumn(string $key, OperationLog $log): mixed
    {
        return match ($key) {
            'action' => $log->action->label(),
            'target' => $log->csvName() ?? code_label('operation_log_subject', $log->target_type),
            'changed_fields' => implode('、', $log->changed_fields ?? []),
            // CSVと一斉メールは、件数も入れる。内訳は入れない
            'detail' => trim($log->detailText().' '.$log->countSummary($this->relatedRecordOf($log))),
        };
    }

    /** 1行の操作ログの、件数を持っている別の記録。CSVに1行ずつ書き出すときに使う。無ければnull */
    private function relatedRecordOf(OperationLog $log): ?Model
    {
        [$class, $id] = $log->relatedKey();

        return $id !== null ? $class::find($id) : null;
    }
}
