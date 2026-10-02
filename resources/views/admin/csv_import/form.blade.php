{{--
    CSV取り込みの画面（ファイルを選ぶ）。どのコーナーでも同じこの画面を使う
    （App\Support\CsvImport::csvImport()）。取り込みの設定は、コントローラーの
    csvImportSettings()（App\Support\CsvImportSettings）で決まる。
--}}
@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $settings->name }}：CSV取り込み</h1>
    @if ($backUrl !== null)
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">一覧へ戻る</a>
    @endif
</div>

<div class="card mb-3">
    <div class="card-body">
        <ul class="mb-0">
            @if ($settings->header)
                <li>1行目の見出しで列を判断します。ダウンロードしたCSVを直して、そのまま取り込めます。</li>
                <li>CSVに無い列の項目は、今の値のまま変わりません。</li>
            @else
                <li>1行目から、決まった順番で全部の列が並んでいるCSVとして読みます（見出しの行はありません）。</li>
            @endif
            @if ($isSave)
                @if ($settings->allowInsert)
                    <li>IDが空欄の行は、新しいデータとして追加します。</li>
                @else
                    <li>更新だけができます（IDが空欄の行はエラーになります）。</li>
                @endif
                <li>削除はできません。</li>
            @endif
            <li>文字コード：{{ $encodingLabel }}</li>
            <li>ファイルの大きさは{{ number_format($maxKb / 1024) }}MBまで@if ($settings->maxRows !== null)、データは{{ number_format($settings->maxRows) }}行まで@endifです。</li>
            <li>次の画面で、全部の行を検証した結果を確認してから取り込みます。</li>
        </ul>
    </div>
</div>

<form method="POST" action="{{ route($settings->route.'.confirm') }}" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
        <label for="csv_file" class="form-label">CSVファイル {!! required_mark() !!}</label>
        <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt,text/csv">
        <div class="invalid-feedback" data-item="csv_file">{{ $errors->first('csv_file') }}</div>
    </div>
    <button type="submit" class="btn btn-primary">確認する</button>
</form>
@endsection
