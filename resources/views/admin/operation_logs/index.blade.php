@extends('layouts.admin')

{{--
    集計の棒グラフの見た目。棒は<div>の高さで書き、グラフの部品は使わない。
    1列が1本の棒で、上から件数・棒・目盛りの文字。棒の高さは、いちばん多い棒を100%とした割合。
--}}
@push('head-extra')
<style>
.stats-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
}
.stats-chart-col {
    flex: 1 1 0;
    min-width: 0;
    padding: 0;
    border: 0;
    background: none;
    text-align: center;
    font-size: 0.7rem;
    line-height: 1.3;
    color: inherit;
}
.stats-chart-area {
    display: flex;
    align-items: flex-end;
    height: 100px;
}
.stats-chart-bar {
    width: 100%;
    min-height: 1px;
    background: var(--bs-primary);
}
.stats-chart-col.is-selected .stats-chart-bar {
    background: var(--bs-danger);
}
button.stats-chart-col:hover .stats-chart-bar {
    opacity: 0.7;
}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">操作ログ</h1>
    {{-- 一覧の今の検索条件・並び順で全件を出す --}}
    <a href="{{ route('admin.operation-logs.csv') }}" class="btn btn-outline-secondary btn-sm">CSVダウンロード</a>
</div>

{{--
    name属性は検索対象のカラム名のまま。OperationLogController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - date_from・date_to：日付の範囲（OperationLogController::applyCustomSearch()で処理）
    - operator_type・operator_id：操作した人の種類とid（完全一致）。「訪問者」は、誰もログインして
      いない操作（OperationLogController::applyCustomSearch()で処理）
    - action：操作の種類（完全一致）
    - target_type・target_id：対象の種類とid（完全一致）
    - ip：IPアドレス（部分一致）
--}}
<form method="POST" action="{{ route('admin.operation-logs.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-2">
        <input type="date" name="date_from" class="form-control" title="日付（ここから）"
               value="{{ $filters['date_from'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="date" name="date_to" class="form-control" title="日付（ここまで）"
               value="{{ $filters['date_to'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="action" class="form-select">
            <option value="">操作（すべて）</option>
            @foreach (code_table('operation_log_action') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['action'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <input type="text" name="ip" maxlength="45" class="form-control" placeholder="IPアドレス"
               value="{{ $filters['ip'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="orderby" class="form-select">
            @foreach ($orderOptions as $key => $option)
                <option value="{{ $key }}" @selected($selectedOrder === $key)>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <button type="submit" class="btn btn-primary w-100">検索</button>
    </div>

    <div class="col-sm-2">
        <select name="operator_type" class="form-select">
            <option value="">操作した人（すべて）</option>
            {{-- スタッフ・会員・訪問者。訪問者は、誰もログインしていない操作
                 （お問い合わせの送信、ログインの失敗など） --}}
            @foreach ($operatorOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['operator_type'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <input type="text" name="operator_id" inputmode="numeric" class="form-control" placeholder="操作した人のID"
               value="{{ $filters['operator_id'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="target_type" class="form-select">
            <option value="">対象（すべて）</option>
            @foreach (code_table('operation_log_subject') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['target_type'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <input type="text" name="target_id" inputmode="numeric" class="form-control" placeholder="対象のID"
               value="{{ $filters['target_id'] ?? '' }}">
    </div>
</form>

{{--
    集計。1ページ目にだけ出す。今の検索条件で数える（日付の条件だけは外し、期間は下のとおり）。
    - 1時間ごと：直近の24時間。検索で1日だけに絞っているときは、その日の0時から24時間
    - 1日ごと：今日までの$stats['days']日。棒を押すと、今の検索条件のまま、その1日に絞る
    - 操作の多い人：1時間ごとの棒と同じ期間。スタッフと会員の上位と、訪問者
--}}
@if ($stats !== null)
<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6">{{ $stats['period'] }}（1時間ごと）　合計 {{ number_format(array_sum(array_column($stats['hourly'], 'count'))) }}件</h2>
        <div class="stats-chart mb-4">
            @foreach ($stats['hourly'] as $bar)
                <div class="stats-chart-col">
                    <div>{{ $bar['count'] > 0 ? number_format($bar['count']) : '' }}</div>
                    <div class="stats-chart-area"><div class="stats-chart-bar" style="height: {{ $bar['percent'] }}%;"></div></div>
                    <div class="text-muted">{{ $bar['label'] }}時</div>
                </div>
            @endforeach
        </div>

        <h2 class="h6">直近{{ $stats['days'] }}日（1日ごと）　合計 {{ number_format(array_sum(array_column($stats['daily'], 'count'))) }}件</h2>
        {{-- 棒は送信ボタン。押した日をdayで送る。今の検索条件は、日付のほかをhiddenで持ち越す --}}
        <form method="POST" action="{{ route('admin.operation-logs.day') }}" class="stats-chart mb-4">
            @csrf
            @foreach ($filters as $key => $value)
                @if (! in_array($key, ['date_from', 'date_to'], true))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @foreach ($stats['daily'] as $bar)
                <button type="submit" name="day" value="{{ $bar['date'] }}" title="{{ $bar['date'] }}で絞り込む"
                        @class(['stats-chart-col', 'is-selected' => $bar['date'] === $stats['selectedDay']])>
                    <div>{{ $bar['count'] > 0 ? number_format($bar['count']) : '' }}</div>
                    <div class="stats-chart-area"><div class="stats-chart-bar" style="height: {{ $bar['percent'] }}%;"></div></div>
                    <div class="text-muted">{{ $bar['label'] }}</div>
                </button>
            @endforeach
        </form>

        <h2 class="h6">操作の多い人（{{ $stats['period'] }}）</h2>
        <table class="table table-sm w-auto mb-0">
            <thead>
                <tr>
                    <th>操作した人</th>
                    <th class="text-end">全体</th>
                    <th class="text-end">詳細の閲覧</th>
                    <th class="text-end">CSVダウンロード</th>
                    <th class="text-end">PDF出力</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stats['topOperators'] as $row)
                    <tr>
                        <td>
                            @if ($row->operator_type !== null)
                                {{ code_label('operation_log_subject', $row->operator_type) }}ID:{{ $row->operator_id }}　{{ $stats['topNames'][$row->operator_type][$row->operator_id] ?? '' }}
                            @else
                                <span class="text-muted">訪問者</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($row->total) }}</td>
                        <td class="text-end">{{ number_format($row->views) }}</td>
                        <td class="text-end">{{ number_format($row->csv_downloads) }}</td>
                        <td class="text-end">{{ number_format($row->pdfs) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">この期間の操作はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@include('admin.operation_logs._table', ['logs' => $logs, 'names' => $names, 'related' => $related])

{{ $logs->links('pagination::bootstrap-5') }}
@endsection
