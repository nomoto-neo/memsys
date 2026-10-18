{{--
    複数展開されるアップロードフィールド(例: attach)一式。$readonlyの値に
    よって、次の2通りに切り替わる（考え方は_ajax_upload_block.blade.php
    と同じ）。

    - $readonlyが空（create/edit）: .ajax_upload_block(_ajax_upload_block)
      を必要な数だけ並べ、「ファイルを追加」ボタンでJavaScript側から
      新しい空枠を増やせるようにしておく。増やす元になる空枠の
      マークアップは<template>の中に1つだけ用意しておき、
      resources/js/ajax_upload.jsがこれを複製する。1件も無い状態でも
      最初から1枠は表示しておく（じゃないとファイルを選ぶ手がかりが
      画面上に無くなる）。
    - $readonlyが空でない（confirm/show）: 実際にファイルが設定されている
      行だけを、_ajax_upload_blockの表示専用モードで並べる。空の予備枠や
      削除確定の枠（プレビューのURLがnullになる行）は表示しない。1件も
      無ければ「（添付ファイルはありません）」とだけ表示する。

    呼び出し側が用意する変数:
    - $model    ファイルを持っているレコード（ニュースなら$news）。
                新規登録の画面ではnull。
    - $input    画面の入力値の配列（コントローラーが組み立てた$inputそのもの）。
                その中の{field}・{field}_origin・{field}_tmp・{field}_delの
                並行配列を、行ごとに_ajax_upload_blockが読む。
    - $field    []や.*を含まない、素のフィールド名(例: "attach")
    欄の種類・縮小する横幅・枠の数の上限は受け取らず、upload_field()で今の画面のコントローラーの
    UPLOAD_FILESから読む（_ajax_upload_blockも同じ）。上限まで枠があれば「ファイルを追加」を押せなくし、
    ファイルをまとめて選んだときも、ajax_upload.jsが上限を超えて枠を増やさない。
    - $readonly ' readonly'または''（_ajax_upload_block.blade.phpと同じ
                値をそのまま渡せばよい）
    - $uploadUrl  (読み取り専用モードでは未使用)Ajaxアップロード先のURL
    - $inline   (省略可)trueなら、枠を横に並べる。省略すると縦に並べる。並べ方と枠の幅は
                global.cssの.ajax_upload_inlineにあり、枠の幅は案件のCSSで変えられる。
                読み取り専用モードでは使わない

    行数は$input[$field]（ファイル名の配列）の要素数で決める。4本の並行配列は
    AjaxFileUpload::ajaxUploadValues()が、0から始まる添え字・同じ要素数に
    揃えてあるので、0〜(要素数-1)の添え字で各行を読めばよい。
--}}
@php
    $values = $input[$field] ?? [];
    $count = is_array($values) ? count($values) : 0;
    // 枠の数の上限。今の画面のコントローラーのUPLOAD_FILESの'max'
    $max = upload_field($field, 'max');
    $inline ??= false;
@endphp
{{-- $readonlyは' readonly'または''のどちらかなので、そのままの
     真偽値判定で読み取り専用かどうかが決まる。 --}}
@if ($readonly)
    @php
        // 空の予備枠(ファイル名・tmpどちらも無い)や削除確定の枠は、
        // upload_preview_url()の結果が必ずnullになるので、URLの有無だけで
        // 一律にふるい落とせる。
        // （range(0, -1)は空配列ではなく[0, -1]を返すので、0件のときは
        // rangeを呼ばずに空配列にしている。）
        $visibleIndexes = $count === 0 ? [] : array_values(array_filter(
            range(0, $count - 1),
            fn ($i) => upload_preview_url($model, $input, $field, $i) !== null
        ));
    @endphp
    @if ($visibleIndexes === [])
        <div class="text-muted small">（添付ファイルはありません）</div>
    @else
        <ul class="ps-3 mb-0">
            @foreach ($visibleIndexes as $i)
                <li>
                    @include('neobit::_ajax_upload_block', [
                        'model' => $model,
                        'input' => $input,
                        'field' => $field,
                        'idx' => $i,
                                'readonly' => $readonly,
                    ])
                </li>
            @endforeach
        </ul>
    @endif
@else
    <div class="ajax_upload_group" data-field="{{ $field }}" data-max="{{ $max }}">
        {{-- 枠を並べる入れ物。ajax_upload.jsは、足した枠をこの末尾に入れる --}}
        <div class="ajax_upload_blocks {{ $inline ? 'ajax_upload_inline' : '' }}">
            @for ($i = 0; $i < max($count, 1); $i++)
                {{-- 1件も無いときは$i=0の1回だけ回る。$input[$field]が空なので
                     どの値もnullになり、空の枠が1つ表示される。 --}}
                @include('neobit::_ajax_upload_block', [
                    'model' => $model,
                    'input' => $input,
                    'field' => $field,
                    'idx' => $i,
                    'readonly' => $readonly,
                    'uploadUrl' => $uploadUrl,
                ])
            @endfor
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary ajax_add_block mt-1" @disabled($count >= $max)>ファイルを追加</button>

        {{-- JavaScriptが「ファイルを追加」クリック時に複製する、空枠の
             ひな型。<template>の中身はブラウザに描画されず、hidden系の
             input名がフォーム送信に紛れ込むこともない。
             空の$input（[]）を渡すので、どの値もnullの空枠になる。
             $idxの0は「複数展開フィールドの行である」（name属性に[]を付ける）
             ことを示すためのもので、何行目かという意味は持たない。 --}}
        <template class="ajax_block_template">
            @include('neobit::_ajax_upload_block', [
                'model' => null,
                'input' => [],
                'field' => $field,
                'idx' => 0,
                'readonly' => $readonly,
                'uploadUrl' => $uploadUrl,
            ])
        </template>
    </div>
@endif
