{{--
    固定ページ（タイトル・URLの名前・表示／非表示・本文）の入力欄一式。
    新規登録・編集・確認・詳細の4画面から、この同じタグを呼び出す。

    呼び出し側が用意する変数：
    - $input     画面に表示する値の配列。rules()にある項目のキーは必ず入っているので、
                  ?? '' は付けずに書ける
    - $readonly  text系のinputに付ける文字列（' readonly'または''）
    - $disabled  ラジオボタンに付ける文字列（' disabled'または''）。本文を、入力欄で出すか
                  表示だけにするかも、これで切り替える
    - $required  必須マークのHTMLの配列。表示だけの画面では[]でよい

    本文は、入力の画面ではCKEditorに置き換わる（画面が読み込むresources/js/wysiwyg_ckeditor.js）。
    確認・詳細の画面では、保存されるHTMLを表示するだけにする。
--}}
<div class="mb-3">
    <label for="title" class="form-label">タイトル {!! $required['title'] ?? '' !!}</label>
    <input id="title" type="text" name="title"
           class="form-control"
           value="{{ $input['title'] }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="title">{{ $errors->first('title') }}</div>
</div>

<div class="mb-3">
    <label for="slug" class="form-label">URLの名前 {!! $required['slug'] ?? '' !!}</label>
    {{-- 訪問者の側のURLは、この名前がそのままパスになる。aboutusなら /aboutus --}}
    <div class="input-group">
        <span class="input-group-text">{{ url('/') }}/</span>
        <input id="slug" type="text" name="slug"
               class="form-control"
               value="{{ $input['slug'] }}" placeholder="例: aboutus"{{ $readonly }}>
    </div>
    <div class="form-text">半角の英小文字・数字・ハイフンで入力します。ログイン（login）のように、ほかの画面で使っている名前は使えません。</div>
    <div class="invalid-feedback" data-item="slug">{{ $errors->first('slug') }}</div>
</div>

<div class="mb-3">
    <div class="form-label d-block">表示／非表示 {!! $required['disp_flg'] ?? '' !!}</div>
    {{-- 非表示のページは、訪問者の側でURLを開いても404になる --}}
    <div class="form-check form-check-inline">
        <input id="disp_flg_1" type="radio" name="disp_flg" value="1"
               class="form-check-input"
               @checked(hit($input['disp_flg'], 1)){{ $disabled }}>
        <label for="disp_flg_1" class="form-check-label">表示</label>
    </div>
    <div class="form-check form-check-inline">
        <input id="disp_flg_0" type="radio" name="disp_flg" value="0"
               class="form-check-input"
               @checked(hit($input['disp_flg'], 0)){{ $disabled }}>
        <label for="disp_flg_0" class="form-check-label">非表示</label>
    </div>
    <div class="invalid-feedback" data-item="disp_flg">{{ $errors->first('disp_flg') }}</div>
</div>

<div class="mb-3">
    <label for="body" class="form-label">本文 {!! $required['body'] ?? '' !!}</label>
    @if (! $disabled)
        {{-- class="wysiwyg"のtextareaを、画面が読み込んだエディタ用のスクリプトがCKEditorに置き換える。
             data-upload-urlは、エディタに挿入した画像の送り先 --}}
        <textarea id="body" name="body"
                  class="form-control wysiwyg" rows="10"
                  data-upload-url="{{ route('admin.pages.ajaxUpload') }}"
        >{{ $input['body'] }}</textarea>
        <div class="invalid-feedback" data-item="body">{{ $errors->first('body') }}</div>
    @else
        {{-- 確認・詳細では、保存されるHTMLを表示するだけにする。{!! !!}で出すので、必ずsafe_html()で
             許可していないタグと属性を取り除く（App\Support\HtmlSanitizer）。
             class="ck-content"は、CKEditorの表示用のCSS（wysiwyg_ckeditor_content.css）が効く印 --}}
        <div class="form-control wysiwyg-content ck-content" style="height: auto; min-height: 4rem;">
            @if (filled($input['body']))
                {!! safe_html($input['body']) !!}
            @else
                （本文なし）
            @endif
        </div>
    @endif
</div>
