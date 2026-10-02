@extends('layouts.app')

@section('title', 'ログイン')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
    <h1 class="h3 mb-4">ログイン</h1>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">メールアドレス</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control">
            <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label">パスワード</label>
            <input type="password" name="password"
                   class="form-control">
            <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">ログイン状態を保持する</label>
        </div>

        <button type="submit" class="btn btn-primary">ログイン</button>
    </form>

    {{-- パスキーでのログイン。routes/web.phpにlogin.passkeyのルートがあるときだけ出す
         （ボタンの動きはresources/js/passkeys.js。「ログイン状態を保持する」の
         チェックも一緒に送る）。 --}}
    @if (Route::has('login.passkey'))
        @push('head-extra')
            <meta name="csrf-token" content="{{ csrf_token() }}">
            @vite(['resources/js/passkeys.js'])
        @endpush
        <div class="mt-4 pt-3 border-top" data-passkey-login hidden
             data-options-url="{{ route('login.passkey.options') }}"
             data-submit-url="{{ route('login.passkey') }}"
             data-remember="remember">
            <button type="button" class="btn btn-outline-primary">パスキーでログイン</button>
            <div class="text-danger small mt-2" data-passkey-message></div>
        </div>
    @endif

    <p class="mt-3">
        <a href="{{ route('password.forgot') }}">パスワードをお忘れの方はこちら</a>
    </p>
    </div>
</div>
@endsection
