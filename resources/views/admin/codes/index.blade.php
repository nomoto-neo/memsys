@extends('layouts.admin')

@push('head-extra')
@vite(['resources/js/code_editor.js'])
@endpush

@section('content')
<h1 class="h4 mb-3">項目見出し一覧</h1>

{{-- コード表の切り替え。選ぶと、code_editor.jsがこのフォームを送信する
     （保存していない変更があるときは、先に確認する）。 --}}
<form id="code-type-form" method="GET" action="{{ route('admin.codes.index') }}" class="card mb-4">
    <div class="card-body">
        <label for="code-type" class="form-label fw-bold">項目名</label>
        <select id="code-type" name="type" class="form-select" style="max-width: 16rem;"
                data-current="{{ $type->value }}">
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected($value === $type->value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

{{--
    行の追加・削除・並び替えはcode_editor.jsが行う。入力欄のnameの番号
    （codes[0][code]など）は、送信の直前に画面の並び順で振り直す。エラー欄のdata-itemも
    入力欄のnameと同じ形にしているので、エラーのある欄はapp.jsが赤くする。
    コード値を固定するコード表（CodeType::isFixed()）は、コード値を読み取り専用にし、
    行の追加・削除のボタンを出さない。
--}}
<form id="code-form" method="POST" action="{{ route('admin.codes.update') }}"
      data-unsaved="{{ $unsaved ? '1' : '0' }}" data-fixed="{{ $type->isFixed() ? '1' : '0' }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="type" value="{{ $type->value }}">

    <div class="card mb-3">
        <div class="card-body">
            @if ($type->isFixed())
                <p class="small text-muted">
                    このコード表はプログラムの処理に使われているため、並び替えと表示名称の変更だけができます。
                </p>
            @endif
            <div class="invalid-feedback" data-item="codes">{{ $errors->first('codes') }}</div>

            <table class="table align-middle mb-2">
                <thead>
                    <tr>
                        <th style="width: 2rem;"></th>
                        <th style="width: 10rem;">コード値</th>
                        <th>表示名称</th>
                        <th style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="code-rows">
                    @foreach ($rows as $i => $row)
                        <tr>
                            {{-- ⠿はドラッグの持ち手（カテゴリー一覧と同じ） --}}
                            <td class="drag-handle text-center" style="cursor: grab;">⠿</td>
                            <td>
                                <input type="text" name="codes[{{ $i }}][code]" value="{{ $row['code'] }}"
                                       class="form-control" data-field="code" aria-label="コード値"
                                       @if ($type->isFixed()) readonly @endif>
                                <div class="invalid-feedback" data-item="codes[{{ $i }}][code]">{{ $errors->first("codes.$i.code") }}</div>
                            </td>
                            <td>
                                <input type="text" name="codes[{{ $i }}][name]" value="{{ $row['name'] }}"
                                       class="form-control" data-field="name" aria-label="表示名称">
                                <div class="invalid-feedback" data-item="codes[{{ $i }}][name]">{{ $errors->first("codes.$i.name") }}</div>
                            </td>
                            <td class="text-end">
                                @unless ($type->isFixed())
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="この行を削除">×</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @unless ($type->isFixed())
                <button type="button" id="code-add" class="btn btn-warning text-white">追加</button>
                <p class="small text-muted mt-3 mb-0">
                    コード値は数字でも文字でもかまいません（数字は前ゼロを除いて同じ値かどうかを判断します。「01」と「1」は同じ値です）。
                    コード値を空欄にした行と、×で消した行は、更新すると削除されます。
                    表示名称の中で改行したいときは「\n」と書きます。
                </p>
            @else
                <p class="small text-muted mt-3 mb-0">表示名称の中で改行したいときは「\n」と書きます。</p>
            @endunless
        </div>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-success">▶ 更新する</button>
    </div>
</form>

{{-- 追加ボタンで増やす1行のひな形（code_editor.jsが複製して使う） --}}
<template id="code-row-template">
    <tr>
        <td class="drag-handle text-center" style="cursor: grab;">⠿</td>
        <td><input type="text" class="form-control" data-field="code" aria-label="コード値"></td>
        <td><input type="text" class="form-control" data-field="name" aria-label="表示名称"></td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="この行を削除">×</button>
        </td>
    </tr>
</template>

{{-- 保存していない変更があるときに、コード表を切り替えてよいかを確かめる --}}
<div class="modal fade" id="code-switch-modal" tabindex="-1" aria-labelledby="code-switch-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="code-switch-modal-label">項目の切り替え</h5>
            </div>
            <div class="modal-body">
                保存していない変更があります。切り替えますか？
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-switch-answer="no">いいえ</button>
                <button type="button" class="btn btn-primary" data-switch-answer="yes">はい</button>
            </div>
        </div>
    </div>
</div>
@endsection
