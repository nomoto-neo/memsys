@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                {{ $isCreate ? 'スタッフ新規登録の確認' : 'スタッフ情報更新の確認' }}
            </div>
            <div class="card-body">
                <p class="text-muted">
                    以下の内容で{{ $isCreate ? '登録' : '更新' }}します。
                    よろしければ「{{ $isCreate ? '登録する' : '更新する' }}」を押してください。
                </p>

                {{--
                    ここは表示専用。<form>には入れていない（下の2つの
                    フォームだけが実際に送信する）。create.blade.php/
                    edit.blade.php と全く同じ_fields.blade.phpを、
                    readonly/disabledを付けて呼び出しているだけなので、
                    項目が増減してもこのファイルを直す必要はない。
                --}}
                @include('admin.staff._fields', [
                    'isCreate' => $isCreate,
                    'input' => $input,
                    'readonly' => ' readonly',
                    'disabled' => ' disabled',
                    'required' => [],
                    'showAcl' => $showAcl,
                    'showPassword' => true,
                ])

                <div class="d-flex gap-2 mt-3">
                    {{--
                        戻る・登録する（更新する）のどちらも、上の表示部分とは
                        別の、hiddenだけを持つ小さなフォーム。実際に送信される
                        のはこのhiddenの値であり、上のreadonly/disabled表示は
                        見た目だけで送信には一切関与しない（disabledにした
                        <select>はそもそも送信されない仕様なので、値の運搬は
                        必ずこのhidden側で行う）。表示もhiddenも、
                        confirmStore()/confirmUpdate()が検証した直後の値から
                        組み立てた同じ$inputを使っているので、「画面に表示されている内容」と
                        「実際に送信される内容」は常に同じソースから来ている。
                        セッションのような別の保存領域を経由しないので、
                        その間にズレが生じる余地が無い。

                        $inputには送信される項目だけを入れ、表示専用の値は
                        混ぜない、というのがこのプロジェクト全体の規約
                        （詳しくは_confirm_hiddenのコメント参照）。だから
                        $inputをそのままhidden展開しても、フォーム項目以外の
                        値が紛れ込むことは無い。

                        hidden自体はname・email・acl・password・
                        password_confirmationを1つずつベタ書きするのではなく、
                        _confirm_hiddenに$inputを渡して機械的に展開している。
                        「戻る」側だけpassword・password_confirmationを
                        $excludeで除外しているのは、「戻る」を押した後の
                        入力画面ではパスワード欄を空にして、もう一度
                        入力してもらうため（除外しないと、確認画面の
                        ページソースに平文パスワードがhiddenとしてそのまま
                        残ってしまう）。
                    --}}
                    <form method="POST" action="{{ $isCreate ? route('admin.staff.confirm.create.back') : route('admin.staff.confirm.edit.back', $staff) }}">
                        @csrf
                        @include('_confirm_hidden', [
                            'input' => $input,
                            'exclude' => ['password', 'password_confirmation'],
                        ])
                        <button type="submit" class="btn btn-outline-secondary">戻る</button>
                    </form>

                    <form method="POST" action="{{ $isCreate ? route('admin.staff.store') : route('admin.staff.update', $staff) }}">
                        @csrf
                        @if (! $isCreate)
                            @method('PATCH')
                        @endif
                        @include('_confirm_hidden', ['input' => $input])
                        <button type="submit" class="btn btn-primary">
                            {{ $isCreate ? '登録する' : '更新する' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
