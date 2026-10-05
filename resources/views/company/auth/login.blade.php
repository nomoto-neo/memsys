@extends('layouts.company')

@section('title', '企業会員ログイン')

{{--
    企業会員のログイン画面。企業ID・担当者ID・パスワードの3つを入力する。
    $inputは、入力欄に出す値（企業IDと担当者ID）。「企業IDと担当者IDを記憶する」で覚えた値か、
    入力エラーで戻ってきたときの入力が入っている。$idsRememberedは、覚えた値があるかどうか。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
    <h1 class="h3 mb-4">企業会員ログイン</h1>

    <form method="POST" action="{{ route('company.login.store') }}">
        @csrf

        <div class="mb-3">
            <label for="company_code" class="form-label">企業ID</label>
            <input id="company_code" type="text" name="company_code" value="{{ $input['company_code'] }}"
                   class="form-control" autocomplete="organization">
            <div class="invalid-feedback" data-item="company_code">{{ $errors->first('company_code') }}</div>
        </div>

        <div class="mb-3">
            <label for="login_id" class="form-label">担当者ID</label>
            <input id="login_id" type="text" name="login_id" value="{{ $input['login_id'] }}"
                   class="form-control" autocomplete="username">
            <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">パスワード</label>
            <input id="password" type="password" name="password"
                   class="form-control" autocomplete="current-password">
            <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
        </div>

        {{-- 次にこの画面を開いたときに、企業IDと担当者IDを入力済みで出す。覚えた値があれば、
             チェックを入れた状態で出す（外してログインすると、覚えた値を消す） --}}
        <div class="mb-2 form-check">
            <input type="checkbox" name="remember_ids" value="1" class="form-check-input" id="remember_ids"
                   @checked($idsRemembered)>
            <label class="form-check-label" for="remember_ids">企業IDと担当者IDを記憶する</label>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">ログイン状態を保持する</label>
        </div>

        <button type="submit" class="btn btn-primary">ログイン</button>
    </form>

    {{-- パスキーでのログイン。routes/web.phpにcompany.login.passkeyのルートがあるときだけ出す
         （ボタンの動きはresources/js/passkeys.js。「ログイン状態を保持する」のチェックも一緒に送る） --}}
    @if (Route::has('company.login.passkey'))
        @push('head-extra')
            <meta name="csrf-token" content="{{ csrf_token() }}">
            @vite(['resources/js/passkeys.js'])
        @endpush
        <div class="mt-4 pt-3 border-top" data-passkey-login hidden
             data-options-url="{{ route('company.login.passkey.options') }}"
             data-submit-url="{{ route('company.login.passkey') }}"
             data-remember="remember">
            <button type="button" class="btn btn-outline-primary">パスキーでログイン</button>
            <div class="text-danger small mt-2" data-passkey-message></div>
        </div>
    @endif

    <p class="mt-3">
        <a href="{{ route('company.password.forgot') }}">パスワードをお忘れの方はこちら</a>
    </p>
    </div>
</div>
@endsection
