{{--
    繰り返しの組の入力の1行（_repeatable_groupから使う）。行のidのhiddenと、行を消す×を置き、
    行の中身はコーナーの部分ビュー（$row）に任せる。最後の1行の×は、行を消さずに空にする（repeatable_group.js）。
    Bootstrapが無い画面でも見えるよう、×は文字とインラインのstyleで描く（_ajax_upload_blockの×と同じ）。
--}}
<div class="repeatable_row border rounded p-2 mb-2" style="position: relative;">
    <input type="hidden" name="{{ $group }}_id[]" value="{{ $input["{$group}_id"][$idx] ?? '' }}">

    <button type="button" class="repeatable_remove" aria-label="この行を消す"
            style="position: absolute; top: 0.125rem; right: 0.375rem; z-index: 1; border: 0; background: none; padding: 0 0.25rem; font-size: 1.25rem; line-height: 1; color: #6c757d; cursor: pointer;">&times;</button>

    @include($row, ['model' => $model, 'input' => $input, 'idx' => $idx, 'readonly' => '', 'uploadUrl' => $uploadUrl])
</div>
