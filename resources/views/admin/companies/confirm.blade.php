@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">企業会員情報更新の確認</div>
            <div class="card-body">
                <p class="text-muted">
                    以下の内容で更新します。よろしければ「更新する」を押してください。
                </p>

                {{-- ここは表示専用。<form>には入れていない（下の2つのフォームだけが実際に送信する）。
                     edit.blade.phpと同じ_fields.blade.phpを、readonly/disabledを付けて呼んでいる --}}
                @include('admin.companies._fields', [
                    'input' => $input,
                    'company' => $company,
                    'readonly' => ' readonly',
                    'disabled' => ' disabled',
                    'required' => [],
                ])

                <div class="d-flex gap-2 mt-3">
                    {{-- 戻る・更新するのどちらも、hiddenだけを持つ小さなフォーム。表示もhiddenも、
                         confirmUpdate()が検証した直後の同じ$inputから作る（admin/members/confirm.blade.phpと同じ） --}}
                    <form method="POST" action="{{ route('admin.companies.confirm.edit.back', $company) }}">
                        @csrf
                        @include('_confirm_hidden', ['input' => $input])
                        <button type="submit" class="btn btn-outline-secondary">戻る</button>
                    </form>

                    <form method="POST" action="{{ route('admin.companies.update', $company) }}">
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
