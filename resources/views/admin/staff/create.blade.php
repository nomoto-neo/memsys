@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">スタッフ新規登録</div>
            <div class="card-body">
                {{-- この画面自体が'manager'ミドルウェアで守られている
                     （管理者しかここに来られない）ので、aclの選択肢を
                     出すこと自体に特別な条件分岐は要らない。 --}}
                <form method="POST" action="{{ route('admin.staff.confirm.create') }}">
                    @csrf

                    @include('admin.staff._fields', [
                        'isCreate' => true,
                        'input' => $input,
                        'readonly' => '',
                        'disabled' => '',
                        'required' => $required,
                        'showAcl' => true,
                        'showPassword' => true,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
