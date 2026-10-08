@extends('layouts.admin')

{{-- 本文を、エディタの中と同じ見た目で表示するためのCSS --}}
@push('head-extra')
    @vite(['resources/css/wysiwyg_suneditor_content.css'])
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">固定ページ詳細</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.pages.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">一覧へ戻る</a>
        {{-- 訪問者の側の画面を、別のタブで開く。非表示のページは404になるので出さない --}}
        @if ($page->disp_flg)
            <a href="{{ route('pages.show', $page->slug) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">公開中のページを開く</a>
        @endif
        <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-primary">編集する</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @include('admin.pages._fields', [
            'input' => $input,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
        ])

        <div class="row">
            <div class="col-sm-3 text-muted">更新日時</div>
            <div class="col-sm-9">{{ $page->updated_at->format('Y年n月j日 H:i') }}</div>
        </div>
    </div>
</div>

{{-- 削除は、その場で完結するモーダル --}}
<div class="mt-3">
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deletePageModal">
        このページを削除する
    </button>
</div>

<div class="modal fade" id="deletePageModal" tabindex="-1" aria-labelledby="deletePageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePageModalLabel">固定ページの削除</h5>
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
@endsection
