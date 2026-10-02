@extends('layouts.app')

@push('head-extra')
    {{-- 「送信する」ボタンの二重クリック防止（resources/js/contact_form.jsの
         setupSubmitOnceGuard()）に使う。createの画面と違い、ここでは
         郵便番号検索や同意チェックの仕組みは使わないが、同じファイルに
         まとめてあるものをそのまま読み込む。 --}}
    @vite(['resources/js/contact_form.js'])
@endpush

@section('content')
<div class="card">
    <div class="card-header">お問い合わせ内容の確認</div>

    <div class="card-body">
        <p class="text-muted">
            以下の内容で送信します。よろしければ「送信する」を押してください。
        </p>

        {{-- 表示にも、下のhidden展開にも、同じ$inputを使う（auth/regist-confirm
             や管理画面の確認画面と同じ考え方。詳しくはresources/views/
             _confirm_hiddenのコメント参照）。 --}}
        <dl class="row">
            <dt class="col-sm-4">氏名</dt>
            <dd class="col-sm-8">{{ $input['name'] }}</dd>

            <dt class="col-sm-4">フリガナ</dt>
            <dd class="col-sm-8">{{ $input['kana'] }}</dd>

            <dt class="col-sm-4">メール</dt>
            <dd class="col-sm-8">{{ $input['email'] }}</dd>

            <dt class="col-sm-4">携帯電話</dt>
            <dd class="col-sm-8">{{ $input['phone'] ?: '（未入力）' }}</dd>

            <dt class="col-sm-4">郵便番号</dt>
            <dd class="col-sm-8">{{ $input['zip'] ?: '（未入力）' }}</dd>

            {{-- $input['prefecture']はhiddenで持ち回している「コード」の文字列。
                 auth/regist-confirm.blade.phpと同じく、ここで初めて
                 code_label()で名称に変換して表示する。 --}}
            <dt class="col-sm-4">都道府県</dt>
            <dd class="col-sm-8">{{ code_label('prefectures', $input['prefecture'] ?? null, '（未入力）') }}</dd>

            <dt class="col-sm-4">市区町村名</dt>
            <dd class="col-sm-8">{{ $input['city'] ?: '（未入力）' }}</dd>

            <dt class="col-sm-4">その他住所</dt>
            <dd class="col-sm-8">{{ $input['address_other'] ?: '（未入力）' }}</dd>

            <dt class="col-sm-4">お問い合わせ内容</dt>
            <dd class="col-sm-8" style="white-space: pre-wrap;">{{ $input['body'] ?: '（未入力）' }}</dd>

            <dt class="col-sm-4">添付ファイル</dt>
            <dd class="col-sm-8">
                @include('_ajax_upload_block', [
                    'model' => null,
                    'input' => $input,
                    'field' => 'attach_file',
                    'width' => 0,
                    'readonly' => ' readonly',
                ])
            </dd>
        </dl>

        <div class="d-flex gap-2 mt-4">
            {{-- 戻る：hiddenで持っている内容（同意チェックの値も含む）を
                 入力画面のold()に乗せて戻す。除外する機密値は無いので、
                 $inputをそのまま展開する。連打防止は無害だが必須でも
                 ないので、data-guard-double-submitは付けていない
                 （戻るはDBやメールに影響しない）。 --}}
            <form method="POST" action="{{ route('contact.back') }}">
                @csrf
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-outline-secondary">戻る</button>
            </form>

            {{-- 送信する：同じ内容をもう一度送信し、サーバー側で全項目を
                 再チェックした上で、実際にt_inquiriesへの保存とスタッフへの
                 通知メール送信を行う（ContactController::store()）。

                 confirm_tokenは$inputには含めない専用のhidden。confirm画面を
                 表示するたびに発行し直す使い捨てトークンで、これが
                 セッション側の値と一致しない限りstore()は処理を進めない
                 （ContactController::issueConfirmToken()のコメント参照）。

                 data-guard-double-submitは、このボタンの連打・二重クリック
                 対策（resources/js/contact_form.jsのsetupSubmitOnceGuard()）。
                 こちらはJavaScript前提の保険で、本当の防御はconfirm_token側。 --}}
            <form method="POST" action="{{ route('contact.store') }}" data-guard-double-submit>
                @csrf
                <input type="hidden" name="confirm_token" value="{{ $confirmToken }}">
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-primary">送信する</button>
            </form>
        </div>
    </div>
</div>
@endsection
