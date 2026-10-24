{{--
    子テーブル（Neobit\Core\Crud\ChildTables）の一式。枠の外側（行の入れ物・行を足すボタン・
    空の行のひな型・行の×）を受け持ち、行の中の項目の並べ方は、コーナーの部分ビューに任せる。
    $readonlyの値によって、次の2通りに切り替わる（考え方は_ajax_upload_blockと同じ）。

    - $readonlyが空（create/edit）：行を並べ、「行を足す」で行を増やせるようにする。
      行の数の上限は、今の画面のコントローラーのCHILD_TABLESの'max'から読む。
      行が1つも無いときも、最初から空の行を1つ出す。定義に'initial'があれば、最初からその数の枠を並べる。
      定義に'fixed'があれば、常にその数の枠を並べ（行がそれより多ければ全部）、「行を足す」と行の×は出さない
    - $readonlyが空でない（confirm/show）：行を、コーナーの部分ビューの読み取り専用で並べる。
      行が1つも無ければ「（なし）」とだけ出す

    呼び出し側が用意する変数:
    - $group     組の名前（CHILD_TABLESのキー。例: "photos"）
    - $row       行の中身を書いた、コーナーの部分ビューの名前（例: "admin.news._photo_row"）。
                 $model・$input・$idx（何行目か）・$readonly・$uploadUrlを受け取る。ひな型では
                 $inputが空、$idxが0になる。name属性は「組の名前_項目[]」にする
    - $model     親のレコード（ニュースなら$news）。新規登録の画面ではnull
    - $input     画面の入力値の配列。組の列ごとの配列（photos_id・photos_captionなど）が入っている
    - $readonly  ' readonly'または''
    - $uploadUrl (行にファイルの項目があるとき)Ajaxアップロード先のURL
    - $addLabel  行を足すボタンの文言（例: "写真を追加"）
    - $inline    (省略可)trueなら、行を横に並べる。並べ方と行の幅はglobal.cssの.repeatable_inlineにある

    行のidと、組が送られたことを示す印は、ここでhiddenに出す。コーナーの部分ビューには書かない。
    行が1つも無いときも印が届くので、保存のときに「組を空にする」と分かる（ChildTablesのコメント参照）。
    確認画面では、$inputにある同じ値を_confirm_hiddenが出す。
--}}
@php
    $uploadUrl ??= null;
    $inline ??= false;

    $definition = \Neobit\Core\Crud\ChildTableDefinitions::find(request()->route()?->getControllerClass() ?? '', $group)
        ?? throw new \LogicException("子テーブル「{$group}」が、今の画面のコントローラーのCHILD_TABLESにありません。");
    $ids = is_array($input["{$group}_id"] ?? null) ? $input["{$group}_id"] : [];
    $count = count($ids);
    $markerKey = \Neobit\Core\Crud\ChildTableDefinitions::markerKey($group);
    // 入力の画面に並べる枠の数。最初に並べる数（'initial'。固定の枠ならその数）に足りない分を、空の枠で埋める
    $fixed = $definition['fixed'];
    $slots = max($count, $definition['initial']);
@endphp
@if ($readonly)
    @if ($count === 0)
        <div class="text-muted small mb-2">（なし）</div>
    @else
        <div class="repeatable_rows {{ $inline ? 'repeatable_inline' : '' }}">
            @for ($i = 0; $i < $count; $i++)
                <div class="repeatable_row rounded p-2 mb-2">
                    @include($row, ['model' => $model, 'input' => $input, 'idx' => $i, 'readonly' => $readonly, 'uploadUrl' => $uploadUrl])
                </div>
            @endfor
        </div>
    @endif
@else
    {{-- data-files-onlyは、ファイルの項目だけの組の目印。ajax_upload.jsが、「ファイルを削除」で行ごと消すのに使う。
         data-fixedは、固定の枠の目印。repeatable_group.jsとajax_upload.jsが、行を足さず消さないのに使う --}}
    <div class="repeatable_group" data-group="{{ $group }}" data-max="{{ $definition['max'] }}"@if ($definition['texts'] === []) data-files-only="1"@endif{!! $fixed !== null ? ' data-fixed="1"' : '' !!}>
        <input type="hidden" name="{{ $markerKey }}" value="1">

        {{-- 行の入れ物。repeatable_group.jsは、足した行をこの末尾に入れる --}}
        <div class="repeatable_rows {{ $inline ? 'repeatable_inline' : '' }}">
            @for ($i = 0; $i < $slots; $i++)
                {{-- 行の無い分は空の枠になる --}}
                @include('neobit::_repeatable_group_row', [
                    'row' => $row,
                    'group' => $group,
                    'model' => $model,
                    'input' => $input,
                    'idx' => $i,
                    'uploadUrl' => $uploadUrl,
                    'removable' => $fixed === null,
                ])
            @endfor
        </div>

        @if ($fixed === null)
            <button type="button" class="btn btn-sm btn-outline-secondary repeatable_add mt-1" @disabled($count >= $definition['max'])>{{ $addLabel }}</button>
        @endif

        {{-- 「行を足す」で複製する、空の行のひな型。<template>の中身はブラウザに描画されず、
             フォームの送信にも入らない。$idxの0は「行の番号がある」ことを示すためのもので、何行目かという意味は持たない --}}
        <template class="repeatable_template">
            @include('neobit::_repeatable_group_row', [
                'row' => $row,
                'group' => $group,
                'model' => null,
                'input' => [],
                'idx' => 0,
                'uploadUrl' => $uploadUrl,
                'removable' => $fixed === null,
            ])
        </template>
    </div>
@endif
