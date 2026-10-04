{{--
    操作ログの表。操作ログの一覧と、会員ごとの操作ログで使う。
    $logsは操作ログの行、$namesはApp\Models\OperationLog::subjectNames()の結果、
    $relatedはApp\Models\OperationLog::relatedRecords()の結果。

    操作した人と対象は「種類ID:番号　氏名」で出す。氏名は操作ログに残していないので、今の氏名を
    $names[種類][id]から引く。退会した会員のように、もう行が無いものは氏名が出ない。
    誰もログインしていない操作は、操作した人を「訪問者」と出す。
    CSVのダウンロードと取り込みは、対象の欄にCSVの名前を出す。
    変わった項目は、列の名前のまま出す。

    CSVと一斉メールの送信は、件数を別の記録（$related[操作ログのid]）から出す。
    件数を押すと、内訳のモーダルが開く。モーダルは表の下にまとめて置く。
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
                    @if ($log->csvName() !== null)
                        {{ $log->csvName() }}
                    @elseif ($log->target_type !== null)
                        {{ code_label('operation_log_subject', $log->target_type) }}ID:{{ $log->target_id }}　{{ $names[$log->target_type][$log->target_id] ?? '' }}
                    @endif
                </td>
                <td>
                    {{ implode('、', $log->changed_fields ?? []) }}
                    {{ $log->detailText() }}
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
    内訳のモーダル。<tbody>の直下には<tr>しか置けないので、表の外にまとめて置く。
    中身は、相手の記録の種類（CSVダウンロード・CSV取り込み・一斉メール）で分ける。
--}}
@foreach ($logs as $log)
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

                        @if ($record instanceof \App\Models\CsvDownloadLog)
                            <dl class="row mb-0">
                                <dt class="col-sm-4">名前</dt>
                                <dd class="col-sm-8">{{ $record->name }}</dd>

                                <dt class="col-sm-4">件数</dt>
                                <dd class="col-sm-8">{{ number_format($record->row_count) }}件</dd>

                                {{-- 検索条件は、記録してある形（検索の項目の名前：値）のまま出す --}}
                                <dt class="col-sm-4">検索条件</dt>
                                <dd class="col-sm-8">
                                    @forelse ($record->conditions ?? [] as $key => $value)
                                        <div>{{ $key }}：{{ is_array($value) ? implode(', ', $value) : $value }}</div>
                                    @empty
                                        なし
                                    @endforelse
                                </dd>
                            </dl>
                        @elseif ($record instanceof \App\Models\CsvImportLog)
                            <dl class="row mb-0">
                                <dt class="col-sm-4">名前</dt>
                                <dd class="col-sm-8">{{ $record->name }}</dd>

                                <dt class="col-sm-4">ファイル名</dt>
                                <dd class="col-sm-8">{{ $record->filename }}</dd>

                                <dt class="col-sm-4">文字コード</dt>
                                <dd class="col-sm-8">{{ $record->encoding }}</dd>

                                <dt class="col-sm-4">行数</dt>
                                <dd class="col-sm-8">{{ number_format($record->row_count) }}行</dd>

                                <dt class="col-sm-4">追加</dt>
                                <dd class="col-sm-8">{{ number_format($record->inserted_count) }}件</dd>

                                <dt class="col-sm-4">更新</dt>
                                <dd class="col-sm-8">{{ number_format($record->updated_count) }}件</dd>

                                <dt class="col-sm-4">変更なし</dt>
                                <dd class="col-sm-8">{{ number_format($record->unchanged_count) }}件</dd>
                            </dl>
                        @else
                            {{-- 一斉メールの送信。送信済みと失敗の件数は、送信が終わってから入る --}}
                            <dl class="row mb-3">
                                <dt class="col-sm-4">件名</dt>
                                <dd class="col-sm-8">{{ $record->subject }}</dd>

                                <dt class="col-sm-4">宛先のCSV</dt>
                                <dd class="col-sm-8">{{ $record->csv_filename }}</dd>

                                <dt class="col-sm-4">宛先</dt>
                                <dd class="col-sm-8">{{ number_format($record->recipient_count) }}件</dd>

                                <dt class="col-sm-4">送信済み</dt>
                                <dd class="col-sm-8">{{ $record->finished_at !== null ? number_format($record->sent_count).'件' : '－' }}</dd>

                                <dt class="col-sm-4">失敗</dt>
                                <dd class="col-sm-8">{{ $record->finished_at !== null ? number_format($record->failed_count).'件' : '－' }}</dd>

                                <dt class="col-sm-4">状態</dt>
                                <dd class="col-sm-8">
                                    {{ code_label('bulk_mail_status', $record->status) }}
                                    @if ($record->finished_at !== null)
                                        （{{ $record->finished_at->format('Y-m-d H:i') }}）
                                    @endif
                                </dd>
                            </dl>
                            <a href="{{ route('admin.bulk-mails.show', $record) }}" class="btn btn-sm btn-outline-secondary">送信の状況の画面を開く</a>
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
