@extends('layouts.admin')

{{-- 管理画面の回数の制限（App\Support\AdminRequestLimit）を超えたときに出す画面 --}}
@section('content')
<div class="alert alert-warning">{{ $message }}</div>
@endsection
