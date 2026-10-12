<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.site_name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- jQuery。使うときはこのBladeコメントを外す（summernoteを使う場合は必須）。
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
--}}
{{-- 画面ごとに追加する<head>の中身。layouts/admin.blade.phpと同じ考え方
     （contact/create.blade.phpのCSRFトークン<meta>・resources/js/の
     読み込みなど、この画面だけで必要なものをここへ足す）。 --}}
@stack('head-extra')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand navbar-light bg-white border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">{{ config('app.site_name') }}</a>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('news.index') }}" class="nav-link">お知らせ</a>
            {{-- 固定ページ（管理画面の「固定ページ一覧」で書く）へのリンク。URLの名前を決め打ちで
                 書いているので、ページの名前を変えたり、ページを消したりしたら、ここも直す --}}
            <a href="{{ route('pages.show', 'aboutus') }}" class="nav-link">会社概要</a>
            <a href="{{ route('contact.create') }}" class="nav-link">お問い合わせ</a>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            {{-- @auth / @guest はAuth::check()の結果で表示を切り替えるBladeの専用構文。
                 @auth ... @else ... @endauth という書き方が使える。 --}}
            @auth
                <span class="text-muted small">{{ Auth::user()->name }} さん</span>
                <a href="{{ route('mypage') }}" class="btn btn-sm btn-outline-primary">マイページ</a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">ログアウト</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary">ログイン</a>
                <a href="{{ route('regist.create') }}" class="btn btn-sm btn-primary">会員登録</a>
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

    {{-- パスキーの登録の案内。パスワードでログインした直後に、1回だけ出る --}}
    @include('_passkey_suggest')

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
