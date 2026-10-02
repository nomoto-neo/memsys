{{--
    CSV取り込みの確認画面。どのコーナーでも同じこの画面を使う
    （App\Support\CsvImport::csvImportConfirm()）。

    - エラー・警告のある行は、最初の$previewRows行まで並べる。それより多ければ、
      省略した行数を出す（変更の例・処理だけのモードの読み込んだ内容も同じ）。
    - 正常な行は1行ずつ並べず、項目ごとの変更件数だけを出す。変更の例は項目ごとに
      開いて見られる（App\Support\CsvImport::csvImportChangeSummary()）。
    - 処理だけのモードは、読み込んだ内容を最初の$previewRows行まで、閉じた状態で出す。
    - 行番号は、どれも$row->lineLabel()で「2行目（山田太郎）」の形に出す（添える列は
      CsvImportSettingsのlabelColumn）。
    - エラーが1件も無いときだけ「取り込む」ボタンを出す。ボタンのフォームは
      合言葉（confirm_token）だけを送る。取り込むファイルはサーバーに一時保存してあり、
      実行のときにもう一度読んで検証し直す。
--}}
@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $settings->name }}：CSV取り込みの確認</h1>
    @if ($backUrl !== null)
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">一覧へ戻る</a>
    @endif
</div>

<p class="text-muted">
    ファイル：{{ $result->filename }}
    @if ($result->encoding)
        （文字コード：{{ $result->encoding }}）
    @endif
</p>

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
                @if ($isSave)
                    <th class="table-light">追加</th><td class="text-end">{{ number_format($result->count('insert')) }}件</td>
                    <th class="table-light">更新</th><td class="text-end">{{ number_format($result->count('update')) }}件</td>
                    <th class="table-light">変更なし</th><td class="text-end">{{ number_format($result->count('unchanged')) }}件</td>
                @else
                    <th class="table-light">処理する行</th><td class="text-end">{{ number_format($result->count('process')) }}件</td>
                @endif
                <th class="table-light">エラー</th><td class="text-end {{ $result->errorRows() !== [] ? 'text-danger fw-bold' : '' }}">{{ number_format(count($result->errorRows())) }}件</td>
                <th class="table-light">警告</th><td class="text-end">{{ number_format(count($result->warningRows())) }}件</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($result->warnings !== [])
    <div class="alert alert-warning">
        @foreach ($result->warnings as $message)
            <div>{{ $message }}</div>
        @endforeach
    </div>
@endif

@if ($issueRows !== [])
    <h2 class="h6 mt-4">エラー・警告のある行</h2>
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
                @foreach ($row->warnings as [$column, $message])
                    <tr class="table-warning">
                        <td>{{ $row->lineLabel() }}</td>
                        <td>{{ $column }}</td>
                        <td>{{ $message }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    @if ($issueCount > count($issueRows))
        <p class="text-muted small">ほかに{{ number_format($issueCount - count($issueRows)) }}行あります（最初の{{ $previewRows }}行だけを表示しています）。</p>
    @endif
@endif

@if ($changeSummary !== [])
    {{-- 正常な行は1行ずつ並べず、項目ごとの件数だけを出す。変更の例は「例を見る」で開く --}}
    <h2 class="h6 mt-4">項目ごとの変更件数</h2>
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 12rem;">項目</th>
                <th class="text-end" style="width: 6rem;">更新</th>
                <th class="text-end" style="width: 6rem;">追加</th>
                <th>変更の例</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($changeSummary as $label => $summary)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-end">{{ number_format($summary['update']) }}件</td>
                    <td class="text-end">{{ number_format($summary['insert']) }}件</td>
                    <td>
                        @php
                            $total = $summary['update'] + $summary['insert'];
                        @endphp
                        <details>
                            <summary class="small">例を見る（{{ $total > count($summary['examples']) ? '最初の'.count($summary['examples']).'件' : count($summary['examples']).'件' }}）</summary>
                            <table class="table table-sm mb-0 mt-2">
                                @foreach ($summary['examples'] as [$lineLabel, $action, $before, $after])
                                    <tr>
                                        <td class="text-nowrap">{{ $lineLabel }}</td>
                                        <td class="text-nowrap" style="width: 3rem;">{{ $action === 'insert' ? '追加' : '更新' }}</td>
                                        <td class="text-muted">{{ $before }}</td>
                                        <td class="text-nowrap" style="width: 1.5rem;">→</td>
                                        <td>{{ $after }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            @if ($total > count($summary['examples']))
                                <p class="text-muted small mb-0">ほかに{{ number_format($total - count($summary['examples'])) }}件あります（最初の{{ count($summary['examples']) }}件だけを表示しています）。</p>
                            @endif
                        </details>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($processRows !== [])
    {{-- 処理だけのモードは、読み込んだ内容を閉じた状態で出す（開いて確かめられる） --}}
    <details class="mt-4">
        <summary class="h6 d-inline">読み込んだ内容を見る（{{ $result->count('process') > count($processRows) ? '最初の'.count($processRows).'行' : count($processRows).'行' }}）</summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered align-middle text-nowrap">
                <thead class="table-light">
                    <tr>
                        <th>行</th>
                        @foreach (array_filter($result->headings, fn ($heading) => $heading !== '') as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($processRows as $row)
                        <tr>
                            <td>{{ $row->lineLabel() }}</td>
                            @foreach (array_filter($result->headings, fn ($heading) => $heading !== '') as $heading)
                                <td>{{ $row->cell($heading) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($result->count('process') > count($processRows))
            <p class="text-muted small">ほかに{{ number_format($result->count('process') - count($processRows)) }}行あります（最初の{{ $previewRows }}行だけを表示しています）。</p>
        @endif
    </details>
@endif

<div class="d-flex gap-2 mt-4">
    <a href="{{ route($settings->route) }}" class="btn btn-outline-secondary">ファイルを選び直す</a>

    @if ($token !== null)
        {{-- 送るのは合言葉だけ。二重送信は、合言葉が1回しか使えないことで防いでいる --}}
        <form method="POST" action="{{ route($settings->route.'.execute') }}">
            @csrf
            <input type="hidden" name="confirm_token" value="{{ $token }}">
            <button type="submit" class="btn btn-primary">取り込む</button>
        </form>
    @else
        <span class="align-self-center text-danger">エラーを直したCSVファイルを選び直してください。</span>
    @endif
</div>
@endsection
