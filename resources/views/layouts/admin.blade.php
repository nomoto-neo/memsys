<!DOCTYPE html>
{{-- 色の組み合わせ（navy・green・orange・rose・light）と、文字の大きさ（small・medium・large）。
     値はフレームワークのadmin.cssにある。ヘッダーのプルダウンで選んだものは、<head>の中のスクリプトがこれに替える --}}
<html lang="ja" class="nb-admin-html" data-theme="navy" data-font-size="medium">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理画面 - '.config('neobit.site.name'))</title>
    {{-- ヘッダーのプルダウンで前に選んだ色の組み合わせと文字の大きさを、画面を出す前に当てる（admin_layout.js が覚える） --}}
    <script>
        try {
            const theme = JSON.parse(localStorage.getItem('neobit.admin.theme'));
            if (['navy', 'green', 'orange', 'rose', 'light'].includes(theme)) {
                document.documentElement.dataset.theme = theme;
            }
            const fontSize = JSON.parse(localStorage.getItem('neobit.admin.fontSize'));
            if (['small', 'medium', 'large'].includes(fontSize)) {
                document.documentElement.dataset.fontSize = fontSize;
            }
        } catch (e) {}
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['vendor/neobit/core/resources/css/global.css', 'vendor/neobit/core/resources/css/admin.css', 'vendor/neobit/core/resources/js/global.js', 'vendor/neobit/core/resources/js/admin_layout.js'])
{{-- jQuery。使うときはこのBladeコメントを外す（summernoteを使う場合は必須）。 --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
{{-- --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
{{-- 画面ごとに追加する<head>の中身。jQuery・Bootstrapを前提にするライブラリ
     （summernoteなど）を画面から読み込めるよう、それらより後ろに置いている。 --}}
@stack('head-extra')
</head>
<body class="nb-admin-body">
{{-- 訪問者向けのlayouts/app.blade.phpとは別の、管理画面専用レイアウト。
     ここは常にadminガードのことだけを考えればよいので、
     app.blade.php側にあったような「今どちらの画面か」の分岐は不要。
     ログインしているときは、左にメニュー（admin/_menu.blade.php）を縦に並べる。
     広い画面では、ヘッダーの☰で固定表示とアイコン表示を切り替える（admin_layout.js）。
     狭い画面ではメニューを隠し、☰で左から重ねて出す（Bootstrapのoffcanvas-lg）。 --}}
<div class="nb-admin" data-nb-sidebar="fixed">
    @auth('admin')
        {{-- 前に選んだ表示を、画面を出す前に当てる。admin_layout.jsは画面を出した後に読まれ、
             そこで当てると一瞬だけ前の表示が見えるため --}}
        <script>
            try {
                if (JSON.parse(localStorage.getItem('neobit.admin.sidebar')) === 'rail') {
                    document.currentScript.parentElement.dataset.nbSidebar = 'rail';
                }
            } catch (e) {}
        </script>

        {{-- 左のメニュー。広い画面では本文の左に並び、狭い画面では☰で開く --}}
        <aside class="nb-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminMenu" aria-labelledby="adminMenuLabel">
            <div class="nb-sidebar-panel">
                <div class="nb-brand">
                    <span class="nb-brand-mark" aria-hidden="true">{{ mb_substr(config('neobit.site.name'), 0, 1) ?: '管' }}</span>
                    <span class="nb-brand-name" id="adminMenuLabel">{{ config('neobit.site.name') }} 管理画面</span>
                    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminMenu" aria-label="閉じる"></button>
                </div>
                @include('admin._menu')
            </div>
        </aside>
    @endauth

    <div class="nb-main">
        <header class="nb-header">
            @auth('admin')
                <button type="button" class="nb-header-toggle d-flex d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="メニューを開く">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <button type="button" class="nb-header-toggle d-none d-lg-flex" data-nb-sidebar-toggle aria-label="メニューをアイコンだけの表示にする">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            @endauth
            {{-- 広い画面では、サイトの名前はメニューの上に出す --}}
            <a class="nb-header-title @auth('admin') d-lg-none @endauth" href="{{ route('admin.dashboard') }}">{{ config('neobit.site.name') }} 管理画面</a>
            @auth('admin')
                <div class="nb-header-user">
                    {{-- 色の組み合わせの切り替え。プレゼンなどで色を見比べるためのもので、色を決めたサイトでは消してよい --}}
                    <label class="nb-header-setting d-none d-md-flex">
                        <span>色</span>
                        <select class="form-select form-select-sm w-auto" data-nb-theme-select>
                            <option value="navy">navy</option>
                            <option value="green">green</option>
                            <option value="orange">orange</option>
                            <option value="rose">rose</option>
                            <option value="light">light</option>
                        </select>
                    </label>
                    {{-- 文字の大きさの切り替え。要らないサイトでは消してよい --}}
                    <label class="nb-header-setting d-none d-md-flex">
                        <span>文字</span>
                        <select class="form-select form-select-sm w-auto" data-nb-font-size-select>
                            <option value="small">小</option>
                            <option value="medium">中</option>
                            <option value="large">大</option>
                        </select>
                    </label>
                    <span class="d-none d-sm-inline">{{ Auth::guard('admin')->user()->name }} さん</span>
                    <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="nb-header-logout">ログアウト</button>
                    </form>
                </div>
            @endauth
        </header>

        {{-- 本文。横幅の95%を使う。入力欄の少ない画面は、@section('content-width', '800px')のように書いて最大幅を絞る --}}
        <main class="nb-content" style="max-width: @yield('content-width', 'none');">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            {{-- パスキーの登録の案内。パスワードでログインした直後に、1回だけ出る --}}
            @include('neobit::_passkey_suggest')

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
