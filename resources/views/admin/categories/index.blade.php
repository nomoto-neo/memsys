@extends('layouts.admin')

@push('head-extra')
@vite(['resources/js/sortable.js'])
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">カテゴリー一覧</h1>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm">新規登録</a>
</div>

{{--
    並び替えはドラッグ操作の結果をこの<form>でまとめて送信する。
    削除の確認モーダルは、admin/staff/index.blade.phpと同じ理由
    （<form>の中に<form>は入れ子にできない）で、この<form>の外に
    別立てで置いている。
--}}
<form id="category-order-form" method="POST" action="{{ route('admin.categories.reorder') }}">
    @csrf
    @method('PATCH')

    <ul id="category-list" class="list-group mb-3">
        @foreach ($categories as $category)
            <li class="list-group-item d-flex align-items-center" data-id="{{ $category->id }}">
                {{-- ⠿はドラッグの持ち手であることを示す記号（テキストなので
                     アイコン用の追加ライブラリは不要）。 --}}
                <span class="drag-handle me-2" style="cursor: grab;">⠿</span>
                <span class="flex-grow-1">{{ $category->name }}</span>
                <span class="text-muted small me-3">{{ $category->news_count }}件で使用中</span>
                <a href="{{ route('admin.categories.edit', $category) }}"
                   class="btn btn-sm btn-outline-primary me-2">編集</a>
                {{-- 使用中（news_count > 0）は削除ボタン自体を無効化する。
                     CategoryController::destroy()側でも同じ条件を
                     再チェックしているので、URL直打ちでも安全。 --}}
                <button type="button" class="btn btn-sm btn-outline-danger"
                        @disabled($category->news_count > 0)
                        data-bs-toggle="modal" data-bs-target="#deleteCategoryModal-{{ $category->id }}">
                    削除
                </button>
            </li>
        @endforeach
    </ul>

    <button type="submit" class="btn btn-primary">並び替えて更新</button>
</form>

@foreach ($categories as $category)
    @if ($category->news_count === 0)
        <div class="modal fade" id="deleteCategoryModal-{{ $category->id }}" tabindex="-1"
             aria-labelledby="deleteCategoryModalLabel-{{ $category->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteCategoryModalLabel-{{ $category->id }}">カテゴリーの削除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        「{{ $category->name }}」を削除します。この操作は取り消せません。よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">削除する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script>
    {{-- SortableJSでドラッグ並び替えを有効にする。handle: '.drag-handle'を
         指定しているので、行全体ではなく⠿の部分をつかんだときだけ
         ドラッグが始まる（誤操作で「編集」「削除」ボタンをドラッグして
         しまうのを防ぐため）。 --}}
    document.addEventListener('DOMContentLoaded', () => {
        const list = document.getElementById('category-list');
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
        });

        {{-- 送信直前に、今のDOM上の並び順（li[data-id]の出現順）から
             order[]という名前のhiddenをまとめて作り直す。サーバー側は
             この配列のインデックス（0, 1, 2, ...）をそのまま新しい
             display_orderとして書き込む（CategoryController::reorder()参照）。 --}}
        document.getElementById('category-order-form').addEventListener('submit', (event) => {
            const form = event.target;

            form.querySelectorAll('input[name="order[]"]').forEach((el) => el.remove());

            list.querySelectorAll('li[data-id]').forEach((li) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order[]';
                input.value = li.dataset.id;
                form.appendChild(input);
            });
        });
    });
</script>
@endpush
@endsection
