@extends('layouts.admin')

{{--
    一斉メールの確認画面。件名・本文・添付ファイルと、宛先のCSVを読んだ結果を出す。
    CSVにエラーがあれば送信のボタンを出さず、エラーの行を並べる。
    件名と本文は、1行目の宛先の氏名を差し込んだ見本で出す。
--}}
@section('content')
<div class="card">
    <div class="card-header">一斉メールの確認</div>
    <div class="card-body">
        <h2 class="h6">メールの内容（{{ $sampleName }}さんに届く見本）</h2>
        <dl class="row">
            <dt class="col-sm-2">件名</dt>
            <dd class="col-sm-10">{{ $sampleSubject }}</dd>

            <dt class="col-sm-2">本文</dt>
            <dd class="col-sm-10">
                <div class="form-control" style="white-space: pre-wrap; height: auto;">{{ $sampleBody }}</div>
                <div class="form-text">本文の末尾に、配信停止のURLが宛先ごとに付きます。</div>
            </dd>

            <dt class="col-sm-2">添付ファイル</dt>
            <dd class="col-sm-10">
                @include('_ajax_upload_block', [
                    'model' => null,
                    'input' => $input,
                    'field' => 'attach',
                    'width' => 0,
                    'readonly' => ' readonly',
                ])
            </dd>
        </dl>

        <h2 class="h6 mt-4">宛先（{{ $result->filename }}）</h2>

        @if ($result->errors !== [])
            <div class="alert alert-danger">
                @foreach ($result->errors as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @else
            <table class="table table-bordered w-auto mb-3">
                <tbody>
                    <tr>
                        <th class="table-light">宛先</th><td class="text-end">{{ number_format(count($result->rows)) }}件</td>
                        <th class="table-light">エラー</th><td class="text-end {{ $issueCount > 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($issueCount) }}件</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if ($issueRows !== [])
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 14rem;">行</th>
                        <th style="width: 12rem;">列</th>
                        <th>内容</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($issueRows as $row)
                        @foreach ($row->errors as [$column, $message])
                            <tr class="table-danger">
                                <td>{{ $row->lineLabel() }}</td>
                                <td>{{ $column }}</td>
                                <td>{{ $message }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            @if ($issueCount > count($issueRows))
                <p class="text-muted small">ほかに{{ number_format($issueCount - count($issueRows)) }}行あります（最初の{{ $previewLimit }}行だけを表示しています）。</p>
            @endif
        @elseif ($token !== null)
            {{-- 宛先の一覧は閉じた状態で出し、開いて確かめる --}}
            <details class="mb-3">
                <summary>宛先を見る（最初の{{ $previewLimit }}件）</summary>
                <table class="table table-sm table-bordered mt-2">
                    <thead class="table-light">
                        <tr><th>行</th><th>氏名</th><th>メールアドレス</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr>
                                <td>{{ $row->line }}</td>
                                <td>{{ $row->validated['name'] }}</td>
                                <td>{{ $row->validated['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        @endif

        <div class="d-flex gap-2 mt-3">
            <form method="POST" action="{{ route('admin.bulk-mails.back') }}">
                @csrf
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-outline-secondary">戻る</button>
            </form>

            @if ($token !== null)
                <form method="POST" action="{{ route('admin.bulk-mails.store') }}">
                    @csrf
                    <input type="hidden" name="confirm_token" value="{{ $token }}">
                    @include('_confirm_hidden', ['input' => $input])
                    <button type="submit" class="btn btn-primary">{{ number_format(count($result->rows)) }}件に送信する</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
