{{--
    操作ログの表。操作ログの一覧と、会員ごとの操作ログで使う。
    $logsは操作ログの行、$namesはApp\Models\OperationLog::subjectNames()の結果。

    操作した人と対象は「種類ID:番号　氏名」で出す。氏名は操作ログに残していないので、今の氏名を
    $names[種類][id]から引く。退会した会員のように、もう行が無いものは氏名が出ない。
    誰もログインしていない操作は、操作した人を「訪問者」と出す。
    変わった項目は、列の名前のまま出す。
--}}
<table class="table table-striped table-sm align-middle">
    <thead>
        <tr>
            <th>日時</th>
            <th>操作した人</th>
            <th>操作</th>
            <th>対象</th>
            <th>変わった項目・補足</th>
            <th>IPアドレス</th>
            <th>端末</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                <td>
                    @if ($log->operator_type !== null)
                        {{ code_label('operation_log_subject', $log->operator_type) }}ID:{{ $log->operator_id }}　{{ $names[$log->operator_type][$log->operator_id] ?? '' }}
                    @else
                        <span class="text-muted">訪問者</span>
                    @endif
                </td>
                <td class="text-nowrap">{{ $log->action->label() }}</td>
                <td>
                    @if ($log->target_type !== null)
                        {{ code_label('operation_log_subject', $log->target_type) }}ID:{{ $log->target_id }}　{{ $names[$log->target_type][$log->target_id] ?? '' }}
                    @endif
                </td>
                <td>
                    {{ implode('、', $log->changed_fields ?? []) }}
                    {{ $log->detailText() }}
                </td>
                <td>{{ $log->ip }}</td>
                <td>{{ $log->device }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted">該当する操作ログが見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>
