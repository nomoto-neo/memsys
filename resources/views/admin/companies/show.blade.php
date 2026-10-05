@extends('layouts.admin')

{{--
    企業会員の詳細。企業の情報、状態を変えるボタン、その企業の担当者の一覧を出す。
    $usersはその企業の担当者、$rejectRequiredは却下のフォームの必須マーク。
--}}
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        企業会員詳細
        <span class="align-middle fs-6">@include('admin.companies._status', ['company' => $company])</span>
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.companies.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">一覧へ戻る</a>
        <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-primary">編集する</a>
    </div>
</div>

{{-- 却下の理由の入力エラー。却下のフォームはモーダルの中にあり、戻ってきたときは閉じているので、
     ここに出す（エラーは、CompanyController::reject()がrejectの名前で分けて持つ） --}}
@if ($errors->reject->any())
    <div class="alert alert-danger">却下の理由：{{ $errors->reject->first('reason') }}</div>
@endif

{{-- 申請中の企業の審査。承認すると担当者がログインできるようになり、却下すると企業と担当者の行を消す --}}
@if ($company->isPending())
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>この企業は、承認を待っています。内容を確かめて、承認か却下を行ってください。</span>
        <span class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveCompanyModal">
                承認する
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectCompanyModal">
                却下する
            </button>
        </span>
    </div>
@endif

<div class="card">
    <div class="card-body">
        {{-- edit/confirmと同じ_fields.blade.phpを、readonly/disabled付きで呼び出しているだけ。
             表示する値（$input）はCompanyController::show()が組み立てて渡す --}}
        @include('admin.companies._fields', [
            'input' => $input,
            'company' => $company,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
        ])

        <div class="row">
            <div class="col-sm-3 text-muted">登録日時</div>
            <div class="col-sm-9">{{ $company->created_at->format('Y年n月j日 H:i') }}</div>
        </div>

        {{-- 最終更新者。削除済みのスタッフも氏名を出す（Company::editorStaff()）。
             企業の側だけが更新している企業では、staff_idがnullなので表示を分けている --}}
        <div class="row mt-2">
            <div class="col-sm-3 text-muted">最終更新者</div>
            <div class="col-sm-9">
                @if ($company->editorStaff)
                    <span class="{{ $company->editorStaff->trashed() ? 'text-danger' : '' }}">
                        {{ $company->editorStaff->name }}
                    </span>
                    @if ($company->editorStaff->trashed())
                        <span class="text-danger small">（削除済み）</span>
                    @endif
                @else
                    （未更新）
                @endif
            </div>
        </div>
    </div>
</div>

{{-- この企業の担当者。ログインするのは担当者で、担当者を足すのは企業の側の役目 --}}
<h2 class="h5 mt-4 mb-3">担当者</h2>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>担当者ID</th>
            <th>お名前</th>
            <th>メールアドレス</th>
            <th>登録日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($users as $user)
            <tr>
                <td>{{ $user->login_id }}</td>
                <td>{{ $user->name ?? '（未設定）' }}</td>
                <td>{{ $user->email ?? '（未設定）' }}</td>
                <td>{{ $user->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    {{-- 削除は、担当者の詳細画面で行う --}}
                    <a href="{{ route('admin.companies.users.show', [$company, $user]) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    <a href="{{ route('admin.companies.users.edit', [$company, $user]) }}"
                       class="btn btn-sm btn-outline-primary">編集</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">担当者がいません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- 利用の停止と再開。停止すると担当者はログインできなくなり、ログイン中の担当者も次の操作から使えなくなる --}}
@if ($company->isApproved())
    <div class="mt-3">
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#suspendCompanyModal">
            この企業の利用を停止する
        </button>
    </div>
@elseif ($company->isSuspended())
    <div class="mt-3">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#resumeCompanyModal">
            この企業の利用を再開する
        </button>
    </div>
@endif

{{-- 確認は専用の確認画面ではなく、その場で完結するBootstrapのモーダルにしている
     （admin/staff/show.blade.phpと同じ）。今の状態で使えるものだけを出す --}}
@if ($company->isPending())
    <div class="modal fade" id="approveCompanyModal" tabindex="-1" aria-labelledby="approveCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveCompanyModalLabel">企業会員の承認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $company->name }}」を承認します。担当者がログインできるようになり、
                    担当者へ、承認のお知らせと企業IDをメールで送ります。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.companies.approve', $company) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success">承認する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectCompanyModal" tabindex="-1" aria-labelledby="rejectCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.companies.reject', $company) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectCompanyModalLabel">申請の却下</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            「{{ $company->name }}」の申請を却下します。担当者へ、下の理由をメールで送り、
                            企業と担当者の情報を削除します。削除した情報は元に戻せません。
                        </p>
                        <div class="mb-3">
                            <label for="reason" class="form-label">却下の理由 {!! $rejectRequired['reason'] !!}</label>
                            <textarea id="reason" name="reason" rows="4" class="form-control">{{ old('reason') }}</textarea>
                            <div class="form-text">お知らせのメールに、そのまま載ります。</div>
                            <div class="invalid-feedback" data-item="reason">{{ $errors->reject->first('reason') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-danger">却下する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@elseif ($company->isApproved())
    <div class="modal fade" id="suspendCompanyModal" tabindex="-1" aria-labelledby="suspendCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="suspendCompanyModalLabel">利用の停止</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $company->name }}」の利用を停止します。担当者はログインできなくなり、
                    ログイン中の担当者も次の操作から使えなくなります。担当者へのお知らせは送りません。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.companies.suspend', $company) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-danger">停止する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@elseif ($company->isSuspended())
    <div class="modal fade" id="resumeCompanyModal" tabindex="-1" aria-labelledby="resumeCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resumeCompanyModalLabel">利用の再開</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $company->name }}」の利用を再開し、担当者がログインできる状態に戻します。
                    担当者へのお知らせは送りません。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.companies.resume', $company) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary">再開する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
