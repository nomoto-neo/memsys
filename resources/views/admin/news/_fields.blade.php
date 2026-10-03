{{--
    ニュース記事情報（タイトル・記事日付・表示／非表示・公開範囲・
    掲載カテゴリー・本文）の入力欄一式。

    admin/staff・admin/membersの_fields.blade.phpと同じ考え方で、
    create（新規登録）・edit（編集）・confirm（登録/更新の確認画面）・
    show（詳細表示）の4画面すべてから、この同じタグをそのまま呼び出す。

    呼び出し側が用意する変数：
    - $input     画面に表示する値の配列。送信される項目（title/
                 article_date/disp_flg/members_only/category_ids/body、および
                 list_image・attach系のhidden値）だけが入っている。
                 create/editではold()を優先したデフォルト値、confirmでは
                 直前に検証した確認前データ、showでは対象記事の現在値を、
                 それぞれ呼び出し側（コントローラー）で組み立てて渡す。
                 category_idsは選択中のカテゴリーidの配列。
                 表示専用の値（プレビューのURLなど）は入っていない
                 （理由は_confirm_hiddenのコメント参照）。
    - $model     対象の記事（$news）。新規登録の画面（create・新規登録の
                 confirm）ではnull。アップロード欄で、保存済みファイルの
                 URLを求めるのに使う。
    - $categories 選択肢として並べる全カテゴリー（表示順）。
    - $readonly  text/date系inputに付ける文字列（' readonly'または''）。
    - $disabled  ラジオボタン・チェックボックスに付ける文字列
                 （' disabled'または''）。
    - $required  必須マークのHTML配列。閲覧専用画面では[]でよい。

    一覧用画像(list_image)・添付ファイル(attach)のアップロード欄は、
    4画面すべてでここに表示する。$readonly（他のtext input等と同じ値）を
    そのまま_ajax_upload_block/_ajax_upload_groupへ渡すことで、
    入力用のインタラクティブなUI（create/edit）と、表示専用のプレビュー
    （confirm/show）が自動的に切り替わる（詳しくは
    _ajax_upload_blockのコメント参照）。どちらの部分ビューにも
    $model・$input・フィールド名を渡すだけで、hiddenの値もプレビューの
    URLも、部分ビューの中で$inputから組み立てられる。

    Ajaxアップロード先のURLは、このコーナーで1つに決まっているので、
    呼び出し側から受け取らずここで直接route()を呼ぶ（confirm/showの
    読み取り専用モードでは値が使われないだけなので、渡すのをやめる
    必要はない）。
--}}
@php
    $uploadUrl = route('admin.news.ajaxUpload');
@endphp
<div class="mb-3">
    <label for="title" class="form-label">タイトル {!! $required['title'] ?? '' !!}</label>
    <input id="title" type="text" name="title"
           class="form-control"
           value="{{ $input['title'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="title">{{ $errors->first('title') }}</div>
</div>

<div class="mb-3">
    <label for="article_date" class="form-label">記事日付 {!! $required['article_date'] ?? '' !!}</label>
    <input id="article_date" type="date" name="article_date"
           class="form-control"
           value="{{ $input['article_date'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="article_date">{{ $errors->first('article_date') }}</div>
</div>

<div class="mb-3">
    <div class="form-label d-block">表示／非表示 {!! $required['disp_flg'] ?? '' !!}</div>
    {{--
        ラジオボタンにしているのは、チェックボックス1つ（チェック=表示）
        だと未チェック時は項目自体が送信されず、「必須」の意味が
        あいまいになるため。ラジオボタンなら「表示・非表示のどちらかを
        必ず選ぶ」という必須の意味がそのままHTML構造に現れる。
    --}}
    <div class="form-check form-check-inline">
        <input id="disp_flg_1" type="radio" name="disp_flg" value="1"
               class="form-check-input"
               @checked(($input['disp_flg'] ?? '0') == '1'){{ $disabled }}>
        <label for="disp_flg_1" class="form-check-label">表示</label>
    </div>
    <div class="form-check form-check-inline">
        <input id="disp_flg_0" type="radio" name="disp_flg" value="0"
               class="form-check-input"
               @checked(($input['disp_flg'] ?? '0') == '0'){{ $disabled }}>
        <label for="disp_flg_0" class="form-check-label">非表示</label>
    </div>
    <div class="invalid-feedback" data-item="disp_flg">{{ $errors->first('disp_flg') }}</div>
