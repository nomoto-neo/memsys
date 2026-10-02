@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                @if ($actor->id === $staff->id)
                    自分の情報を編集
                @else
                    スタッフ編集
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.staff.confirm.edit', $staff) }}">
                    @csrf
                    @method('PATCH')

                    {{-- 権限欄は、権限を変えてよい人（App\Policies\StaffPolicy::updateAcl()）にだけ出す --}}
                    @include('admin.staff._fields', [
                        'isCreate' => false,
                        'input' => $input,
                        'readonly' => '',
                        'disabled' => '',
                        'required' => $required,
                        'showAcl' => $actor->can('updateAcl', $staff),
                        'showPassword' => true,
                    ])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認する</button>
                        @if ($actor->isManager())
                            <a href="{{ route('admin.staff.index', ['back']) }}" class="btn btn-outline-secondary">キャンセル</a>
                        @else
                            {{-- スタッフ（acl=0）は一覧を見る権限が無いので、
                                 戻り先は一覧ではなく管理画面TOPにしている。 --}}
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">キャンセル</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
