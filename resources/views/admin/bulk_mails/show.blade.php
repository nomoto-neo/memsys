@extends('layouts.admin')

{{-- 1件の一斉メールの送信の状況。送信中は$refreshSecondsごとに読み直して件数を新しくする --}}
@if ($refreshSeconds !== null)
    @push('head-extra')
        <meta http-equiv="refresh" content="{{ $refreshSeconds }}">
    @endpush
@endif

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">一斉メールの送信の状況</h1>
    <a href="{{ route('admin.bulk-mails.index') }}" class="btn btn-outline-secondary btn-sm">送信の履歴へ</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <table class="table table-bordered w-auto mb-3">
            <tbody>
                <tr>
                    <th class="table-light">状態</th>
                    <td>{{ code_label('bulk_mail_status', $bulkMail->status->value) }}</td>
                    <th class="table-light">宛先</th><td class="text-end">{{ number_format($bulkMail->recipient_count) }}件</td>
                    <th class="table-light">送信済み</th><td class="text-end">{{ number_format($counts['sent']) }}件</td>
                    <th class="table-light">失敗</th><td class="text-end {{ $counts['failed'] > 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($counts['failed']) }}件</td>
                    <th class="table-light">残り</th><td class="text-end">{{ number_format($counts['remaining']) }}件</td>
                </tr>
            </tbody>
        </table>

        @if ($refreshSeconds !== null)
            <p class="text-muted small">送信中です。この画面は{{ $refreshSeconds }}秒ごとに読み直します。画面を閉じても送信は続きます。</p>
        @endif

        <dl class="row mb-0">
            <dt class="col-sm-2">開始</dt>
            <dd class="col-sm-10">{{ $bulkMail->created_at->format('Y年n月j日 H:i') }}</dd>

            <dt class="col-sm-2">完了</dt>
            <dd class="col-sm-10">{{ $bulkMail->finished_at?->format('Y年n月j日 H:i') ?? '－' }}</dd>

            <dt class="col-sm-2">宛先のCSV</dt>
            <dd class="col-sm-10">{{ $bulkMail->csv_filename }}</dd>

            <dt class="col-sm-2">件名</dt>
            <dd class="col-sm-10">{{ $bulkMail->subject }}</dd>

            <dt class="col-sm-2">本文</dt>
            <dd class="col-sm-10"><div class="form-control" style="white-space: pre-wrap; height: auto;">{{ $bulkMail->body }}</div></dd>

            <dt class="col-sm-2">添付ファイル</dt>
            <dd class="col-sm-10">
                @if ($bulkMail->attach_url !== null)
                    <a href="{{ $bulkMail->attach_url }}" target="_blank" rel="noopener">{{ $bulkMail->attach_origin ?: '添付ファイル' }}</a>
                @else
                    なし
                @endif
            </dd>
        </dl>
    </div>
</div>
@endsection
