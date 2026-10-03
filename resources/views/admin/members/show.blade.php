@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">会員詳細</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.members.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">一覧へ戻る</a>
        <a href="{{ route('admin.members.resume', $member) }}" class="btn btn-sm btn-outline-primary" target="_blank">履歴書PDF</a>
        <a href="{{ route('admin.members.edit', $member) }}" class="btn btn-sm btn-primary">編集する</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{--
            edit/confirmと同じ_fields.blade.phpを、readonly/disabled付きで
            呼び出しているだけ。3画面が同じ1つのパーツを見ているので、項目を
            1つ増やすときも_fields.blade.phpだけを直せばよい。

            そのため生年月日はreadonly指定の日付input（ブラウザ標準の日付
            ピッカーの見た目）、都道府県はdisabled指定のselectで表示される。

            表示する値（$input）はMemberController::show()が組み立てて渡す。
        --}}
        @include('admin.members._fields', [
            'input' => $input,
            'model' => $member,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
            'showPassword' => false,
        ])

        <div class="row">
            <div class="col-sm-3 text-muted">登録日時</div>
            <div class="col-sm-9">{{ $member->created_at->format('Y年n月j日 H:i') }}</div>
        </div>

        {{--
            editorStaff()は削除済みスタッフも含める（withTrashed()）ように
            定義しているので、最終更新者が削除済みでも氏名を表示できる。
            削除済みかどうかはtrashed()で判定する。
            一度も/adminから更新されていない会員（自己登録のまま）では
            staff_id自体がnullなので、その場合の表示も分けている。
        --}}
        <div class="row mt-2">
            <div class="col-sm-3 text-muted">最終更新者</div>
            <div class="col-sm-9">
                @if ($member->editorStaff)
                    <span class="{{ $member->editorStaff->trashed() ? 'text-danger' : '' }}">
                        {{ $member->editorStaff->name }}
                    </span>
                    @if ($member->editorStaff->trashed())
                        <span class="text-danger small">（削除済み）</span>
                    @endif
                @else
                    （未更新）
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
