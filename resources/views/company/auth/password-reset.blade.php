@extends('layouts.company')

@section('title', 'パスワードの再設定')

{{-- メールで届いた確認コードと、新しいパスワードを入力する。お知らせは、レイアウトが出す --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">パスワードの再設定</div>
            <div class="card-body">
                <p>
                    メールに記載された確認コードと、新しいパスワードを入力してください。
                </p>

                <form method="POST" action="{{ route('company.password.reset.update') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">
                            確認コード {!! $required['code'] ?? '' !!}
                        </label>
                        <input id="code" type="text" name="code"
                               class="form-control"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            新しいパスワード {!! $required['password'] ?? '' !!}
                        </label>
                        <input id="password" type="password" name="password"
                               autocomplete="new-password"
                               class="form-control">
                        <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
                        <div class="form-text">8文字以上で入力してください。</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">
                            新しいパスワード（確認） {!! $required['password_confirmation'] ?? '' !!}
                        </label>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               autocomplete="new-password"
                               class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary">パスワードを再設定する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
