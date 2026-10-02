@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">スタッフ一覧・検索</h1>
    <a href="{{ route('admin.staff.create') }}" class="btn btn-primary btn-sm">新規登録</a>
</div>

{{--
    name属性は検索対象のカラム名のまま。StaffController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - q：フリーワード（対象はname）
    - email：単項目検索（文字列なので部分一致）
    - acl：権限での絞り込み（Rule::inで検証しているので完全一致）。
      プルダウンなので複数選択にはしていない
      （都道府県の複数選択とは違い、「スタッフ」か「管理者」かの2択しか無いため）。
    - with_trashed：「削除済みも含める」。削除済み（論理削除）のスタッフも一覧に出す
      （StaffController::applyCustomSearch()で処理）。削除済みの行は<tr>にrow-deletedを付けて
      赤字で表示し（CSSはlayouts/admin.blade.php）、詳細画面から削除を取り消せる。
    - orderby：並び順。StaffController::ORDER_OPTIONSに定義した選択肢を
      そのまま並べているだけなので、選択肢を増減させてもここは直さなくてよい。
      他の検索条件と同じくsearch_session経由で保存・復元される。
    action先がroute('admin.staff.search')になっている点に注意。会員の検索は
    route('admin.members.search')＝/admin/membersそのものだが、スタッフは
    /admin/staffが新規登録(store)に使われているため、検索条件の保存だけ
    /admin/staff/searchという別パスに分けている。
--}}
<form method="POST" action="{{ route('admin.staff.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-3">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="お名前"
               value="{{ $filters['q'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="text" name="email" class="form-control" placeholder="メールアドレス"
               value="{{ $filters['email'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="acl" class="form-select">
            <option value="">権限（すべて）</option>
            @foreach (code_table('staff_acl') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['acl'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2 d-flex align-items-center">
        <div class="form-check mb-0">
            <input type="checkbox" name="with_trashed" value="1" id="with_trashed" class="form-check-input"
                   @checked(($filters['with_trashed'] ?? '') === '1')>
            <label for="with_trashed" class="form-check-label">削除済みも含める</label>
        </div>
    </div>
    <div class="col-sm-2">
        <select name="orderby" class="form-select">
            @foreach ($orderOptions as $key => $option)
                <option value="{{ $key }}" @selected($selectedOrder === $key)>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-1">
        <button type="submit" class="btn btn-primary w-100">検索</button>
    </div>
</form>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>お名前</th>
            <th>メールアドレス</th>
            <th>権限</th>
            <th>登録日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($staffList as $staff)
            <tr @class(['row-deleted' => $staff->trashed()])>
                <td>
                    {{ $staff->name }}
                    @if ($staff->trashed())
                        <span class="small">（削除済み）</span>
                    @endif
                </td>
                <td>{{ $staff->email }}</td>
                <td>
                    @if ($staff->isManager())
                        <span class="badge text-bg-primary">管理者</span>
                    @else
                        <span class="badge text-bg-secondary">スタッフ</span>
                    @endif
                </td>
                <td>{{ $staff->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.staff.show', $staff) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    {{-- 編集・削除のボタンは、StaffPolicyで許されるときだけ出す
                         （削除済みの行・自分自身の行には削除を出さない。show.blade.phpと同じ判断）。 --}}
                    @can('update', $staff)
                        <a href="{{ route('admin.staff.edit', $staff) }}"
                           class="btn btn-sm btn-outline-primary">編集</a>
                    @endcan
                    @can('delete', $staff)
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteStaffModal-{{ $staff->id }}">
                            削除
                        </button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">該当するスタッフが見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $staffList->links('pagination::bootstrap-5') }}

{{--
    削除確認のモーダルは<table>の外にまとめて置く。<tbody>の直下に置ける
    要素は<tr>だけと決まっており、<tr>と並べて<div>（モーダル）を
    置くとHTMLとして不正になる（ブラウザが辻褄合わせで要素を
    テーブルの外へ移動させてしまい、見た目やJSの動きが不安定になりうる）ため。
--}}
@foreach ($staffList as $staff)
    @can('delete', $staff)
        <div class="modal fade" id="deleteStaffModal-{{ $staff->id }}" tabindex="-1"
             aria-labelledby="deleteStaffModalLabel-{{ $staff->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteStaffModalLabel-{{ $staff->id }}">スタッフの削除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        「{{ $staff->name }}」さんを削除します。削除したスタッフはログインできなくなります。
                        （一覧で「削除済みも含める」にチェックを入れて検索すると表示でき、詳細画面から削除を取り消せます。）
                        よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">削除する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endforeach
@endsection
