@extends('layouts.admin')

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

@include('admin.operation_logs._table', ['logs' => $logs, 'names' => $names, 'related' => $related])

{{ $logs->links('pagination::bootstrap-5') }}
@endsection
