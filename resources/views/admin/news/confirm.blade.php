@extends('layouts.admin')

@section('content')
{{-- create.blade.phpと同じ理由で、row/col-md-8による幅制限はせず、
     show.blade.php（詳細画面）と同じ横幅にしている。 --}}
<div class="card">
    <div class="card-header">
        {{ $isCreate ? 'ニュース記事登録の確認' : 'ニュース記事更新の確認' }}
    </div>
    <div class="card-body">
        <p class="text-muted">
            以下の内容で{{ $isCreate ? '登録' : '更新' }}します。
            よろしければ「{{ $isCreate ? '登録する' : '更新する' }}」を押してください。
        </p>

        {{--
            admin/staff/confirm.blade.phpと同じパターン。ここは
            表示専用で、実際に送信するのは下の2つのhiddenだけの
            フォーム。

            一覧用画像・添付ファイルも、他の項目（タイトル・カテゴリー等）
            と同じく_fields.blade.phpが表示する。$readonly=' readonly'
            なので、_ajax_upload_block/_ajax_upload_groupは自動的に
            表示専用のプレビュー（画像またはリンクのみ、アップロードUIは
            出さない）に切り替わる（詳しくは_ajax_upload_blockの
            コメント参照）。
        --}}
        @include('admin.news._fields', [
            'input' => $input,
            'model' => $news,
            'categories' => $categories,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
            ])

        <div class="d-flex gap-2 mt-3">
            <form method="POST" action="{{ $isCreate ? route('admin.news.confirm.create.back') : route('admin.news.confirm.edit.back', $news) }}">
                @csrf
                {{--
                    title・article_date・disp_flg・category_ids・body・
                    一覧用画像・添付ファイルのhiddenを、1項目ずつベタ書き
                    するのではなく、$input（送信される項目だけを集めた
                    配列）から_confirm_hiddenが機械的に組み立てる。項目が
                    増減してもこのconfirm.blade.php自体は直さずに済む
                    （詳しくは_confirm_hiddenのコメント参照）。

                    上の表示に使っているのと同じ$inputをそのまま渡している。
                    $inputには送信される項目だけが入っていて、プレビューの
                    URLのような表示専用の値は、_ajax_upload_blockの中で
                    その場で求めているので、hiddenに紛れ込むことは無い。
                --}}
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-outline-secondary">戻る</button>
            </form>

            <form method="POST" action="{{ $isCreate ? route('admin.news.store') : route('admin.news.update', $news) }}">
                @csrf
                @if (! $isCreate)
                    @method('PATCH')
                @endif
                @include('_confirm_hidden', ['input' => $input])
                <button type="submit" class="btn btn-primary">
                    {{ $isCreate ? '登録する' : '更新する' }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
