@extends('layouts.admin')

{{-- create.blade.phpと同じ理由・同じ仕組み。 --}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
{{-- summernote --}}
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css" rel="stylesheet" integrity="sha384-NCIOkH1RWTLh0uk0cWmHMJbcBZE8aFTbBNELvTaRgLwGsGIgaacBRlWynjlAt69p" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js" integrity="sha384-MntjVYiMgh4GvBm6NfOuT9XLA242rIvKp/oGBkGnIQeEPoPaMmfJz9BUa86NE8lB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/lang/summernote-ja-JP.min.js" integrity="sha384-dr5H23vIH57PWOg07dJsz2IIwIVI9LgOX4QUryaxPVEY/wedSn6L8xPfnQcKA/cf" crossorigin="anonymous"></script>
    @vite(['resources/js/ajax_upload.js', 'resources/js/wysiwyg_summernote.js'])
@endpush

@section('content')
{{-- create.blade.phpと同じ理由で、row/col-md-8による幅制限はせず、
     show.blade.php（詳細画面）と同じ横幅にしている。 --}}
<div class="card">
    <div class="card-header">ニュース記事編集</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.news.confirm.edit', $news) }}">
            @csrf
            @method('PATCH')

            @include('admin.news._fields', [
                'input' => $input,
                'model' => $news,
                'categories' => $categories,
                'readonly' => '',
                'disabled' => '',
                'required' => $required,
                ])

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">確認する</button>
                <a href="{{ route('admin.news.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>

@endsection
