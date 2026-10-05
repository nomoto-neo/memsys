@extends('layouts.company')

@section('title', '初回ログインの登録')

{{--
    既存のシステムから移した企業の、初回のログインでの登録。企業ID・担当者ID・パスワードが通った後に出す。
    $userは登録を待っている担当者。$canSetupは、照合する値が企業のデータにあるかどうか。
    無ければ、本人では登録できないので、フォームを出さずに案内だけ出す。

    照合する値（identity）の見出しは、設定のconfig('members.company.identity_check_column')に
    合わせて書く。見本のサイトでは、企業の電話番号。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">初回ログインの登録</div>
            <div class="card-body">
                @if ($canSetup)
                    <p class="text-muted">
                        {{ $user->company->name }} のご担当者様の情報を登録してください。
                        新しいサイトでは、担当者お一人ずつにIDとパスワードを持っていただきます。
                        ここで登録した方が最初の担当者になり、ほかの担当者は、ログインした後で招待できます。
                    </p>

                    <form method="POST" action="{{ route('company.login.setup.send') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">お名前 {!! $required['name'] !!}</label>
                            <input id="name" type="text" name="name"
                                   class="form-control"
                                   value="{{ $input['name'] ?? '' }}" autofocus>
                            <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                            <input id="email" type="email" name="email"
                                   class="form-control"
                                   value="{{ $input['email'] ?? '' }}">
                            <div class="form-text">このアドレスに確認コードを送ります。次回からのログインの確認コードも、ここに届きます。</div>
                            <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="login_id" class="form-label">新しい担当者ID {!! $required['login_id'] !!}</label>
                            <input id="login_id" type="text" name="login_id"
                                   class="form-control"
                                   autocomplete="username"
                                   value="{{ $input['login_id'] ?? '' }}">
                            <div class="form-text">
                                次回から、企業ID（{{ $user->company->code }}）と一緒に入力します。
                                半角の英数字と記号（_ . -）が使えます。
                            </div>
                            <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">新しいパスワード {!! $required['password'] !!}</label>
                            <input id="password" type="password" name="password"
                                   autocomplete="new-password"
                                   class="form-control">
                            <div class="form-text">今までのパスワードとは別のものを決めてください。</div>
                            <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">新しいパスワード（確認） {!! $required['password_confirmation'] !!}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                   autocomplete="new-password"
                                   class="form-control">
                            <div class="invalid-feedback" data-item="password_confirmation">{{ $errors->first('password_confirmation') }}</div>
                        </div>

                        {{-- ご本人の確認のための照合。IDとパスワードを知っているだけの他人に、登録させないため --}}
                        <hr>

                        <div class="mb-3">
                            <label for="identity" class="form-label">ご登録の電話番号 {!! $required['identity'] !!}</label>
                            <input id="identity" type="text" name="identity"
                                   class="form-control"
                                   placeholder="例: 03-1234-5678">
                            <div class="form-text">ご本人の確認のため、これまでのサイトに登録していた企業の電話番号を入力してください。</div>
                            <div class="invalid-feedback" data-item="identity">{{ $errors->first('identity') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary">確認コードを送信する</button>
                    </form>
                @else
                    <p>
                        恐れ入りますが、この企業会員は、画面からの登録ができません。
                        お手数ですが、お問い合わせフォームからご連絡ください。
                    </p>
                    <a href="{{ route('contact.create') }}" class="btn btn-outline-secondary">お問い合わせ</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
