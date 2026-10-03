@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">ニュース記事一覧・検索</h1>
    <div class="d-flex gap-2">
        {{-- 今の検索条件・並び順で、全件をCSVにする（ページ分けはしない） --}}
        <a href="{{ route('admin.news.csv') }}" class="btn btn-outline-secondary btn-sm">CSVダウンロード</a>
        {{-- ダウンロードしたCSVを直して、そのまま取り込める（記事IDが空欄の行は追加） --}}
        <a href="{{ route('admin.news.csv-import') }}" class="btn btn-outline-secondary btn-sm">CSV取り込み</a>
        <a href="{{ route('admin.news.create') }}" class="btn btn-primary btn-sm">新規登録</a>
    </div>
</div>

{{--
    name属性は検索対象のカラム名のまま。NewsController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - q：フリーワード（対象はtitle）
    - disp_flg：真偽値の完全一致（booleanで検証しているので自動で完全一致）
    - category_id：t_newsのカラムではないので、NewsController::applyCustomSearch()で
      多対多の絞り込みとして処理する
    - orderby：並び順。NewsController::ORDER_OPTIONSに定義した選択肢を
      そのまま並べているだけ。
--}}
<form method="POST" action="{{ route('admin.news.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-3">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="タイトル"
               value="{{ $filters['q'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="disp_flg" class="form-select">
            <option value="">状態（すべて）</option>
            <option value="1" @selected(($filters['disp_flg'] ?? '') === '1')>表示</option>
            <option value="0" @selected(($filters['disp_flg'] ?? '') === '0')>非表示</option>
        </select>
    </div>
    <div class="col-sm-2">
        <select name="category_id" class="form-select">
            <option value="">カテゴリー（すべて）</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
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
            <th>タイトル</th>
            <th>記事日付</th>
            <th>状態</th>
            <th>登録日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($newsList as $news)
            <tr>
                <td>
                    {{ $news->title }}
                    @if ($news->members_only)
                        <span class="badge text-bg-warning">会員限定</span>
                    @endif
                </td>
                <td>{{ $news->article_date->format('Y-m-d') }}</td>
                <td>
                    @if ($news->disp_flg)
                        <span class="badge text-bg-primary">表示</span>
                    @else
                        <span class="badge text-bg-secondary">非表示</span>
                    @endif
                </td>
                <td>{{ $news->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.news.show', $news) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    <a href="{{ route('admin.news.edit', $news) }}"
                       class="btn btn-sm btn-outline-primary">編集</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#deleteNewsModal-{{ $news->id }}">
                        削除
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">該当する記事が見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $newsList->links('pagination::bootstrap-5') }}

{{-- 削除確認のモーダルはadmin/staff/index.blade.phpと同じ理由で
     <table>の外にまとめて置く。 --}}
@foreach ($newsList as $news)
    <div class="modal fade" id="deleteNewsModal-{{ $news->id }}" tabindex="-1"
         aria-labelledby="deleteNewsModalLabel-{{ $news->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteNewsModalLabel-{{ $news->id }}">ニュース記事の削除</h5>
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
@endforeach
@endsection
