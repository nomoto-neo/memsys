<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理画面 - '.config('app.site_name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['resources/js/app.js'])

<style>
.form-control[readonly] {
    background-color: var(--bs-secondary-bg);
    border: 0;
}
.form-control[readonly]:focus {
    box-shadow: none;
}
.form-check-input:disabled:checked {
    background-color: #666;
    border-color: #666;
}
.form-check-input:disabled:checked ~ .form-check-label {
    opacity: 1;
    color: inherit;
}
{{-- WYSIWYGエディタで作った本文の表示。挿入した画像は保存時に横幅を
     縮めているが、表示欄の幅より大きいことがあるので、欄の幅に収める。 --}}
.wysiwyg-content img {
    max-width: 100%;
    height: auto;
}
{{-- 確認・詳細の画面で、本文を枠の中に表示だけするとき。枠線のすぐ内側に、少し余白を取る。 --}}
.form-control.wysiwyg-content {
    padding: 10px;
}
{{-- エラー欄（.invalid-feedback）は、中身が空でなければ表示する。
     Bootstrapの標準では、直前の入力欄にis-invalidが付いたときだけ表示される。
     JavaScriptが動かない環境でもサーバー側のエラー文言を出すため、ここはCSSで行う。
     （:emptyは空白や改行も「中身あり」とみなすので、エラー欄の<div>と{{ }}の間に
     改行や空白を入れないこと。例：
     <div class="invalid-feedback" data-item="項目名">{{ $errors->first('項目名') }}</div>） --}}
.invalid-feedback:not(:empty) {
    display: block;
}
{{-- 一覧の表で、削除済み（論理削除）のデータの行を赤字にする。<tr>にrow-deletedを付ける。
     Bootstrapの表はセル（<td>）ごとに文字色を決めているので、<tr>に色を付けても
     セルには効かない。そのため<tr>の下の<td>を指定して色を変える。 --}}
.table > tbody > tr.row-deleted > td {
    color: var(--bs-danger);
}
</style>
{{-- jQuery。使うときはこのBladeコメントを外す（summernoteを使う場合は必須）。 --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
{{-- --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
{{-- 画面ごとに追加する<head>の中身。jQuery・Bootstrapを前提にするライブラリ
     （summernoteなど）を画面から読み込めるよう、それらより後ろに置いている。 --}}
@stack('head-extra')
</head>
<body class="bg-light">
{{-- 訪問者向けのlayouts/app.blade.phpとは別の、管理画面専用レイアウト。
     ここは常にadminガードのことだけを考えればよいので、
     app.blade.php側にあったような「今どちらの画面か」の分岐は不要。
     色（navbar-dark bg-dark）も変えて、見た目でも管理画面だと
     分かるようにしている。 --}}
<nav class="navbar navbar-expand navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">{{ config('app.site_name') }} 管理画面</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            @auth('admin')
                <span class="text-light small">{{ Auth::guard('admin')->user()->name }} さん</span>
                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light">ログアウト</button>
                </form>
            @endauth
        </div>
    </div>
</nav>
<div class="container" style="max-width: 1000px;">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
