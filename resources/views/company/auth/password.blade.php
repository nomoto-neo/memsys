@extends('layouts.company')

@section('title', 'パスワード変更')

{{--
    企業会員の担当者のパスワード変更。本人確認は、メールで送る確認コードで行う。確認コードは、
    この画面を開いた時点で自動的に送信済みになっている。$sendFailedは、その送信に失敗したかどうか。
    お知らせ（session('status')・session('error')）は、レイアウトが出す。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">パスワード変更</div>
            <div class="card-body">
                @if ($sendFailed)
                    <div class="alert alert-danger">
                        確認コードの送信に失敗しました。お手数ですが「確認コードを再送する」からもう一度お試しください。
                    </div>
                @endif

                <p>
                    ご登録のメールアドレス宛に確認コードを送信しました。
                    メールに記載された6桁の数字と、新しいパスワードを入力してください。
                </p>
                <p class="small text-muted">
                    パスワードを変更すると、ほかの端末でのログインと、「このデバイスを記憶する」で記憶した端末{{ Route::has('company.mypage.passkeys') ? '、登録されているパスキー' : '' }}は、すべて無効になります。
                    変更したことは、ご登録のメールアドレスにお知らせします。
                </p>

                <form method="POST" action="{{ route('company.password.update') }}">
                    @csrf
                    {{-- HTMLフォームはGET/POSTしか送れないため、実際にはPOSTで送りつつ
                         このhiddenフィールド(_method)でPATCHとして扱ってほしいことを伝える。 --}}
                    @method('PATCH')

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
                        {{-- confirmedルールによるエラーは"password"の側に付くので、この欄にはエラー欄を置かない --}}
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               autocomplete="new-password"
                               class="form-control">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">変更する</button>
                        <a href="{{ route('company.mypage') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('company.password.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
