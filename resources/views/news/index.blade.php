@extends('layouts.app')

@section('content')
<h1 class="h4 mb-4">お知らせ</h1>

{{--
    カテゴリー・年度の絞り込みフォーム。admin側の検索フォームと同じ
    仕組み（SearchableListトレイトのstoreSearchCondition()）を、
    訪問者側でもそのまま使っている。

    どちらも先頭に「すべて」（value=""）を置いており、この場合は
    $filtersに空文字が入るので、SearchableList::setWhere()の
    「$value === '' なら何もしない」という分岐でそのまま全件表示になる。
--}}
<form method="POST" action="{{ route('news.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-6">
        <select name="category_id" class="form-select" onchange="this.form.submit()">
            <option value="">カテゴリー：すべて</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <select name="year" class="form-select" onchange="this.form.submit()">
            <option value="">年度：すべて</option>
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>
                    {{ $year }}年
                </option>
            @endforeach
        </select>
    </div>
    {{--
        プルダウンを変更した瞬間にonchangeでこのフォーム自体を送信する
        ので、検索ボタンは無くてもよい。ただしJavaScriptを切っている
        ブラウザでも操作できるよう、通常の送信ボタンも残しておく
        （<noscript>ではなく常時表示にしているのは、そのほうが実装が
        シンプルで、表示されていても実害が無いため）。
    --}}
    <div class="col-12">
        <button type="submit" class="btn btn-outline-primary btn-sm">絞り込む</button>
    </div>
</form>

@include('news._list', ['newsList' => $newsList, 'emptyMessage' => '該当するお知らせはありません。'])

{{ $newsList->links('pagination::bootstrap-5') }}
@endsection
