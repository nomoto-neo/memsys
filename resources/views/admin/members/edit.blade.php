@extends('layouts.admin')

{{-- 顔写真のアップロード欄があるので、CSRFトークンの<meta>とajax_upload.jsを読み込む
     （admin/news/create.blade.phpと同じ）。経歴の行を足す・消すのに、repeatable_group.jsも読み込む。 --}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['vendor/neobit/core/resources/js/ajax_upload.js', 'vendor/neobit/core/resources/js/repeatable_group.js'])
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">会員編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.members.confirm.update', $member) }}">
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
                        <a href="{{ route('admin.members.index', ['back']) }}#id{{ $member->id }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
