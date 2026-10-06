@extends('layouts.admin')

{{--
    アップロードの欄とエディタに要るものを、この画面だけで<head>に足す。
    layouts/admin.blade.phpの@stack('head-extra')に入る。
    エディタはsummernoteのwysiwyg_summernote.jsか、CKEditorのwysiwyg_ckeditor.jsのどちらか一方を読み込む。
    summernoteを使うときは、layouts/admin.blade.phpのjQueryも有効にしておく。
--}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
{{-- summernote --}}
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css" rel="stylesheet" integrity="sha384-NCIOkH1RWTLh0uk0cWmHMJbcBZE8aFTbBNELvTaRgLwGsGIgaacBRlWynjlAt69p" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js" integrity="sha384-MntjVYiMgh4GvBm6NfOuT9XLA242rIvKp/oGBkGnIQeEPoPaMmfJz9BUa86NE8lB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/lang/summernote-ja-JP.min.js" integrity="sha384-dr5H23vIH57PWOg07dJsz2IIwIVI9LgOX4QUryaxPVEY/wedSn6L8xPfnQcKA/cf" crossorigin="anonymous"></script>
    @vite(['resources/js/ajax_upload.js', 'resources/js/wysiwyg_summernote.js'])
{{-- CKEditor
    @vite(['resources/js/ajax_upload.js', 'resources/js/wysiwyg_ckeditor.js'])
--}}
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
