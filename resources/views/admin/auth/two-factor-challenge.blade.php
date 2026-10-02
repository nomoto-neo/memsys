@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">2段階認証</div>
            <div class="card-body">
                <p>
                    ログインID「<strong>{{ $staff->login_id }}</strong>」の認証アプリに表示されている、
                    6桁の数字を入力してください。
                </p>

                <form method="POST" action="{{ route('admin.twoFactor.verify') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">確認コード</label>
                        <input id="code" type="text" name="code"
                               class="form-control"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember_device" value="1" class="form-check-input" id="remember_device">
                        <label class="form-check-label" for="remember_device">
                            この端末を信頼する（次回から30日間、この端末では確認コードの入力を省略します）
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">ログインする</button>
                </form>

                <hr>

                <p class="text-muted small mb-2">
                    スマートフォンが手元に無い場合は、登録時に発行されたバックアップコードでもログインできます。
                </p>

                <form method="POST" action="{{ route('admin.twoFactor.verify') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="backup_code" class="form-label">バックアップコード</label>
                        <input id="backup_code" type="text" name="backup_code"
                               class="form-control" placeholder="0000-0000">
                        <div class="invalid-feedback" data-item="backup_code">{{ $errors->first('backup_code') }}</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember_device" value="1" class="form-check-input" id="remember_device_backup">
                        <label class="form-check-label" for="remember_device_backup">
                            この端末を信頼する（次回から30日間、この端末では確認コードの入力を省略します）
                        </label>
                    </div>

                    <button type="submit" class="btn btn-outline-secondary">バックアップコードでログインする</button>
                </form>

                <div class="mt-3">
                    <a href="{{ route('admin.login') }}">ログイン画面に戻る</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
