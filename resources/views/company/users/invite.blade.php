@extends('layouts.company')

@section('title', '担当者の招待')

{{-- 担当者の招待。メールアドレスを入力すると、その人に登録の画面へのリンクが届く。
     担当者IDとパスワードは、招待された本人が決める --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">担当者の招待</div>
            <div class="card-body">
                <p class="text-muted">
                    招待する方のメールアドレスを入力してください。登録の画面へのリンクを、メールで送ります。
                    リンクの期限は{{ $validDays }}日です。
                    担当者IDとパスワードは、招待された方がご自身で決めます。
                </p>

                <form method="POST" action="{{ route('company.users.invite.send') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email"
                               class="form-control"
                               value="{{ $input['email'] ?? '' }}" autofocus>
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">招待のメールを送る</button>
                        <a href="{{ route('company.users.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
