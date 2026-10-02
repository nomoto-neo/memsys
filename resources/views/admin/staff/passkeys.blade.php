@extends('layouts.admin')

@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/passkeys.js'])
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">パスキー</h1>
    <a href="{{ route('admin.staff.show', Auth::guard('admin')->user()) }}" class="btn btn-sm btn-outline-secondary">自分の情報へ戻る</a>
</div>

<div class="card">
    <div class="card-body">
        @include('_passkeys')
        <p class="small text-muted mt-3 mb-0">
            端末のパスキー選択画面では、「管理画面：ログインID」の名前で表示されます。
            2段階認証の登録を解除すると、登録済みのパスキーもすべて削除されます。
        </p>
    </div>
</div>
@endsection
