{{--
    ベーシック認証（Neobit\Core\Http\Controllers\BasicAuthController）。訪問者画面と管理画面のそれぞれに、
    掛けるかとユーザーの追加と削除。認証するのはApacheで、サーバーのVirtualHostに決まりを書いたときだけ働く。
    入力のエラーは、範囲の名前のエラーの入れ物（$errors->getBag('site')など）にあるので、範囲ごとの枠にだけ出す。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。
--}}
@extends(config('neobit.layouts.admin'))

@section('content')
<h1 class="h4 mb-3">ベーシック認証</h1>

<p class="small text-muted mb-4">※ サーバーの設定（VirtualHost）に決まりを書いてあるときだけ働きます。書き方は利用ガイドにあります。</p>

@foreach ($areas as $area => $item)
    @php
        $bag = $errors->getBag($area);
        // 入力エラーで戻ってきたのが、この範囲の追加の欄か
        $addFailed = old('area') === $area;
    @endphp
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">{{ $item['label'] }}</h2>

            {{-- 掛けるか --}}
            <form method="POST" action="{{ route($routes['update'], $area) }}" class="d-flex flex-wrap align-items-center gap-3 mb-1">
                @csrf
                @method('PATCH')
                <div class="form-check mb-0">
                    {{-- チェックを外したときも値が届くよう、先に0を置く --}}
                    <input type="hidden" name="on" value="0">
                    <input type="checkbox" id="basic-auth-{{ $area }}" name="on" value="1" class="form-check-input" @checked($item['on'])>
                    <label for="basic-auth-{{ $area }}" class="form-check-label">ベーシック認証を掛ける</label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">保存する</button>
                <div class="invalid-feedback w-100" data-item="on">{{ $bag->first('on') }}</div>
            </form>
            @if ($area === 'site')
                <p class="small text-muted mb-3">死活監視とメルマガの配信停止のリンク、証明書の更新などに使う /.well-known には掛けません。</p>
            @else
                <p class="small text-muted mb-3">管理画面の下の全部のURLに掛けます。</p>
            @endif

            {{-- ユーザーの追加 --}}
            <form method="POST" action="{{ route($routes['store'], $area) }}" class="row g-2 align-items-start mb-1">
                @csrf
                <input type="hidden" name="area" value="{{ $area }}">
                <div class="col-md-4">
                    <input type="text" name="username" value="{{ $addFailed ? old('username') : '' }}" class="form-control form-control-sm"
                           placeholder="ユーザー名" aria-label="ユーザー名" autocomplete="off">
                    <div class="invalid-feedback" data-item="username">{{ $bag->first('username') }}</div>
                </div>
                <div class="col-md-4">
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="パスワード" aria-label="パスワード" autocomplete="new-password">
                    <div class="invalid-feedback" data-item="password">{{ $bag->first('password') }}</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary">追加</button>
                </div>
            </form>
            <p class="small text-muted mb-3">ユーザー名は半角の英数字と - _ . 、パスワードは8文字以上です。</p>

            {{-- ユーザーの一覧 --}}
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ユーザー名</th>
                            <th>追加した日</th>
                            <th style="width: 6rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($item['users'] as $user)
                            <tr>
                                <td class="text-break">{{ $user->username }}</td>
                                <td class="small">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#delete-basic-auth-user-{{ $user->id }}">削除</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted">ユーザーはいません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach

{{-- 削除の確認の窓。ユーザーごとに1つ --}}
@foreach ($areas as $area => $item)
    @foreach ($item['users'] as $user)
        <div class="modal fade" id="delete-basic-auth-user-{{ $user->id }}" tabindex="-1" aria-labelledby="delete-basic-auth-user-label-{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="delete-basic-auth-user-label-{{ $user->id }}">ユーザーの削除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body text-break">
                        {{ $item['label'] }}のユーザー「{{ $user->username }}」を削除します。この操作は取り消せません。よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route($routes['destroy'], $user) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">削除する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endforeach
@endsection
