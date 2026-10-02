@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">お知らせ詳細</h1>
    {{--
        ?backを付けて一覧に戻る。SearchableList::buildListData()が
        これを見て「一覧の何ページ目を見ていたか」をセッションから
        復元してリダイレクトする（admin側の?backとまったく同じ
        仕組み。詳しくはSearchableList.phpのコメント参照）。
    --}}
    <a href="{{ route('news.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">
        一覧へ戻る
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="text-muted small mb-2">{{ $news->article_date->format('Y年n月j日') }}</div>

        <h2 class="h5 mb-2">{{ $news->title }}</h2>

        <div class="mb-3">
            @foreach ($news->categories as $category)
                <span class="badge text-bg-secondary">{{ $category->name }}</span>
            @endforeach
        </div>

        {{-- 一覧用画像（未登録なら出さない）。$news->list_image_urlは
             Newsモデルのアクセサ。 --}}
        @if ($news->list_image_url)
            <div class="mb-3">
                <img src="{{ $news->list_image_url }}" alt="" style="max-width: 90%;" class="rounded">
            </div>
        @endif

        {{--
            bodyはWYSIWYGエディタで入力したHTMLなので、{{ }}でエスケープすると
            タグが文字として見えてしまう。そのため{!! !!}で出力するが、
            必ずsafe_html()で許可リスト以外のタグ・属性を取り除いてから出す。

            エディタが制限しているのはエディターから入力できるものだけで、
            直接POSTすれば任意のHTMLを保存できてしまうので、エディタの
            制限を安全対策として当てにしてはいけない。

            保存時にもAdmin\NewsController（確認画面の表示・登録・更新）で
            同じ処理を掛けているので、通常は二重になるが、DBを直接書き換えた
            場合など、保存時の処理を通っていない本文があっても安全に表示できる
            よう、表示時にも掛けている。
        --}}
        <div class="news-body wysiwyg-content">
            {!! safe_html($news->body) !!}
        </div>

        {{-- 添付ファイル（1件も無ければ見出しごと出さない）。リンク先は
             NewsAttachmentモデルのアクセサ$attachment->url、表示名は
             アップロード時の元のファイル名。download属性で、保存される
             ファイル名も元のファイル名にしている（サーバー上のファイル名は
             ランダムな名前のため）。download属性はリンク先が同じサイトの
             URLのときだけ効くので、filesystems.phpのurlは"/"で始まる
             パスにしておくこと。
             表示名が無い（CSV取り込みで空欄にした等）ときは、リンクの文字を
             「添付ファイル1」のようにする。download属性の値は空になり、保存される
             ファイル名はブラウザが決める（サーバー上のファイル名になる）。 --}}
        @if ($news->attach->isNotEmpty())
            <div class="mt-4">
                <h3 class="h6">添付ファイル</h3>
                <ul class="ps-3 mb-0">
                    @foreach ($news->attach as $attachment)
                        <li>
                            <a href="{{ $attachment->url }}" target="_blank" download="{{ $attachment->original_name }}">{{ $attachment->original_name ?: '添付ファイル'.$loop->iteration }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
