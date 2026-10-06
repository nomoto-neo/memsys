@extends('layouts.app')

@section('title', '確認コードの入力')

{{-- プロフィール編集でメールアドレスを変えたときだけ通る画面。入力内容は、
     確認コードが入力できた時点で保存される（App\Support\EmailChange）。 --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">確認コードの入力</div>
            <div class="card-body">
                <p>
                    新しいメールアドレス {{ $email }} 宛に確認コードを送信しました。
                    メールに記載された6桁の数字を入力すると、プロフィールの更新が完了します。
                </p>
                <p class="small text-muted">
                    入力いただいた内容は、まだ保存されていません。
                </p>

                <form method="POST" action="{{ route('mypage.email.confirm') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">確認コード</label>
                        <input id="code" type="text" name="code"
                               class="form-control"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                        <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">更新する</button>
                </form>

                <form method="POST" action="{{ route('mypage.email.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
                </form>

                {{-- メールアドレスを間違えていた場合など。入力内容を引き継いで、編集画面に戻る。 --}}
                <form method="POST" action="{{ route('mypage.email.back') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">入力内容を修正する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
