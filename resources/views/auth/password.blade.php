@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">パスワード変更</div>
            <div class="card-body">
                {{--
                    本人確認は、メールで送る確認コードで行う。確認コードは、この画面を
                    開いた時点（AuthPasswordController::edit()）で自動的に送信済みになっている。
                --}}

                @if ($sendFailed)
                    <div class="alert alert-danger">
                        確認コードの送信に失敗しました。お手数ですが「確認コードを再送する」からもう一度お試しください。
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <p>
                    ご登録のメールアドレス宛に確認コードを送信しました。
                    メールに記載された6桁の数字と、新しいパスワードを入力してください。
                </p>
                <p class="small text-muted">
                    パスワードを変更すると、ほかの端末でのログインと、「このデバイスを記憶する」で記憶した端末{{ Route::has('mypage.passkeys') ? '、登録されているパスキー' : '' }}は、すべて無効になります。
                    変更したことは、ご登録のメールアドレスにお知らせします。
                </p>

                <form method="POST" action="{{ route('password.update') }}">
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
                        {{-- confirmedルールによるエラーは"password"側に付くため、
                             このフィールド自体にis-invalid/invalid-feedbackは付けていない。
                             会員登録フォームのpassword_confirmationと同じ扱い。 --}}
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               autocomplete="new-password"
                               class="form-control">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">変更する</button>
                        <a href="{{ route('mypage') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('password.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
