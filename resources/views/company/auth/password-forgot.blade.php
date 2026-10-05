@extends('layouts.company')

@section('title', 'パスワードをお忘れの方')

{{--
    パスワードを忘れた担当者が、確認コードの送り先を決めるための入力。
    企業ID・担当者ID・メールアドレスの3つが、登録の内容と合ったときだけ、確認コードを送る。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">パスワードをお忘れの方</div>
            <div class="card-body">
                <p>
                    企業ID・担当者IDと、ご登録のメールアドレスを入力してください。
                    確認コードを記載したメールをお送りします。
                </p>

                <form method="POST" action="{{ route('company.password.forgot.send') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="company_code" class="form-label">企業ID {!! $required['company_code'] !!}</label>
                        <input id="company_code" type="text" name="company_code" value="{{ old('company_code') }}"
                               class="form-control" autocomplete="organization" autofocus>
                        <div class="invalid-feedback" data-item="company_code">{{ $errors->first('company_code') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="login_id" class="form-label">担当者ID {!! $required['login_id'] !!}</label>
                        <input id="login_id" type="text" name="login_id" value="{{ old('login_id') }}"
                               class="form-control" autocomplete="username">
                        <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="form-control">
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認コードを送信する</button>
                        <a href="{{ route('company.login') }}" class="btn btn-outline-secondary">ログイン画面へ戻る</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
