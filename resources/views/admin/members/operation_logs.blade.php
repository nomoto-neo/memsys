@extends('layouts.admin')

{{--
    1人の会員の操作ログ。この会員を対象にした操作（スタッフによる閲覧・更新など）と、
    この会員が行った操作（ログイン・マイページでの変更など）を、新しい順に出す。
    問い合わせに答えるのに使う。操作ログの一覧と同じく、管理者だけが開ける。
--}}
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">会員の操作ログ（会員ID:{{ $member->id }}　{{ $member->name }}）</h1>
    <a href="{{ route('admin.members.show', $member) }}" class="btn btn-sm btn-outline-secondary">会員詳細へ戻る</a>
</div>

@include('admin.operation_logs._table', ['logs' => $logs, 'names' => $names])

{{ $logs->links('pagination::bootstrap-5') }}
@endsection
