@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">登録内容の確認</div>

            <div class="card-body">
                <p class="text-muted">
                    以下の内容で登録します。よろしければ「確認コードを送信する」を押してください。
                    入力したメールアドレスに確認コードが届きます。そのコードを次の画面で入力すると、登録が完了します。
                </p>

                {{-- 表示にも、下のhidden展開にも、同じ$inputを使うのはadmin側の
                     確認画面と同じ（詳しくはresources/views/_confirm_hiddenの
                     コメント参照）。 --}}
                <dl class="row">
                    <dt class="col-sm-4">お名前</dt>
                    <dd class="col-sm-8">{{ $input['name'] }}</dd>

                    <dt class="col-sm-4">フリガナ</dt>
                    <dd class="col-sm-8">{{ $input['kana'] ?? '（未入力）' }}</dd>

                    <dt class="col-sm-4">メールアドレス</dt>
                    <dd class="col-sm-8">{{ $input['email'] }}</dd>

                    <dt class="col-sm-4">電話番号</dt>
                    <dd class="col-sm-8">{{ $input['phone'] ?? '（未入力）' }}</dd>

                    <dt class="col-sm-4">生年月日</dt>
                    <dd class="col-sm-8">{{ $input['birthdate'] ?? '（未入力）' }}</dd>

                    {{-- $input['prefecture']はhiddenで持ち回している「コード」の文字列。
                         人間向けの確認画面なので、ここで初めてコード表を引いて
                         名称に変換して表示する（hiddenの値そのものはコードのまま）。
                         code_label()はどこからでも手軽に引ける仕組みとして用意してある
                         ものなので、コントローラーで名称を用意しておく必要は無い。 --}}
                    <dt class="col-sm-4">都道府県</dt>
                    <dd class="col-sm-8">{{ code_label('prefectures', $input['prefecture'] ?? null, '（未入力）') }}</dd>

                    <dt class="col-sm-4">パスワード</dt>
                    <dd class="col-sm-8">●●●●●●●●</dd>
                </dl>

                <div class="d-flex gap-2 mt-4">
                    {{-- 戻る：hiddenで持っている内容を入力画面のold()に乗せて戻す。
                         パスワードだけは$excludeで除外し、入力画面で再入力して
                         もらう（back()側でも$request->except()で落としているが、
                         そもそもこの確認画面のHTMLに平文パスワードを出さない方が
                         良いので、hiddenを出す時点で除外している）。 --}}
                    <form method="POST" action="{{ route('regist.back') }}">
                        @csrf
                        @include('_confirm_hidden', [
                            'input' => $input,
                            'exclude' => ['password', 'password_confirmation'],
                        ])
                        <button type="submit" class="btn btn-outline-secondary">戻る</button>
                    </form>

                    {{-- 確認コードを送信する：同じ内容をもう一度送信し、サーバー側で
                         全項目を再チェックした上で、入力内容を仮置きして確認コードを送る
                         （会員はコードの入力が済んでから作る）。登録に使う内容なので、
                         パスワードも含めてすべて展開する。 --}}
                    <form method="POST" action="{{ route('regist.send') }}">
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
