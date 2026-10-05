@extends('layouts.admin')

{{-- 運営による企業の登録。企業は承認済みで作られ、最初の担当者へ招待のメールを送る --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">企業会員登録</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.companies.confirm.create') }}">
                    @csrf

                    @include('admin.companies._fields', [
                        'isCreate' => true,
                        'input' => $input,
                        'company' => null,
                        'readonly' => '',
                        'disabled' => '',
                        'required' => $required,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        <a href="{{ route('admin.companies.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
