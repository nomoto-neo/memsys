@extends('layouts.app')

@section('title', 'パスキー')

@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/passkeys.js'])
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                パスキー
                <a href="{{ route('mypage') }}" class="btn btn-sm btn-outline-secondary">マイページへ戻る</a>
            </div>
            <div class="card-body">
                @include('_passkeys')
            </div>
        </div>
    </div>
</div>
@endsection
