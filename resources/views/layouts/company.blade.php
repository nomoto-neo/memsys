<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '企業会員') - memsys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['resources/js/app.js'])

<style>
{{-- エラー欄（.invalid-feedback）は、中身が空でなければ表示する。layouts/app.blade.phpと同じ --}}
.invalid-feedback:not(:empty) {
    display: block;
}
</style>
{{-- 画面ごとに追加する<head>の中身。layouts/app.blade.phpと同じ考え方 --}}
@stack('head-extra')
</head>
<body class="bg-light">
{{-- 企業会員の画面のヘッダー。個人会員の画面（白）・管理画面（黒）と見分けられるよう、色を変えている --}}
<nav class="navbar navbar-expand navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('company.mypage') }}">memsys 企業会員</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            {{-- ログイン中の担当者は、企業会員のガードから取る --}}
            @auth('company')
                <span class="text-light small">
                    {{ Auth::guard('company')->user()->company->name }}
                    {{ Auth::guard('company')->user()->name }} さん
                </span>
                <a href="{{ route('company.mypage') }}" class="btn btn-sm btn-outline-light">マイページ</a>
                <form action="{{ route('company.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light">ログアウト</button>
                </form>
            @else
                <a href="{{ route('company.login') }}" class="btn btn-sm btn-outline-light">ログイン</a>
                <a href="{{ route('company.regist.create') }}" class="btn btn-sm btn-light">企業会員登録</a>
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
