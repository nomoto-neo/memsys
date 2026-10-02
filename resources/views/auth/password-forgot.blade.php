@extends('layouts.app')

@section('title', 'パスワードをお忘れの方')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">パスワードをお忘れの方</div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <p>
                    ご登録のメールアドレスを入力してください。
                    確認コードを記載したメールをお送りします。
                </p>

                <form method="POST" action="{{ route('password.forgot.send') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="form-control" autofocus>
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">確認コードを送信する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
