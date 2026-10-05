@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">担当者詳細</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.companies.show', $company) }}" class="btn btn-sm btn-outline-secondary">企業の詳細へ戻る</a>
        <a href="{{ route('admin.companies.users.edit', [$company, $user]) }}" class="btn btn-sm btn-primary">編集する</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{-- edit/confirmと同じ_fields.blade.phpを、readonly付きで呼び出しているだけ。
             表示する値（$input）はCompanyUserController::show()が組み立てて渡す --}}
        @include('admin.company_users._fields', [
            'input' => $input,
            'company' => $company,
            'user' => $user,
            'readonly' => ' readonly',
            'required' => [],
            'showPassword' => false,
        ])

        <div class="row">
            <div class="col-sm-3 text-muted">登録日時</div>
            <div class="col-sm-9">{{ $user->created_at->format('Y年n月j日 H:i') }}</div>
        </div>

        {{-- パスキーの登録件数。登録・削除は本人だけができる --}}
        <div class="row mt-2">
            <div class="col-sm-3 text-muted">パスキー</div>
            <div class="col-sm-9">{{ $user->passkeys()->count() }}件登録</div>
        </div>
    </div>
</div>

{{-- 削除。退職した人のアカウントを止めるときなどに使う。最後の1人も削除できる。
     確認は、その場で完結するBootstrapのモーダルにしている（admin/staff/show.blade.phpと同じ） --}}
<div class="mt-3">
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCompanyUserModal">
        この担当者を削除する
    </button>
</div>

<div class="modal fade" id="deleteCompanyUserModal" tabindex="-1" aria-labelledby="deleteCompanyUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCompanyUserModalLabel">担当者の削除</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                「{{ $company->name }}」の担当者「{{ $user->name ?? $user->login_id }}」さんを削除します。
                削除した担当者はログインできなくなり、元に戻せません。担当者へのお知らせは送りません。
                @if ($company->users()->count() === 1)
                    <strong>この企業の担当者は、この1人だけです。削除すると、この企業にはログインできる人がいなくなります。</strong>
                @endif
                よろしいですか？
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                <form method="POST" action="{{ route('admin.companies.users.destroy', [$company, $user]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">削除する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
