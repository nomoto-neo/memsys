@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">会員編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.members.confirm.edit', $member) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.members._fields', [
                        'input' => $input,
                        'readonly' => '',
                        'disabled' => '',
                        'required' => $required,
                        'showPassword' => true,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        <a href="{{ route('admin.members.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
