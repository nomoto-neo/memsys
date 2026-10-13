<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理画面 - '.config('app.site_name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
{{-- 本文の横幅。列の多い一覧の画面は、@section('content-width', '1200px')のように書いて広げる --}}
<div class="container" style="max-width: @yield('content-width', '1000px');">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- パスキーの登録の案内。パスワードでログインした直後に、1回だけ出る --}}
    @include('neobit::_passkey_suggest')

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
