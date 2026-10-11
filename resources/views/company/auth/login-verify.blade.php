@extends('layouts.company')

@section('title', '確認コードの入力')

@section('content')
{{-- 企業会員のログインの2段階目。中身は、どのログインでも同じ_two_factorにある --}}
<div class="row justify-content-center">
    <div class="col-md-6">
        @include('_two_factor', [
            'rememberLabel' => 'このデバイスを記憶する（次回から30日間、確認コードの入力を省略します）',
        ])
    </div>
</div>
@endsection
