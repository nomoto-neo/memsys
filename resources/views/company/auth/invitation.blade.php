@extends('layouts.company')

@section('title', '担当者の登録')

{{--
    招待された人の、担当者としての登録。招待のメールのリンクから開く。
    $invitationは招待、$tokenはリンクの値。メールアドレスは招待の宛先のものになるので、表示だけにしている。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">担当者の登録</div>
            <div class="card-body">
                <p class="text-muted">
                    {{ $invitation->company->name }} の担当者として登録します。
                    担当者IDとパスワードを決めてください。
                </p>

                <form method="POST" action="{{ route('company.invitation.store', ['token' => $token]) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">メールアドレス</label>
                        <div class="form-control-plaintext">{{ $invitation->email }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">お名前 {!! $required['name'] !!}</label>
                        <input id="name" type="text" name="name"
                               class="form-control"
                               value="{{ $input['name'] ?? '' }}" autofocus>
                        <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="login_id" class="form-label">担当者ID {!! $required['login_id'] !!}</label>
                        <input id="login_id" type="text" name="login_id"
                               class="form-control"
                               autocomplete="username"
                               value="{{ $input['login_id'] ?? '' }}">
                        <div class="form-text">
                            ログインのときに、企業ID（{{ $invitation->company->code }}）と一緒に入力します。
                            半角の英数字と記号（_ . -）が使えます。
                        </div>
                        <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">パスワード {!! $required['password'] !!}</label>
                        <input id="password" type="password" name="password"
                               autocomplete="new-password"
                               class="form-control">
                        <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">パスワード（確認） {!! $required['password_confirmation'] !!}</label>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               autocomplete="new-password"
                               class="form-control">
                        <div class="invalid-feedback" data-item="password_confirmation">{{ $errors->first('password_confirmation') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">登録する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
