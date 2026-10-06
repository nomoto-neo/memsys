@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">会員登録</div>

            <div class="card-body">
                {{-- 送信先はconfirm()。ここでは保存せず、入力チェックと
                     確認画面の表示だけを行う。 --}}
                <form method="POST" action="{{ route('regist.confirm') }}">
                    @csrf

                    {{-- required属性・is-invalidクラスはここでは書かない。ラベル内の
                         .required-markと、エラー欄(.invalid-feedback)のdata-itemを見て、
                         resources/js/app.jsが付ける。 --}}

                    <div class="mb-3">
                        <label for="name" class="form-label">お名前 {!! $required['name'] !!}</label>
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
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email"
                               class="form-control"
                               value="{{ $input['email'] ?? '' }}">
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">電話番号 {!! $required['phone'] !!}</label>
                        <input id="phone" type="text" name="phone"
                               class="form-control"
                               value="{{ $input['phone'] ?? '' }}" placeholder="例: 090-1234-5678">
                        <div class="invalid-feedback" data-item="phone">{{ $errors->first('phone') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="birthdate" class="form-label">生年月日 {!! $required['birthdate'] !!}</label>
                        <input id="birthdate" type="date" name="birthdate"
                               class="form-control"
                               value="{{ $input['birthdate'] ?? '' }}">
                        <div class="invalid-feedback" data-item="birthdate">{{ $errors->first('birthdate') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] !!}</label>
                        {{-- valueに入れているのは名称ではなく都道府県コード（code/prefectures.csvの
                             キー）。$input['prefecture']はフォームから返ってきた「文字列」の
                             ことがあるので、コード（int）と比較する前に明示的に(int)
                             キャストしている。 --}}
                        <select id="prefecture" name="prefecture"
                                class="form-select">
                            <option value="">選択してください</option>
                            @foreach (code_table('prefectures') as $code => $name)
                                <option value="{{ $code }}" @selected((int) ($input['prefecture'] ?? 0) === $code)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-item="prefecture">{{ $errors->first('prefecture') }}</div>
                    </div>

                    {{-- お知らせメールを受け取るかどうか。$input['notice_mail']は、送信された文字列と
                         それ以外のintの2通りがあるので、比べる前に(int)でそろえる。 --}}
                    <div class="mb-3">
                        <div class="form-label d-block">お知らせメール {!! $required['notice_mail'] !!}</div>
                        @foreach (code_table('notice_mail') as $code => $name)
                            <div class="form-check form-check-inline">
                                <input id="notice_mail_{{ $code }}" type="radio" name="notice_mail" value="{{ $code }}"
                                       class="form-check-input"
                                       @checked((int) ($input['notice_mail'] ?? 1) === $code)>
                                <label for="notice_mail_{{ $code }}" class="form-check-label">{{ $name }}</label>
                            </div>
                        @endforeach
                        <div class="form-text">サイトからのお知らせを、メールでお届けします。</div>
                        <div class="invalid-feedback" data-item="notice_mail">{{ $errors->first('notice_mail') }}</div>
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
