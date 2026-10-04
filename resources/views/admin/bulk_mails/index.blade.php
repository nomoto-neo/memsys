@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">一斉メールの送信の履歴</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.bulk-mail-templates.index') }}" class="btn btn-outline-primary btn-sm">文面の管理</a>
        <a href="{{ route('admin.bulk-mails.create') }}" class="btn btn-primary btn-sm">一斉メールを送る</a>
    </div>
</div>

@if ($bulkMails->isEmpty())
    <p class="text-muted">まだ送っていません。</p>
@else
    <table class="table table-hover align-middle bg-white">
        <thead>
            <tr>
                <th>開始</th>
                <th>件名</th>
                <th class="text-end">宛先</th>
                <th class="text-end">送信済み</th>
                <th class="text-end">失敗</th>
                <th>状態</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bulkMails as $bulkMail)
                <tr>
                    <td class="text-nowrap">{{ $bulkMail->created_at->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('admin.bulk-mails.show', $bulkMail) }}">{{ $bulkMail->subject }}</a></td>
                    <td class="text-end">{{ number_format($bulkMail->recipient_count) }}</td>
                    {{-- 送信中の件数は状況の画面で見る。ここは送り終えたときに写した件数 --}}
                    <td class="text-end">{{ $bulkMail->finished_at ? number_format($bulkMail->sent_count) : '－' }}</td>
                    <td class="text-end {{ $bulkMail->failed_count > 0 ? 'text-danger' : '' }}">{{ $bulkMail->finished_at ? number_format($bulkMail->failed_count) : '－' }}</td>
                    <td>{{ code_label('bulk_mail_status', $bulkMail->status->value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $bulkMails->links() }}
@endif
@endsection
