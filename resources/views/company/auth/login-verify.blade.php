@extends('layouts.company')

@section('title', '確認コードの入力')

{{-- 企業会員のログインの2段階目。担当者のメールアドレスに送った確認コードを入力する --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">確認コードの入力</div>
            <div class="card-body">
                <p>
                    ご登録のメールアドレス宛に、確認コードを送信しました。
                    メールに記載された6桁の数字を入力してください。
                </p>

                <form method="POST" action="{{ route('company.login.verify.confirm') }}">
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
                            このデバイスを記憶する（次回から30日間、確認コードの入力を省略します）
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">ログインする</button>
                </form>

                <form method="POST" action="{{ route('company.login.verify.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
