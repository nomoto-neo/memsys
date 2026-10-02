@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">ニュース記事詳細</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.news.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">一覧へ戻る</a>
        <a href="{{ route('admin.news.edit', $news) }}" class="btn btn-sm btn-primary">編集する</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{--
            一覧用画像・添付ファイルも、他の項目と同じく_fields.blade.php
            が表示する。$readonly=' readonly'なので、confirm.blade.phpと
            同じく表示専用のプレビューに自動的に切り替わる（詳しくは
            _ajax_upload_blockのコメント参照）。$inputは
            コントローラー（NewsController::show()）が組み立て済み。
        --}}
        @include('admin.news._fields', [
            'input' => $input,
            'model' => $news,
            'categories' => $news->categories,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
            ])

        <div class="row">
            <div class="col-sm-3 text-muted">登録日時</div>
            <div class="col-sm-9">{{ $news->created_at->format('Y年n月j日 H:i') }}</div>
        </div>
    </div>
</div>

{{-- 削除はadmin/staff/show.blade.phpと同じ、その場で完結するモーダル。 --}}
<div class="mt-3">
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteNewsModal">
        この記事を削除する
    </button>
</div>

<div class="modal fade" id="deleteNewsModal" tabindex="-1" aria-labelledby="deleteNewsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteNewsModalLabel">ニュース記事の削除</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                「{{ $news->title }}」を削除します。この操作は取り消せません。よろしいですか？
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                <form method="POST" action="{{ route('admin.news.destroy', $news) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">削除する</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
