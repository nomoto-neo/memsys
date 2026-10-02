{{--
    画像・添付ファイル1枠分の表示。$readonlyの値によって、次の2通りに
    切り替わる。

    - $readonlyが空（create/edit）: Ajaxアップロード用のインタラクティブな
      UI一式（App\Support\AjaxFileUploadトレイト・resources/js/ajax_upload.js
      と対応するマークアップ）。1枠が.ajax_upload_blockという単位になって
      いて、複数展開フィールド(attachなど)はこの単位ごと増減する。
    - $readonlyが空でない（confirm/show）: 表示専用の、ごく単純な
      プレビュー（画像またはリンク1つだけ）。file input・ドロップ
      エリア・hiddenの類は一切出さない。

    こうしておくことで、resources/views/admin/news/_fields.blade.phpは
    create・edit・confirm・showの4画面すべてから同じ書き方でこの部分
    ビューを呼べる（呼び出し側で画面ごとに書き分ける必要が無い）。

    App\Support\AjaxFileUploadトレイト自体が管理画面専用の仕組みではない
    （どのコントローラーでも use できる）のと同じく、この部分ビューも
    admin/配下ではなくresources/views直下に置いている
    （@include('_ajax_upload_block', ...)）。訪問者向けの問い合わせ
    フォーム（resources/views/contact/create.blade.php・confirm.blade.php）も、
    この同じ部分ビューを添付ファイル欄にそのまま使う（_confirm_hidden.blade.php
    と同じ理由）。

    呼び出し側が用意する変数:
    - $model       ファイルを持っているレコード（ニュースなら$news）。
                   新規登録の画面ではnull。複数展開の添付ファイルでも、
                   保存先は親のディレクトリなので親のレコードを渡す。
    - $input       画面の入力値の配列（コントローラーが組み立てた$input
                   そのもの）。この部分ビューは、その中から{field}・
                   {field}_origin・{field}_tmp・{field}_delを自分で読む。
    - $field       []や.*を含まない、素のフィールド名(例: "list_image","attach")
    - $idx         (省略可)複数展開フィールドの何行目か。省略(null)なら
                   単独のフィールド。整数なら複数展開フィールドの$idx番目の
                   行で、name属性の末尾に[]が付く。$inputからの値の取り出し方
                   （upload_input_value()・upload_preview_url()）と同じ意味。
    - $width       横幅(px)。0なら添付ファイル、それ以外なら画像。
                   サーバー側(AjaxFileUpload::resizeIfNeeded())で既に
                   この横幅を超えないようリサイズ済みなので、<img>の
                   表示サイズはpx指定ではなく、画面の幅に応じて縮む
                   max-width:90%にしている（$widthは判定・ドロップ
                   エリアの案内文・サーバー側リサイズにのみ使う）。
    - $readonly    ' readonly'または''（他のtext input等と同じ値をそのまま
                   渡せばよい。空でなければ読み取り専用モードになる）
    - $uploadUrl   (読み取り専用モードでは未使用)Ajaxアップロード先のURL

    表示するURLは、コントローラーから受け取るのではなく、ここで
    upload_preview_url()を呼んで$inputの値から求める。hiddenに出す値と
    プレビューのURLが、どちらも同じ$inputから作られるので、食い違うことが
    無い（$inputに表示専用の値を混ぜない理由は_confirm_hiddenのコメント参照）。
--}}
@php
    $idx ??= null;
    $uploadUrl ??= null;

    $isImage = $width > 0;
    $suffix = $idx === null ? '' : '[]';
    $nameValue = $field.$suffix;
    $nameTmp = $field.'_tmp'.$suffix;
    $nameOrigin = $field.'_origin'.$suffix;
    $nameDel = $field.'_del'.$suffix;

    // $idxがnullか整数か、配列かどうか、の判断はヘルパーの中で行う
    // （upload_input_value()のコメント参照）。
    $value = upload_input_value($input, $field, $idx);
    $origin = upload_input_value($input, $field.'_origin', $idx);
    $tmp = upload_input_value($input, $field.'_tmp', $idx);
    $del = upload_input_value($input, $field.'_del', $idx) === '1';
    $url = upload_preview_url($model, $input, $field, $idx);
    $hasFile = $url !== null;
    // 添付ファイルのリンクの文字。表示名が無い（CSV取り込みで空欄にした等）ときは「添付ファイル1」のようにする
    $linkText = ($origin ?? '') !== '' ? $origin : '添付ファイル'.($idx === null ? '' : $idx + 1);
@endphp
{{-- $readonlyは' readonly'または''のどちらかなので、そのままの
     真偽値判定で読み取り専用かどうかが決まる（他のtext input等で
     {{ $readonly }}をそのまま出力しているのと同じ値）。 --}}
