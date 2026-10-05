@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">企業会員編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.companies.confirm.edit', $company) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.companies._fields', [
                        'input' => $input,
                        'company' => $company,
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
