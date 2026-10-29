{{--
    操作ログの表。操作ログの一覧と、会員ごとの操作ログで使う。
    $recordsは操作ログの行、$namesはNeobit\Core\Models\OperationLog::modelNames()の結果、
    $relatedはNeobit\Core\Models\OperationLog::relatedRecords()の結果。

    操作した人と対象は「種類ID:番号　氏名」で出す。氏名は操作ログに残していないので、今の氏名を
    $names[種類][id]から引く。退会した会員のように、もう行が無いものは氏名が出ない。
    誰もログインしていない操作は、操作した人を「訪問者」と出す。
    ログインの失敗のように、対象の種類だけが残っている行は、種類だけを出す。
    CSVのダウンロードと取り込みは、対象の欄にCSVの名前を出す。
    変わった項目は、列の名前のまま出す。

    CSVと一斉メールの送信は、件数を別の記録（$related[操作ログのid]）から出す。
    件数を押すと、内訳のモーダルが開く。モーダルは表の下にまとめて置く。
    変わった項目と補足が長い（OperationLog::hasLongChangesText()）ときは、切って「…」と「全文」の
    リンクを出し、押すと全文のモーダルが開く。全文は、補足の項目ごとに1行ずつ出す。
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
        @forelse ($records as $log)
            <tr>
                <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                <td>
                    @if ($log->operator_type !== null)
                        {{ code_label('operation_log_model', $log->operator_type, $log->operator_type) }}ID:{{ $log->operator_id }}　{{ $names[$log->operator_type][$log->operator_id] ?? '' }}
                    @else
                        <span class="text-muted">訪問者</span>
                    @endif
                </td>
                <td class="text-nowrap">{{ $log->action->label() }}</td>
                <td>
                    @if ($log->csvName() !== null)
                        {{ $log->csvName() }}
                    @elseif ($log->target_type !== null && $log->target_id === null)
                        {{ code_label('operation_log_model', $log->target_type, $log->target_type) }}
                    @elseif ($log->target_type !== null)
                        {{ code_label('operation_log_model', $log->target_type, $log->target_type) }}ID:{{ $log->target_id }}　{{ $names[$log->target_type][$log->target_id] ?? '' }}
                    @endif
                </td>
                <td>
                    {{-- 長いときは切って、全文をモーダルで出す --}}
                    @if ($log->hasLongChangesText())
                        {{ mb_substr($log->changesText(), 0, $log::DETAIL_SHOWN_LENGTH) }}…
                        <a href="#" data-bs-toggle="modal" data-bs-target="#operationLogFull-{{ $log->id }}">全文</a>
                    @else
                        {{ $log->changesText() }}
                    @endif
                    @isset($related[$log->id])
                        <a href="#" data-bs-toggle="modal" data-bs-target="#operationLogDetail-{{ $log->id }}">{{ $log->countSummary($related[$log->id]) }}</a>
                    @endisset
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

{{--
    長い補足の全文のモーダル。変わった項目を1行、補足の項目を1行ずつ出す。
--}}
@foreach ($records as $log)
    @if ($log->hasLongChangesText())
        <div class="modal fade" id="operationLogFull-{{ $log->id }}" tabindex="-1"
             aria-labelledby="operationLogFullLabel-{{ $log->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="operationLogFullLabel-{{ $log->id }}">{{ $log->action->label() }}の補足</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                        @if (($log->changed_fields ?? []) !== [])
                            <div>変わった項目：{{ implode('、', $log->changed_fields) }}</div>
                        @endif
                        @foreach ($log->detailLines() as $line)
                            <div class="text-break">{{ $line }}</div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

{{--
    内訳のモーダル。<tbody>の直下には<tr>しか置けないので、表の外にまとめて置く。
    中身は、相手の記録が返す項目を並べる。記録の種類（CSVの記録、アプリの一斉メールの記録など）は見ない。
--}}
@foreach ($records as $log)
    @isset($related[$log->id])
        @php($record = $related[$log->id])
        <div class="modal fade" id="operationLogDetail-{{ $log->id }}" tabindex="-1"
             aria-labelledby="operationLogDetailLabel-{{ $log->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="operationLogDetailLabel-{{ $log->id }}">{{ $log->action->label() }}の内訳</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>

                        {{-- 中身は、記録の側が返す（OperationLogRelatedRecord::operationLogDetails()）。
                             値が配列なら1行ずつ出し、空なら「なし」と出す --}}
                        <dl class="row mb-0">
                            @foreach ($record->operationLogDetails() as $label => $value)
                                <dt class="col-sm-4">{{ $label }}</dt>
                                <dd class="col-sm-8">
                                    @if (is_array($value))
                                        @forelse ($value as $line)
                                            <div>{{ $line }}</div>
                                        @empty
                                            なし
                                        @endforelse
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                        @if ($record->operationLogUrl() !== null)
                            <a href="{{ $record->operationLogUrl() }}" class="btn btn-sm btn-outline-secondary mt-3">詳しい画面を開く</a>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    @endisset
@endforeach
