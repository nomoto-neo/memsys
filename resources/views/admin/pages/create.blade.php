@extends('layouts.admin')

{{--
    本文のエディタ（CKEditor）に要るものを、この画面だけで<head>に足す。
    layouts/admin.blade.phpの@stack('head-extra')に入る。
    CSRFトークンの<meta>は、エディタに挿入した画像のアップロードが読む。
    CKEditorの見た目のCSSは、wysiwyg_ckeditor.jsが読み込むので、ここには書かない。
--}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/wysiwyg_ckeditor.js'])
@endpush

@section('content')
<div class="card">
    <div class="card-header">固定ページ 新規登録</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.pages.confirm.create') }}">
            @csrf

            @include('admin.pages._fields', [
                'input' => $input,
                'readonly' => '',
                'disabled' => '',
                'required' => $required,
            ])

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">確認する</button>
                <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>
@endsection
