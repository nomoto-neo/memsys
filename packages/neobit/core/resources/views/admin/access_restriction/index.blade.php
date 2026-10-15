{{--
    アクセス制限（Neobit\Core\Http\Controllers\AccessRestrictionController）。サイト（訪問者画面）と管理画面の
    それぞれに、制限を掛けるかと、開けるIPアドレスを決める。2つは互いに影響しない。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。設定ファイル（.env）で
    常に許可しているIPアドレスは、それぞれの欄の上に出す。画面からは外せない。
--}}
@extends(config('neobit.layouts.admin'))

@section('content')
<h1 class="h4 mb-3">アクセス制限</h1>

<p class="small mb-4">あなたのIPアドレス：<strong>{{ $currentIp }}</strong></p>

<form method="POST" action="{{ route($routes['update']) }}">
    @csrf
    @method('PATCH')

    {{-- サイト（訪問者画面）の制限 --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">サイト（訪問者画面）</h2>
            <div class="form-check mb-2">
                {{-- チェックを外したときも値が届くよう、先に0を置く --}}
                <input type="hidden" name="site_restricted" value="0">
                <input type="checkbox" id="site-restricted" name="site_restricted" value="1" class="form-check-input"
                       @checked($siteRestricted)>
                <label for="site-restricted" class="form-check-label">アクセス制限を掛ける</label>
            </div>
            <p class="small text-muted mb-3">
                チェックを入れると、下のIPアドレス以外の人には訪問者画面にメンテナンス中の画面が表示されます。
            </p>

            <label for="site-ips" class="form-label">サイトを開けるIPアドレス</label>
            <textarea id="site-ips" name="site_ips" rows="5" class="form-control"
                      placeholder="1行に1つ。カンマで区切ってもかまいません">{{ $siteIps }}</textarea>
            <div class="invalid-feedback" data-item="site_ips">{{ $errors->first('site_ips') }}</div>
            @if ($envSiteIps !== [])
                <div class="small text-muted mb-2">
                    ※ 次のIPアドレスは、設定ファイルで常に許可されています。
                    @foreach ($envSiteIps as $ip)
                        <div class="ms-3">{{ $ip }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- 管理画面の制限 --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">管理画面</h2>
            <div class="form-check mb-2">
                <input type="hidden" name="admin_restricted" value="0">
                <input type="checkbox" id="admin-restricted" name="admin_restricted" value="1" class="form-check-input"
                       @checked($adminRestricted)>
                <label for="admin-restricted" class="form-check-label">アクセス制限を掛ける</label>
            </div>
            <p class="small text-muted mb-3">
                チェックを入れると、下のIPアドレス以外の人は管理画面を閲覧できなくなります。
            </p>

            <label for="admin-ips" class="form-label">管理画面を開けるIPアドレス</label>
            <textarea id="admin-ips" name="admin_ips" rows="5" class="form-control"
                      placeholder="1行に1つ。カンマで区切ってもかまいません">{{ $adminIps }}</textarea>
            <div class="invalid-feedback" data-item="admin_ips">{{ $errors->first('admin_ips') }}</div>
            @if ($envAdminIps !== [])
                <div class="small text-muted mb-2">
                    ※ 次のIPアドレスは、設定ファイルで常に許可されています。
                    @foreach ($envAdminIps as $ip)
                        <div class="ms-3">{{ $ip }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <button type="submit" class="btn btn-primary">保存する</button>
</form>
@endsection
