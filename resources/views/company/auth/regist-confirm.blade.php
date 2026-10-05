@extends('layouts.company')

@section('title', 'お申し込み内容の確認')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">お申し込み内容の確認</div>

            <div class="card-body">
                <p class="text-muted">
                    以下の内容で申し込みます。よろしければ「確認コードを送信する」を押してください。
                    担当者のメールアドレスに確認コードが届きます。そのコードを次の画面で入力すると、お申し込みが完了します。
                </p>

                {{-- 表示にも、下のhidden展開にも、同じ$inputを使うのはadmin側の
                     確認画面と同じ（詳しくはresources/views/_confirm_hiddenの
                     コメント参照）。 --}}
                <h2 class="h6 border-bottom pb-2 mb-3">企業の情報</h2>

                <dl class="row">
                    <dt class="col-sm-4">企業名</dt>
                    <dd class="col-sm-8">{{ $input['name'] }}</dd>

                    <dt class="col-sm-4">フリガナ</dt>
                    <dd class="col-sm-8">{{ $input['kana'] ?? '（未入力）' }}</dd>

                    <dt class="col-sm-4">代表者名</dt>
                    <dd class="col-sm-8">{{ $input['representative'] ?? '（未入力）' }}</dd>

                    <dt class="col-sm-4">郵便番号</dt>
                    <dd class="col-sm-8">{{ $input['zip'] ?? '（未入力）' }}</dd>

                    {{-- hiddenで持ち回しているのはコード。確認画面では、コード表を引いて名称で出す --}}
                    <dt class="col-sm-4">都道府県</dt>
                    <dd class="col-sm-8">{{ code_label('prefectures', $input['prefecture'] ?? null, '（未入力）') }}</dd>

                    <dt class="col-sm-4">住所</dt>
                    <dd class="col-sm-8">{{ $input['address'] ?? '（未入力）' }}</dd>

                    <dt class="col-sm-4">電話番号</dt>
                    <dd class="col-sm-8">{{ $input['tel'] }}</dd>

                    <dt class="col-sm-4">ホームページURL</dt>
                    <dd class="col-sm-8">{{ $input['url'] ?? '（未入力）' }}</dd>
                </dl>

                <h2 class="h6 border-bottom pb-2 mb-3">担当者の情報</h2>

                <dl class="row">
                    <dt class="col-sm-4">お名前</dt>
                    <dd class="col-sm-8">{{ $input['user_name'] }}</dd>

                    <dt class="col-sm-4">メールアドレス</dt>
                    <dd class="col-sm-8">{{ $input['email'] }}</dd>

                    <dt class="col-sm-4">担当者ID（ログインID）</dt>
                    <dd class="col-sm-8">{{ $input['login_id'] }}</dd>

                    <dt class="col-sm-4">パスワード</dt>
                    <dd class="col-sm-8">●●●●●●●●</dd>
                </dl>

                <div class="d-flex gap-2 mt-4">
                    {{-- 戻る：hiddenで持っている内容を入力画面のold()に乗せて戻す。
                         パスワードは確認画面のHTMLに平文で出さないよう、hiddenから除外し、
                         入力画面で入力し直してもらう。 --}}
                    <form method="POST" action="{{ route('company.regist.back') }}">
                        @csrf
                        @include('_confirm_hidden', [
                            'input' => $input,
                            'exclude' => ['password', 'password_confirmation'],
                        ])
                        <button type="submit" class="btn btn-outline-secondary">戻る</button>
                    </form>

                    {{-- 確認コードを送信する：同じ内容をもう一度送信し、サーバー側で
                         全項目を再チェックした上で、入力内容を仮置きして確認コードを送る
                         （企業と担当者はコードの入力が済んでから作る）。登録に使う内容なので、
                         パスワードも含めてすべて展開する。 --}}
                    <form method="POST" action="{{ route('company.regist.send') }}">
                        @csrf
                        @include('_confirm_hidden', ['input' => $input])
                        <button type="submit" class="btn btn-primary">確認コードを送信する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
