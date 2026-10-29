{{--
    アクセス制限（Neobit\Core\Http\Controllers\AccessRestrictionController）。メンテナンス画面と、管理画面の存在を
    隠すかを決め、それぞれで通すIPアドレスを書く。上から順に読めるよう、1つの枠に並べる。
    「常にサイト全体を閲覧できるIPアドレス」は管理画面の許すIPアドレスで、メンテナンス中の訪問者画面でも通す
    （Neobit\Core\Security\Gate の「管理画面に入れる人」）。IPアドレスの欄は1行に1つで、# から後ろはコメント。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。設定ファイル（.env）で
    常に許可しているIPアドレスは、それぞれの欄の下に出す。画面からは外せない。
--}}
@extends(config('neobit.layouts.admin'))

@section('content')
<h1 class="h4 mb-3">アクセス制限</h1>

<p class="small mb-4">あなたのIPアドレス：<strong>{{ $currentIp }}</strong></p>

<form method="POST" action="{{ route($routes['update']) }}">
    @csrf
    @method('PATCH')

    <div class="card mb-4">
        <div class="card-body">
            {{-- メンテナンス画面。チェックを外したときも値が届くよう、先に0を置く --}}
            <div class="form-check mb-4">
                <input type="hidden" name="site_restricted" value="0">
                <input type="checkbox" id="site-restricted" name="site_restricted" value="1" class="form-check-input"
                       @checked($siteRestricted)>
                <label for="site-restricted" class="form-check-label">メンテナンス画面を表示する</label>
            </div>

            {{-- メンテナンス中も通すIPアドレス --}}
            <div class="mb-4">
                <label for="site-ips" class="form-label mb-0">メンテナンス中も訪問者画面を閲覧できるIPアドレス</label>
                <p class="small text-muted mb-1">（IPアドレスは1行に1つ。# から後ろはコメントです。行の頭に # を付けると、その行は使いません。）</p>
                <textarea id="site-ips" name="site_ips" rows="5" class="form-control">{{ $siteIps }}</textarea>
                <div class="invalid-feedback" data-item="site_ips">{{ $errors->first('site_ips') }}</div>
                @if ($envSiteIps !== [])
                    <div class="small text-muted mt-1">
                        ※ 次のIPアドレスは、設定ファイルにより常に許可されています。
                        @foreach ($envSiteIps as $ip)
                            <div class="ms-3">{{ $ip }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 常に通すIPアドレス。管理画面の許すIPアドレスで、メンテナンス中の訪問者画面にも入れる --}}
            <div class="mb-4">
                <label for="admin-ips" class="form-label mb-0">常にサイト全体を閲覧できるIPアドレス</label>
                <p class="small text-muted mb-1">（メンテナンス中でも訪問者画面を閲覧でき、管理画面の存在を隠していても管理画面に入れます。）</p>
                <textarea id="admin-ips" name="admin_ips" rows="5" class="form-control">{{ $adminIps }}</textarea>
                <div class="invalid-feedback" data-item="admin_ips">{{ $errors->first('admin_ips') }}</div>
                @if ($envAdminIps !== [])
                    <div class="small text-muted mt-1">
                        ※ 次のIPアドレスは、設定ファイルにより常に許可されています。
                        @foreach ($envAdminIps as $ip)
                            <div class="ms-3">{{ $ip }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 管理画面の存在を隠す。許していない人には、ページが見つからない画面（404）を返す --}}
            <div class="form-check mb-1">
                <input type="hidden" name="admin_restricted" value="0">
                <input type="checkbox" id="admin-restricted" name="admin_restricted" value="1" class="form-check-input"
                       @checked($adminRestricted)>
                <label for="admin-restricted" class="form-check-label">管理画面の存在を隠す</label>
            </div>
            <p class="small text-muted mb-0">管理画面の存在を隠しても、「常にサイト全体を閲覧できるIPアドレス」の人は管理画面に入れます。</p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">保存する</button>
</form>
@endsection
