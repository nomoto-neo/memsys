@extends('layouts.app')

@section('title', 'メルマガの登録が完了しました')

{{-- 会員のアドレスだったかどうかに関係なく、同じ文を出す（NewsletterController::done()） --}}
@section('content')
<div class="card">
    <div class="card-body text-center py-5">
        <p class="fw-bold">メルマガの登録が完了しました</p>
        <p>お知らせメールを、ご登録のメールアドレスにお送りします。<br>
            配信を停止したいときは、お知らせメールの末尾にあるURLから停止できます。</p>

        <a href="/" class="btn btn-primary mt-3">ホームへ戻る</a>
    </div>
</div>
@endsection
