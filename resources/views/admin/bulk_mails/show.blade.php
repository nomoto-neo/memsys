@extends('layouts.admin')

{{--
    1件の一斉メールの送信の状況。送信中は$refreshSeconds秒ごとに読み直して、件数を新しくする。
    読み直しまでの残りの秒数を、行の最後に「（あと3秒）」と出して1秒ずつ減らす。
    送信が終わっていれば、同じ場所に「送信処理は完了しました」を出す。失敗が無ければ緑、
    1件でもあれば赤にして、失敗の件数を添える。過去の送信を履歴から開いたときも、同じ表示になる。

    読み直しは、画面の下のスクリプトが、残りの秒数を減らして0になったときに行う。
    スクリプトが動かないブラウザでは、<noscript>の中の<meta>が同じ秒数で読み直す。
--}}
@if ($refreshSeconds !== null)
    @push('head-extra')
        <noscript><meta http-equiv="refresh" content="{{ $refreshSeconds }}"></noscript>
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
            {{-- 残りの秒数は、スクリプトがdata-secondsの秒数から数えて、この<span>に入れる --}}
            <p class="text-muted small">送信中です。画面を閉じても送信処理は続きます。この画面は{{ $refreshSeconds }}秒ごとに読み直します。<span id="refresh-countdown" data-seconds="{{ $refreshSeconds }}"></span></p>
        @elseif ($counts['failed'] > 0)
            <p class="text-danger small fw-bold">送信処理は完了しました（失敗 {{ number_format($counts['failed']) }}件）。</p>
        @else
            <p class="text-success small fw-bold">送信処理は完了しました。</p>
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

@if ($refreshSeconds !== null)
    @push('scripts')
    <script>
        {{-- 読み直しまでの残りの秒数を出し、1秒ずつ減らして、0になったら読み直す。
             表示と読み直しを1つのタイマーで行うので、秒数がずれない --}}
        document.addEventListener('DOMContentLoaded', () => {
            const countdown = document.getElementById('refresh-countdown');
            let remaining = Number(countdown.dataset.seconds);

            const show = () => {
                countdown.textContent = `（あと${remaining}秒）`;
            };

            show();

            const timer = setInterval(() => {
                remaining--;
                show();

                if (remaining <= 0) {
                    clearInterval(timer);
                    location.reload();
                }
            }, 1000);
        });
    </script>
    @endpush
@endif
