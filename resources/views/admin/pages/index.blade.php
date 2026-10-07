@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">固定ページ一覧・検索</h1>
    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary btn-sm">新規登録</a>
</div>

{{--
    name属性は検索対象のカラム名のまま。コントローラーのsrchRules()に載っている項目だけが
    検索条件として保存される。$filtersには検索の項目のキーが必ず入っているので、?? '' は付けない。

    - q：フリーワード（対象はタイトルとURLの名前）
    - disp_flg：真偽値の完全一致
    - orderby：並び順。コントローラーのORDER_OPTIONSの選択肢を並べているだけ
--}}
<form method="POST" action="{{ route('admin.pages.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-5">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="タイトル・URLの名前"
               value="{{ $filters['q'] }}">
    </div>
    <div class="col-sm-3">
        <select name="disp_flg" class="form-select">
            <option value="">状態（すべて）</option>
            <option value="1" @selected(hit($filters['disp_flg'], 1))>表示</option>
            <option value="0" @selected(hit($filters['disp_flg'], 0))>非表示</option>
        </select>
    </div>
    <div class="col-sm-3">
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
            <th>タイトル</th>
            <th>URL</th>
            <th>状態</th>
            <th>更新日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pages as $page)
            <tr>
                <td>{{ $page->title }}</td>
                <td>
                    {{-- 表示にしているページだけ、訪問者の側の画面へのリンクにする --}}
                    @if ($page->disp_flg)
                        <a href="{{ route('pages.show', $page->slug) }}" target="_blank" rel="noopener">/{{ $page->slug }}</a>
                    @else
                        /{{ $page->slug }}
                    @endif
                </td>
                <td>
                    @if ($page->disp_flg)
                        <span class="badge text-bg-primary">表示</span>
                    @else
                        <span class="badge text-bg-secondary">非表示</span>
                    @endif
                </td>
                <td>{{ $page->updated_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.pages.show', $page) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    <a href="{{ route('admin.pages.edit', $page) }}"
                       class="btn btn-sm btn-outline-primary">編集</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#deletePageModal-{{ $page->id }}">
                        削除
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">該当するページが見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $pages->links('pagination::bootstrap-5') }}

{{-- 削除確認のモーダルは<table>の外にまとめて置く（<tbody>の直下には<tr>しか置けないため） --}}
@foreach ($pages as $page)
    <div class="modal fade" id="deletePageModal-{{ $page->id }}" tabindex="-1"
         aria-labelledby="deletePageModalLabel-{{ $page->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePageModalLabel-{{ $page->id }}">固定ページの削除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $page->title }}」を削除します。この操作は取り消せません。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}">
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
