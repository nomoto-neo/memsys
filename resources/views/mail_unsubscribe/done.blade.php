@extends('layouts.app')

{{--
    お知らせメールの配信停止の完了。そのアドレスの会員がいてもいなくても、同じ表示にする。
--}}
@section('content')
<div class="card">
    <div class="card-header">お知らせメールの配信停止</div>
    <div class="card-body text-center py-5">
        <p>お知らせメールの配信を停止しました。</p>
        <p class="text-muted small">会員の方は、マイページのプロフィール編集から、いつでも再開できます。</p>

        <a href="/" class="btn btn-primary mt-3">ホームへ戻る</a>
    </div>
</div>
@endsection
