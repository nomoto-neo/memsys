@extends('layouts.admin')

{{-- 顔写真のアップロード欄があるので、CSRFトークンの<meta>とajax_upload.jsを読み込む
     （admin/news/create.blade.phpと同じ）。 --}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/ajax_upload.js'])
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">会員編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.members.confirm.edit', $member) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.members._fields', [
                        'input' => $input,
                        'model' => $member,
                        'readonly' => '',
                        'disabled' => '',
                        'required' => $required,
                        'showPassword' => true,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        <a href="{{ route('admin.members.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