@if ($readonly)
    {{--
        表示専用モード。確認画面・詳細画面のどちらでも、既存ファイルが
        あれば画像またはリンクを見せるだけで、ファイルが無ければ
        「（未設定）」とだけ表示する（掲載カテゴリーなど他の項目が
        confirm/showでも常に見出しごと表示される作りと揃えている）。
    --}}
    {{-- 添付ファイルのリンクのdownload属性は、保存されるファイル名を
         元のファイル名にするため（news/show.blade.phpのコメント参照）。 --}}
    @if ($hasFile)
        @if ($isImage)
            <img src="{{ $url }}" alt="" style="max-width: 90%;" class="border rounded mb-2">
        @else
            <div class="mb-2">
                <a href="{{ $url }}" target="_blank" download="{{ $origin }}">{{ $linkText }}</a>
            </div>
        @endif
    @else
        <div class="text-muted small mb-2">（未設定）</div>
    @endif
@else
    <div class="ajax_upload_block border rounded p-2 mb-2"
         data-field="{{ $field }}"
         data-width="{{ $width }}"
         data-is-image="{{ $isImage ? '1' : '0' }}"
         data-upload-url="{{ $uploadUrl }}">

        {{-- 実際に選択されたファイルを受け取るためだけのinput。見た目上は
             ドロップエリア(.ajax_noimage_block)をクリック/ドロップした
             ときに、JavaScript側からこのinputのclick()やfilesを操作する。 --}}
        <input type="file" class="ajax_file_input d-none" accept="{{ $isImage ? 'image/*' : '' }}">

        {{-- データ更新に必要な情報を持ち回るための4つのhidden。
             意味はApp\Support\AjaxFileUploadのコメント参照。 --}}
        <input type="hidden" class="ajax_value" name="{{ $nameValue }}" value="{{ $value }}">
        <input type="hidden" class="ajax_tmp" name="{{ $nameTmp }}" value="{{ $tmp }}">
        <input type="hidden" class="ajax_origin" name="{{ $nameOrigin }}" value="{{ $origin }}">
        <input type="hidden" class="ajax_del" name="{{ $nameDel }}" value="{{ $del ? '1' : '' }}">

        {{--
            表示/非表示の切り替えは、d-noneクラスの付け外しと、インライン
            styleのdisplay指定の両方を、あえて併用する。

            .ajax_noimage_block側はレイアウト用にd-flexクラスを常時持たせて
            いるが、Bootstrapのユーティリティクラスはすべて!important付きで
            定義されているため、「d-flexクラス」と「インラインstyleの
            display:none」だけで済ませると、後から評価されるクラス側
            （d-flex）が必ず勝ってしまい、style="display:none"を書いても
            隠れない、という事故が起きる。d-noneもBootstrap側で!important
            付きの、かつd-flexより後ろに定義されているクラスなので、
            d-flexと同時に付ければd-noneが正しく勝つ
            （Bootstrapの表示ユーティリティ同士の競合はこの「後で定義されて
            いる方が勝つ」というCSSの仕様で解決されている）。

            ただしこの仕組みはBootstrapのCSSが読み込まれている前提であり、
            このトレイト・部分ビューを将来Bootstrap以外のプロジェクトへ
            流用した場合、d-noneクラスに何のスタイルも定義されず、
            トグルが効かなくなる。そのため、Bootstrapが無い環境でも
            最低限隠れるよう、インラインのdisplay指定も同時に行っている。
            Bootstrap環境ではd-noneが（!important+定義順で）確実に勝つので
            実害はなく、Bootstrapが無い環境ではd-flexによる妨害が
            そもそも存在しないので、インラインのdisplay指定がそのまま効く。
        --}}
        <div class="ajax_image_block {{ $hasFile ? '' : 'd-none' }}" style="{{ $hasFile ? '' : 'display:none;' }}">
            @if ($isImage)
                <img src="{{ $url }}" alt="" style="max-width: 90%; display: block;" class="mb-2 border rounded">
            @else
                <div class="mb-2">
                    <a href="{{ $url }}" target="_blank" download="{{ $origin }}" class="ajax_file_link">{{ $linkText }}</a>
                </div>
            @endif
            <a href="#" class="ajax_cancel small text-danger">ファイルを削除する</a>
        </div>

        <div class="ajax_noimage_block d-flex align-items-center justify-content-center text-center text-muted {{ $hasFile ? 'd-none' : '' }}"
             style="{{ $hasFile ? 'display:none;' : '' }} border: 2px dashed #ced4da; border-radius: 0.5rem; background-color: #f8f9fa; min-height: 4rem; cursor: pointer; padding: 0.75rem;">
            <span>
                ドロップまたはクリック
                @if ($isImage)
                    <br><span style="font-size: 0.75rem;">（横幅{{ $width }}pxに自動リサイズ）</span>
                @endif
            </span>
        </div>
    </div>
@endif
