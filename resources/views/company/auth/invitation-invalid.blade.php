@extends('layouts.company')

@section('title', '招待のリンクが使えません')

{{-- 招待のリンクが使えないときの案内。期限切れ・取り消し・登録済みのどれかは伝えない --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">招待のリンクが使えません</div>
            <div class="card-body">
                <p>
                    この招待のリンクは、期限が切れているか、すでに使われています。
                </p>
                <p>
                    まだ登録が済んでいない場合は、招待した方に、招待の送り直しを依頼してください。
                    登録が済んでいる場合は、ログイン画面からログインしてください。
                </p>
                <a href="{{ route('company.login') }}" class="btn btn-outline-secondary">ログイン画面へ</a>
            </div>
        </div>
    </div>
</div>
@endsection
