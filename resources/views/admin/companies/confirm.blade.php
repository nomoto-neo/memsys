@extends('layouts.admin')

{{-- 新規登録と編集の、両方の確認画面。$isCreateで、見出し・送信先・ボタンの文言を切り替える --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">{{ $isCreate ? '企業会員登録の確認' : '企業会員情報更新の確認' }}</div>
            <div class="card-body">
                <p class="text-muted">
                    @if ($isCreate)
                        以下の内容で登録します。よろしければ「登録する」を押してください。
                        最初の担当者へ、招待のメールを送ります。
                    @else
                        以下の内容で更新します。よろしければ「更新する」を押してください。
                    @endif
                </p>

                {{-- ここは表示専用。<form>には入れていない（下の2つのフォームだけが実際に送信する）。
                     create・edit.blade.phpと同じ_fields.blade.phpを、readonly/disabledを付けて呼んでいる --}}
                @include('admin.companies._fields', [
                    'isCreate' => $isCreate,
                    'input' => $input,
                    'company' => $company,
                    'readonly' => ' readonly',
                    'disabled' => ' disabled',
                    'required' => [],
                ])

                <div class="d-flex gap-2 mt-3">
                    {{-- 戻る・登録する（更新する）のどちらも、hiddenだけを持つ小さなフォーム。表示もhiddenも、
                         コントローラーが検証した直後の同じ$inputから作る（admin/staff/confirm.blade.phpと同じ） --}}
                    @if ($isCreate)
                        <form method="POST" action="{{ route('admin.companies.confirm.create.back') }}">
                            @csrf
                            @include('_confirm_hidden', ['input' => $input])
                            <button type="submit" class="btn btn-outline-secondary">戻る</button>
                        </form>

                        <form method="POST" action="{{ route('admin.companies.store') }}">
                            @csrf
                            @include('_confirm_hidden', ['input' => $input])
                            <button type="submit" class="btn btn-primary">登録する</button>
                        </form>
                    @else
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
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
