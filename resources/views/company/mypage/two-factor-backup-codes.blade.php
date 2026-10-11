@extends('layouts.company')

@section('title', 'バックアップコード')

@section('content')
{{-- バックアップコードを1回だけ見せる画面。中身は、どのログインでも同じ_two_factor_backup_codesにある --}}
<div class="row justify-content-center">
    <div class="col-md-6">
        @include('_two_factor_backup_codes', ['nextLabel' => '確認しました。マイページへ進む'])
    </div>
</div>
@endsection
