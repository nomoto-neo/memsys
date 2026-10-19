@extends('layouts.app')

@section('title', 'メルマガの登録')

{{--
    メルマガだけの申し込み（NewsletterController）。送ると、入力したメールアドレスに確認コードを送り、
    コードの入力画面へ進む。確認画面は挟まない。項目が2つだけで、コードの入力画面に宛先を出すため。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">メルマガの登録</div>
            <div class="card-body">
                <p>お知らせメール（メルマガ）を受け取るための登録です。会員登録をしなくても、お知らせメールだけを受け取れます。</p>

                <form method="POST" action="{{ route('newsletter.send') }}">
                    @csrf

                    {{-- required属性・is-invalidクラスはここでは書かない。ラベル内の
                         .required-markと、エラー欄(.invalid-feedback)のdata-itemを見て、
                         vendor/neobit/core/resources/js/global.jsが付ける。 --}}

                    <div class="mb-3">
                        <label for="name" class="form-label">お名前（ニックネームでも可） {!! $required['name'] !!}</label>
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
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    {{-- スパム対策（ハニーポット・Cloudflare Turnstile。Neobit\Core\Security\SpamGuard） --}}
                    @include('neobit::_spam_guard')

                    <button type="submit" class="btn btn-primary">確認コードを送る</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
