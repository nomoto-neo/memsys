@extends('layouts.admin')

@section('content')
{{-- 管理画面のログインの2段階目。中身は、どのログインでも同じ_two_factorにある --}}
<div class="row justify-content-center">
    <div class="col-md-6">
        @include('_two_factor', [
            'rememberLabel' => 'この端末を信頼する（次回から30日間、この端末では確認コードの入力を省略します）',
        ])
    </div>
</div>
@endsection
