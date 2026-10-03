@extends('layouts.app')

{{--
    お問い合わせフォーム。resources/views/auth/regist.blade.phpと同じ
    考え方（confirm()で保存せず検証と確認画面表示だけを行い、
    admin/staffのような$readonly切り替え式の_fields.blade.phpは、
    画面がcreate/confirmの2つしか無いので分けていない）。
--}}

@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/ajax_upload.js', 'resources/js/contact_form.js'])
@endpush

@section('content')
<div class="card">
    <div class="card-header">お問い合わせ</div>

    <div class="card-body">
        {{-- 送信先はconfirmStore()。ここでは保存せず、入力チェックと
             確認画面の表示だけを行う。 --}}
        <form method="POST" action="{{ route('contact.confirm') }}">
            @csrf

            {{-- required属性・is-invalidクラスはここでは書かない。ラベル内の
                 .required-markと、エラー欄(.invalid-feedback)のdata-itemを見て、
                 resources/js/app.jsが付ける。 --}}

            <div class="mb-3">
                <label for="name" class="form-label">氏名 {!! $required['name'] !!}</label>
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
                <label for="email" class="form-label">メール {!! $required['email'] !!}</label>
                <input id="email" type="email" name="email"
                       class="form-control"
                       value="{{ $input['email'] ?? '' }}">
                <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">携帯電話 {!! $required['phone'] !!}</label>
                <input id="phone" type="text" name="phone"
                       class="form-control"
                       value="{{ $input['phone'] ?? '' }}" placeholder="例: 090-1234-5678">
                <div class="invalid-feedback" data-item="phone">{{ $errors->first('phone') }}</div>
            </div>

            <div class="mb-3">
                <label for="zip" class="form-label">郵便番号 {!! $required['zip'] !!}</label>
                {{-- resources/js/contact_form.jsが、入力のたびに数字だけを
                     取り出して7桁で自動的にハイフンを差し込み、7桁になった
                     時点の値が前回と違っていれば住所を検索して都道府県・
                     市区町村名・その他住所へ自動入力する。 --}}
                <input id="zip" type="text" name="zip" inputmode="numeric"
                       class="form-control" style="max-width: 12rem;"
                       value="{{ $input['zip'] ?? '' }}" placeholder="例: 123-4567">
                <div class="invalid-feedback" data-item="zip">{{ $errors->first('zip') }}</div>
            </div>

            <div class="mb-3">
                <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] !!}</label>
                {{-- valueに入れているのは名称ではなく都道府県コード
                     （auth/regist.blade.phpの都道府県欄と同じ考え方）。 --}}
                <select id="prefecture" name="prefecture" class="form-select" style="max-width: 20rem;">
                    <option value="">選択してください</option>
                    @foreach (code_table('prefectures') as $code => $name)
                        <option value="{{ $code }}" @selected((int) ($input['prefecture'] ?? 0) === $code)>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-item="prefecture">{{ $errors->first('prefecture') }}</div>
            </div>

            <div class="mb-3">
                <label for="city" class="form-label">市区町村名 {!! $required['city'] !!}</label>
                <input id="city" type="text" name="city"
                       class="form-control"
                       value="{{ $input['city'] ?? '' }}">
                <div class="invalid-feedback" data-item="city">{{ $errors->first('city') }}</div>
            </div>

            <div class="mb-3">
                <label for="address_other" class="form-label">その他住所 {!! $required['address_other'] !!}</label>
                <input id="address_other" type="text" name="address_other"
                       class="form-control"
                       value="{{ $input['address_other'] ?? '' }}" placeholder="番地・建物名など">
                <div class="invalid-feedback" data-item="address_other">{{ $errors->first('address_other') }}</div>
            </div>

            <div class="mb-3">
                <label for="body" class="form-label">お問い合わせ内容 {!! $required['body'] !!}</label>
                <textarea id="body" name="body" class="form-control" rows="10">{{ $input['body'] ?? '' }}</textarea>
                <div class="invalid-feedback" data-item="body">{{ $errors->first('body') }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label d-block">添付ファイル {!! $required['attach_file'] ?? '' !!}</label>
                @include('_ajax_upload_block', [
                    'model' => null,
                    'input' => $input,
                    'field' => 'attach_file',
                    'width' => 0,
                    'readonly' => '',
                    'uploadUrl' => route('contact.ajaxUpload'),
                ])
            </div>

            <div class="mb-3 form-check">
                {{-- チェックすると「確認画面へ進む」ボタンが有効になる
                     （resources/js/contact_form.js）。JavaScriptが動かない
                     環境でも初期状態が正しくなるよう、ボタンのdisabledは
                     JavaScript任せにせずここでも$inputの値から出している
                     （下のボタン参照）。 --}}
                <input id="agree" type="checkbox" name="agree" value="1"
                       class="form-check-input"
                       @checked(($input['agree'] ?? null) == '1')>
                <label for="agree" class="form-check-label">
                    個人情報の取り扱いについて同意する {!! $required['agree'] !!}
                </label>
                <div class="invalid-feedback" data-item="agree">{{ $errors->first('agree') }}</div>
            </div>

            {{-- スパム対策（ハニーポット・Cloudflare Turnstile。App\Support\SpamGuard） --}}
            @include('_spam_guard')

            <button type="submit" id="contact_submit" class="btn btn-primary"
                    @disabled(($input['agree'] ?? null) != '1')>確認画面へ進む</button>
        </form>
    </div>
</div>
@endsection
