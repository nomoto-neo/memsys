@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">担当者編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.companies.users.confirm.edit', [$company, $user]) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.company_users._fields', [
                        'input' => $input,
                        'company' => $company,
                        'user' => $user,
                        'readonly' => '',
                        'required' => $required,
                        'showPassword' => true,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        <a href="{{ route('admin.companies.show', $company) }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
