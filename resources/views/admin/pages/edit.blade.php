@extends('layouts.admin')

{{-- create.blade.phpと同じ理由・同じ仕組み。 --}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/wysiwyg_ckeditor.js'])
@endpush

@section('content')
<div class="card">
    <div class="card-header">固定ページ編集</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.pages.confirm.edit', $page) }}">
            @csrf
            @method('PATCH')

            @include('admin.pages._fields', [
                'input' => $input,
                'readonly' => '',
                'disabled' => '',
                'required' => $required,
            ])

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">確認する</button>
                <a href="{{ route('admin.pages.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>
@endsection
