@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">会員情報更新の確認</div>
            <div class="card-body">
                <p class="text-muted">
                    以下の内容で更新します。よろしければ「更新する」を押してください。
                </p>

                {{--
                    ここは表示専用。<form>には入れていない（下の2つの
                    フォームだけが実際に送信する）。edit.blade.phpと
                    全く同じ_fields.blade.phpを、readonly/disabledを
                    付けて呼び出しているだけなので、項目が増減しても
                    このファイルを直す必要はない。
                --}}
                @include('admin.members._fields', [
                    'input' => $input,
                    'readonly' => ' readonly',
                    'disabled' => ' disabled',
                    'required' => [],
                    'showPassword' => true,
                ])

                <div class="d-flex gap-2 mt-3">
                    {{--
                        戻る・更新するのどちらも、上の表示部分とは別の、
                        hiddenだけを持つ小さなフォーム。実際に送信されるのは
                        このhiddenの値であり、上のreadonly/disabled表示は
                        見た目だけで送信には一切関与しない（disabledにした
                        <select>はそもそも送信されない仕様なので、値の運搬は
                        必ずこのhidden側で行う）。表示もhiddenも、
                        confirmUpdate()が検証した直後の値から組み立てた
                        同じ$inputを使っているので、「画面に表示されている内容」と「実際に
                        送信される内容」は常に同じソースから来ている。
                        セッションのような別の保存領域を経由しないので、
                        その間にズレが生じる余地が無い。

                        hiddenを1つずつ書かずに_confirm_hiddenに$inputを
                        渡しているのは、admin/staff/confirm.blade.phpと同じ
                        （詳しくはそちらのコメント参照）。
                    --}}
                    <form method="POST" action="{{ route('admin.members.confirm.edit.back', $member) }}">
                        @csrf
                        @include('_confirm_hidden', [
                            'input' => $input,
                            'exclude' => ['password', 'password_confirmation'],
                        ])
                        <button type="submit" class="btn btn-outline-secondary">戻る</button>
                    </form>

                    <form method="POST" action="{{ route('admin.members.update', $member) }}">
                        @csrf
                        @method('PATCH')
                        @include('_confirm_hidden', ['input' => $input])
                        <button type="submit" class="btn btn-primary">更新する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
