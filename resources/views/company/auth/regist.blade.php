@extends('layouts.company')

@section('title', '企業会員登録')

{{--
    企業会員の登録の入力画面。企業の情報と、最初の担当者の情報を一緒に入力する。
    登録が済んでも、運営が承認するまではログインできない。企業IDは、承認のときに決まる番号を
    メールで伝えるので、ここでは入力しない。
--}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">企業会員登録</div>

            <div class="card-body">
                <p class="text-muted">
                    お申し込みの後、当サイトで内容を確認します。承認のお知らせが届いてから、ログインできるようになります。
                </p>

                {{-- 送信先はconfirm()。ここでは保存せず、入力チェックと
                     確認画面の表示だけを行う。 --}}
                <form method="POST" action="{{ route('company.regist.confirm') }}">
                    @csrf

                    {{-- required属性・is-invalidクラスはここでは書かない。ラベル内の
                         .required-markと、エラー欄(.invalid-feedback)のdata-itemを見て、
                         resources/js/app.jsが付ける。 --}}

                    <h2 class="h6 border-bottom pb-2 mb-3">企業の情報</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">企業名 {!! $required['name'] !!}</label>
                        <input id="name" type="text" name="name"
                               class="form-control"
                               value="{{ $input['name'] ?? '' }}" autofocus>
                        <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="kana" class="form-label">フリガナ {!! $required['kana'] !!}</label>
                        <input id="kana" type="text" name="kana"
                               class="form-control"
                               value="{{ $input['kana'] ?? '' }}">
                        <div class="invalid-feedback" data-item="kana">{{ $errors->first('kana') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="representative" class="form-label">代表者名 {!! $required['representative'] !!}</label>
                        <input id="representative" type="text" name="representative"
                               class="form-control"
                               value="{{ $input['representative'] ?? '' }}">
                        <div class="invalid-feedback" data-item="representative">{{ $errors->first('representative') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="zip" class="form-label">郵便番号 {!! $required['zip'] !!}</label>
                        <input id="zip" type="text" name="zip"
                               class="form-control"
                               value="{{ $input['zip'] ?? '' }}" placeholder="例: 100-0001">
                        <div class="invalid-feedback" data-item="zip">{{ $errors->first('zip') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] !!}</label>
                        {{-- $input['prefecture']はフォームから返ってきた文字列のことがあるので、
                             コード（int）と比べる前に(int)でそろえている --}}
                        <select id="prefecture" name="prefecture"
                                class="form-select">
                            <option value="">選択してください</option>
                            @foreach (code_table('prefectures') as $code => $name)
                                <option value="{{ $code }}"
                                        @selected((int) ($input['prefecture'] ?? 0) === $code)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-item="prefecture">{{ $errors->first('prefecture') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">住所 {!! $required['address'] !!}</label>
                        <input id="address" type="text" name="address"
                               class="form-control"
                               value="{{ $input['address'] ?? '' }}">
                        <div class="invalid-feedback" data-item="address">{{ $errors->first('address') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="tel" class="form-label">電話番号 {!! $required['tel'] !!}</label>
                        <input id="tel" type="text" name="tel"
                               class="form-control"
                               value="{{ $input['tel'] ?? '' }}" placeholder="例: 03-1234-5678">
                        <div class="invalid-feedback" data-item="tel">{{ $errors->first('tel') }}</div>
                    </div>

                    <div class="mb-4">
                        <label for="url" class="form-label">ホームページURL {!! $required['url'] !!}</label>
                        <input id="url" type="text" name="url"
                               class="form-control"
                               value="{{ $input['url'] ?? '' }}" placeholder="例: https://example.com/">
                        <div class="invalid-feedback" data-item="url">{{ $errors->first('url') }}</div>
                    </div>

                    {{-- 最初の担当者。ログインするのは担当者で、ほかの担当者は、ログインした後で足す --}}
                    <h2 class="h6 border-bottom pb-2 mb-3">担当者の情報</h2>

                    <div class="mb-3">
                        <label for="user_name" class="form-label">お名前 {!! $required['user_name'] !!}</label>
                        <input id="user_name" type="text" name="user_name"
                               class="form-control"
                               value="{{ $input['user_name'] ?? '' }}">
                        <div class="invalid-feedback" data-item="user_name">{{ $errors->first('user_name') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email"
                               class="form-control"
                               value="{{ $input['email'] ?? '' }}">
                        <div class="form-text">確認コードと、承認のお知らせをお送りします。</div>
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="login_id" class="form-label">担当者ID（ログインID） {!! $required['login_id'] !!}</label>
                        <input id="login_id" type="text" name="login_id"
                               class="form-control"
                               autocomplete="username"
                               value="{{ $input['login_id'] ?? '' }}">
                        <div class="form-text">ログインのときに入力します。半角の英数字と記号（_ . -）が使えます。</div>
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

                    <button type="submit" class="btn btn-primary">確認画面へ</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
