@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">管理者ログイン</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="login_id" class="form-label">ログインID</label>
                        <input id="login_id" type="text" name="login_id"
                               class="form-control"
                               value="{{ old('login_id') }}" autofocus>
                        <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">パスワード</label>
                        <input id="password" type="password" name="password"
                               class="form-control">
                        <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">ログイン状態を保持する</label>
                    </div>

                    <button type="submit" class="btn btn-primary">ログイン</button>
                </form>

                {{-- パスキーでのログイン。routes/web.phpにadmin.login.passkeyのルートがあるときだけ出す
                     （ボタンの動きはresources/js/passkeys.js。パスキーで通ったときは、2段階目の
                     TOTPも求めない）。 --}}
                @if (Route::has('admin.login.passkey'))
                    @push('head-extra')
                        <meta name="csrf-token" content="{{ csrf_token() }}">
                        @vite(['resources/js/passkeys.js'])
                    @endpush
                    <div class="mt-4 pt-3 border-top" data-passkey-login hidden
                         data-options-url="{{ route('admin.login.passkey.options') }}"
                         data-submit-url="{{ route('admin.login.passkey') }}"
                         data-remember="remember">
                        <button type="button" class="btn btn-outline-primary">パスキーでログイン</button>
                        <div class="text-danger small mt-2" data-passkey-message></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
