@extends('layouts.company')

@section('title', '確認コードの入力')

{{-- 初回のログインでの登録の、確認コードの入力。入力したメールアドレスに送ったコードを入力すると、
     担当者の情報を保存して、ログインが完了する --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">確認コードの入力</div>
            <div class="card-body">
                <p>
                    {{ $email }} 宛に確認コードを送信しました。
                    メールに記載された6桁の数字を入力すると、登録が完了します。
                </p>

                <form method="POST" action="{{ route('company.login.setup.verify.confirm') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">確認コード</label>
                        <input id="code" type="text" name="code"
                               class="form-control"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">登録する</button>
                </form>

                <form method="POST" action="{{ route('company.login.setup.verify.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
                </form>

                {{-- メールアドレスを間違えていた場合など。登録フォームに戻り、
                     パスワードと電話番号以外の入力内容はそのまま引き継ぐ。 --}}
                <form method="POST" action="{{ route('company.login.setup.verify.back') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">入力内容を修正する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
