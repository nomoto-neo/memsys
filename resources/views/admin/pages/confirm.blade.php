@extends('layouts.admin')

{{-- 本文を、エディタの中と同じ見た目で表示するためのCSS --}}
@push('head-extra')
    @vite(['resources/css/wysiwyg_ckeditor_content.css'])
@endpush

@section('content')
<div class="card">
    <div class="card-header">
        {{ $isCreate ? '固定ページ登録の確認' : '固定ページ更新の確認' }}
    </div>
    <div class="card-body">
        <p class="text-muted">
            以下の内容で{{ $isCreate ? '登録' : '更新' }}します。
            よろしければ「{{ $isCreate ? '登録する' : '更新する' }}」を押してください。
        </p>

        {{-- ここは表示専用で、実際に送信するのは下の2つのhiddenだけのフォーム --}}
        @include('admin.pages._fields', [
            'input' => $input,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
        ])

        <div class="d-flex gap-2 mt-3">
            {{-- hiddenは、$input（送信される項目だけを集めた配列）から_confirm_hiddenが組み立てる --}}
            <form method="POST" action="{{ $isCreate ? route('admin.pages.confirm.create.back') : route('admin.pages.confirm.edit.back', $page) }}">
                @csrf
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-outline-secondary">戻る</button>
            </form>

            <form method="POST" action="{{ $isCreate ? route('admin.pages.store') : route('admin.pages.update', $page) }}">
                @csrf
                @if (! $isCreate)
                    @method('PATCH')
                @endif
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-primary">
                    {{ $isCreate ? '登録する' : '更新する' }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
