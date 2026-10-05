@extends('layouts.company')

@section('title', 'お申し込みの完了')

{{-- 企業会員の登録の申請が済んだ後の案内。運営が承認するまでは、ログインできない --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">お申し込みを受け付けました</div>
            <div class="card-body">
                <p>
                    企業会員登録のお申し込みを受け付けました。ありがとうございます。
                </p>
                <p>
                    当サイトで内容を確認し、承認のお知らせを担当者のメールアドレスへお送りします。
                    ログインに使う企業IDは、そのメールでお伝えします。
                    承認のお知らせが届くまで、しばらくお待ちください。
                </p>
                <a href="{{ route('top') }}" class="btn btn-outline-secondary">トップページへ</a>
            </div>
        </div>
    </div>
</div>
@endsection