</div>

<div class="mb-3">
    <div class="form-label d-block">公開範囲 {!! $required['members_only'] ?? '' !!}</div>
    {{-- 会員限定の記事は、ログインしていない人には一覧にも詳細にも出ない。
         画像・添付ファイルも同じ（App\Policies\NewsPolicy）。 --}}
    <div class="form-check form-check-inline">
        <input id="members_only_0" type="radio" name="members_only" value="0"
               class="form-check-input"
               @checked(($input['members_only'] ?? '0') == '0'){{ $disabled }}>
        <label for="members_only_0" class="form-check-label">一般公開</label>
    </div>
    <div class="form-check form-check-inline">
        <input id="members_only_1" type="radio" name="members_only" value="1"
               class="form-check-input"
               @checked(($input['members_only'] ?? '0') == '1'){{ $disabled }}>
        <label for="members_only_1" class="form-check-label">会員限定</label>
    </div>
    <div class="invalid-feedback" data-item="members_only">{{ $errors->first('members_only') }}</div>
</div>

<div class="mb-3">
    <label class="form-label d-block">一覧用画像</label>
    @include('_ajax_upload_block', [
        'model' => $model,
        'input' => $input,
        'field' => 'list_image',
        'width' => \App\Models\News::LIST_IMAGE_WIDTH,
        'readonly' => $readonly,
        'uploadUrl' => $uploadUrl,
    ])
</div>

<div class="mb-3">
    <div class="form-label d-block">掲載カテゴリー（複数選択可） {!! $required['category_ids'] ?? '' !!}</div>
    {{--
        <select multiple>（都道府県の検索フォームで使っている書き方）とは
        違い、name="category_ids[]"が同じ、独立したチェックボックスを
        並べる形にしている。チェックが入っているものだけが、それぞれ
        個別にcategory_ids[]としてPOSTされる。
    --}}
    @foreach ($categories as $category)
        <div class="form-check">
            <input id="category_ids_{{ $category->id }}" type="checkbox"
                   name="category_ids[]" value="{{ $category->id }}"
                   class="form-check-input"
                   @checked(in_array($category->id, $input['category_ids'] ?? [])){{ $disabled }}>
            <label for="category_ids_{{ $category->id }}" class="form-check-label">
                {{ $category->name }}
            </label>
        </div>
    @endforeach
    <div class="invalid-feedback" data-item="category_ids">{{ $errors->first('category_ids') }}</div>
</div>

<div class="mb-3">
    <label for="body" class="form-label">本文</label>
    {{-- $disabledは' disabled'または''（他の項目と同じ値）。本文欄には
         disabled属性は無いので直接は使わないが、真偽値としては
         「create/editで空、confirm/showで埋まっている」という同じ
         判断からできているので、これで表示・編集を切り替える。 --}}
    @if (! $disabled)
        {{-- class="wysiwyg"のtextareaは、画面が読み込んだエディタ用の
             スクリプト（resources/js/wysiwyg_ckeditor.jsなど）がWYSIWYG
             エディタに置き換える。data-upload-urlは、エディタに挿入した
             画像の送り先（一覧用画像などと同じAjaxアップロード）。 --}}
        <textarea id="body" name="body"
                  class="form-control wysiwyg" rows="10"
                  data-upload-url="{{ $uploadUrl }}"
        >{{ $input['body'] ?? '' }}</textarea>
        <div class="invalid-feedback" data-item="body">{{ $errors->first('body') }}</div>
    @else
        {{-- confirm/showでは、エディタを起動し直すのではなく、保存される
             HTMLをレンダリングした結果を見せるだけにしている。{!! !!}で
             出力するので、必ずsafe_html()で許可リスト以外のタグ・属性を
             取り除いてから出す（管理画面でスクリプトが動くと、開いた管理者の
             権限で操作されてしまうため。詳しくはApp\Support\HtmlSanitizer
             のコメント参照）。 --}}
        <div class="form-control wysiwyg-content" style="height: auto; min-height: 4rem;">
            @if (filled($input['body'] ?? null))
                {!! safe_html($input['body']) !!}
            @else
                （本文なし）
            @endif
        </div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label d-block">添付ファイル</label>
    @include('_ajax_upload_group', [
        'model' => $model,
        'input' => $input,
        'field' => 'attach',
        'width' => 0,
        'readonly' => $readonly,
        'uploadUrl' => $uploadUrl,
    ])
</div>
