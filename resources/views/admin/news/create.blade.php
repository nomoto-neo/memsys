@extends('layouts.admin')

{{--
    CSRFトークンの<meta>・resources/js/ajax_upload.js・
    resources/js/wysiwyg_ckeditor.jsは、一覧用画像・添付ファイルの
    アップロード欄や、画像を挿入できる本文のエディタが実際にある画面
    だけで必要なので、layouts/admin.blade.phpの@vite(['resources/js/app.js'])とは
    別に、@stack('head-extra')経由でこの画面からだけ<head>へ追加する
    （layouts/admin.blade.phpの<head>に@stack('head-extra')がある前提）。
--}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/ajax_upload.js', 'resources/js/wysiwyg_ckeditor.js'])
@endpush

@section('content')
{{--
    admin/news/show.blade.php（詳細画面）と横幅を揃えるため、
    row/col-md-8での中央寄せ・幅制限は行わず、カードをそのまま
    コンテンツ幅いっぱいに表示する。
--}}
<div class="card">
    <div class="card-header">ニュース記事 新規登録</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.news.confirm.create') }}">
            @csrf

            @include('admin.news._fields', [
                'input' => $input,
                'model' => null,
                'categories' => $categories,
                'readonly' => '',
                'disabled' => '',
                'required' => $required,
                ])

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">確認する</button>
                <a href="{{ route('admin.news.index') }}" class="btn btn-outline-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>

@endsection
