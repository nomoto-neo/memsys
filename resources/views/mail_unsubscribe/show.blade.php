@extends('layouts.app')

{{--
    お知らせメールの配信停止の確認。メールの中のURLから開く。
    URLが正しいかや、そのアドレスの会員がいるかに関係なく、同じ表示にする。メールアドレスも出さない。
--}}
@section('content')
<div class="card">
    <div class="card-header">お知らせメールの配信停止</div>
    <div class="card-body text-center py-5">
        <p>お知らせメールの配信を停止します。<br>よろしければ、下のボタンを押してください。</p>

        {{-- 送り先は、今開いているURLと同じ（署名とメールアドレスの付いたURL）。actionを空にして、
             クエリごとそのまま送る。メールソフトの「登録解除」のボタンと同じ入口なので、
             CSRFトークンは付けない（bootstrap/app.phpで除外している） --}}
        <form method="POST" action="">
            <button type="submit" class="btn btn-primary mt-3">配信を停止する</button>
        </form>
    </div>
</div>
@endsection
