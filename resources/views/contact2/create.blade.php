@extends('layouts.app')

{{--
    /contact（App\Support\MailTemplate方式・AjaxFileUpload方式）との
    比較用フォーム。confirm画面を挟まない一段階の送信で、添付ファイルも
    普通の<input type="file">＋enctype="multipart/form-data"で送る
    （resources/js/ajax_upload.js・upload_request.jsのような、送信より
    前にファイルだけ別送りするJavaScriptは一切使わない）。

    required属性・is-invalidクラスの自動付与（resources/js/app.js）
    はlayouts.appが読み込んでいるものをそのまま使えるので、ここでも
    .required-mark・.invalid-feedback[data-item]の書き方だけ揃えている
    （rules()から必須マークを組み立てる仕組み（required_fields()）は比較を単純にするため
    使わず、ここではrequired_mark()を項目ごとに直接書いている）。
--}}

@section('content')
<div class="card">
    <div class="card-header">お問い合わせ（比較用：contact2）</div>

    <div class="card-body">
        {{-- ファイルを送信するフォームには、method="POST"に加えて
             enctype="multipart/form-data"が必須。付け忘れると、
             $request->hasFile()が常にfalseになる（ここが、Ajaxで
             ファイルだけ別送りする/contactの作りとの一番大きな違い）。 --}}
        <form method="POST" action="{{ route('contact2.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">
                    お名前 {!! required_mark() !!}
                </label>
                <input id="name" type="text" name="name"
                       class="form-control"
                       value="{{ $input['name'] ?? '' }}" autofocus>
                <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">
                    メールアドレス {!! required_mark() !!}
                </label>
                <input id="email" type="email" name="email"
                       class="form-control"
                       value="{{ $input['email'] ?? '' }}">
                <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
            </div>

            <div class="mb-3">
                <label for="message" class="form-label">お問い合わせ内容</label>
                <textarea id="message" name="message" class="form-control" rows="8">{{ $input['message'] ?? '' }}</textarea>
                <div class="invalid-feedback" data-item="message">{{ $errors->first('message') }}</div>
            </div>

            <div class="mb-3">
                <label for="attachment" class="form-label">添付ファイル</label>
                <input id="attachment" type="file" name="attachment" class="form-control">
                <div class="form-text">PDF・Word・Excel・CSV・zip・画像（10MBまで）</div>
                <div class="invalid-feedback" data-item="attachment">{{ $errors->first('attachment') }}</div>
            </div>

            <button type="submit" class="btn btn-primary">送信する</button>
        </form>
    </div>
</div>
@endsection
