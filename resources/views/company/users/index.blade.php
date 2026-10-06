@extends('layouts.company')

@section('title', '担当者の管理')

{{--
    同じ企業の担当者の一覧と、招待中の人の一覧。どの担当者も、招待と、ほかの担当者の編集・削除ができる。
    $meはログイン中の担当者、$usersは担当者、$invitationsは期限内の招待。
--}}
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">担当者の管理</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('company.mypage') }}" class="btn btn-sm btn-outline-secondary">マイページへ戻る</a>
        <a href="{{ route('company.users.invite') }}" class="btn btn-sm btn-primary">担当者を招待する</a>
    </div>
</div>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>担当者ID</th>
            <th>お名前</th>
            <th>メールアドレス</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->login_id }}</td>
                <td>
                    {{ $user->name }}
                    @if ($user->is($me))
                        <span class="badge text-bg-secondary">あなた</span>
                    @endif
                </td>
                <td>{{ $user->email }}</td>
                <td class="text-end">
                    {{-- 自分の情報は専用の画面で変える。自分自身は削除できないので、削除のボタンも出さない --}}
                    @if ($user->is($me))
                        <a href="{{ route('company.mypage.profile') }}" class="btn btn-sm btn-outline-primary">編集</a>
                    @else
                        <a href="{{ route('company.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">編集</a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteUserModal-{{ $user->id }}">
                            削除
                        </button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- 招待中の人。招待のメールのリンクから登録が済むと、上の担当者の一覧に移る。
     期限を過ぎた招待は、ここから消える --}}
<h2 class="h5 mt-4 mb-3">招待中</h2>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>メールアドレス</th>
            <th>リンクの期限</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invitations as $invitation)
            <tr>
                <td>{{ $invitation->email }}</td>
                <td>{{ $invitation->expires_at->format('Y年n月j日 H:i') }}</td>
                <td class="text-end">
                    <form method="POST" action="{{ route('company.users.invitations.resend', $invitation) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">送り直す</button>
                    </form>
                    <form method="POST" action="{{ route('company.users.invitations.cancel', $invitation) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">取り消す</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center text-muted">招待中の人はいません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- 削除確認のモーダルは<table>の外にまとめて置く（<tbody>の直下には<tr>しか置けないため）。
     モーダルを動かすBootstrapのJavaScriptは、この画面でだけ読み込む --}}
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@endpush

@foreach ($users as $user)
    @continue($user->is($me))
    <div class="modal fade" id="deleteUserModal-{{ $user->id }}" tabindex="-1"
         aria-labelledby="deleteUserModalLabel-{{ $user->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel-{{ $user->id }}">担当者の削除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $user->name }}」さんを削除します。削除した担当者はログインできなくなり、元に戻せません。
                    よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('company.users.destroy', $user) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">削除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
