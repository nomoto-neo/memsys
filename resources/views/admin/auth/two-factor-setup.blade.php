@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">2段階認証の登録</div>
            <div class="card-body">
                <p>
                    ログインID「<strong>{{ $staff->login_id }}</strong>」用の2段階認証を設定します。
                    Google Authenticator・Microsoft Authenticatorなどの認証アプリで、
                    下のQRコードを読み取ってください。読み取ると、認証アプリの画面に
                    6桁の数字（30秒ごとに変わります）が表示されます。
                </p>

                <div class="text-center my-4">
                    <img src="{{ $qrCodeSvgDataUri }}" alt="QRコード" width="240" height="240">
                </div>

                <p class="text-muted small">
                    カメラでQRコードを読み取れない場合は、次のキーを認証アプリに手入力してください。<br>
                    <code>{{ $secret }}</code>
                </p>

                <form method="POST" action="{{ route('admin.twoFactor.verify') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">
                            認証アプリに表示されている、ログインID「{{ $staff->login_id }}」の6桁の数字
                        </label>
                        <input id="code" type="text" name="code"
                               class="form-control"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">登録を完了する</button>
                </form>

                <div class="mt-3">
                    <a href="{{ route('admin.login') }}">ログイン画面に戻る</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
