@extends('layouts.admin')

@section('content')
{{-- 管理画面のログインの2段階目。中身は、どのログインでも同じ_two_factorにある --}}
<div class="row justify-content-center">
    <div class="col-md-6">
        @include('_two_factor')
    </div>
</div>
@endsection
