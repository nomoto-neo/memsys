@extends('layouts.company')

@section('title', '確認コードの入力')

@section('content')
{{-- 企業会員のログインの2段階目。中身は、どのログインでも同じ_two_factorにある --}}
<div class="row justify-content-center">
    <div class="col-md-6">
        @include('neobit::_two_factor')
    </div>
</div>
@endsection
