{{--
    写真の組の1行（neobit::_repeatable_groupから使う）。画像とキャプション。
    入力・確認・詳細の画面で同じものを使い、$readonlyで入力の欄と表示だけを切り替える。
    行のidのhiddenと、行を消す×は、組の部品が置くのでここには書かない。

    受け取る変数:
    - $model     ニュース。新規登録の画面と、行のひな型ではnull
    - $input     画面の入力値。photos_caption・photos_imageなどの、列ごとの配列が入っている
    - $idx       何行目か
    - $readonly  ' readonly'または''
    - $uploadUrl Ajaxアップロード先のURL
--}}
@include('neobit::_ajax_upload_block', [
    'model' => $model,
    'input' => $input,
    'field' => 'photos_image',
    'idx' => $idx,
    'readonly' => $readonly,
    'uploadUrl' => $uploadUrl,
])

@if ($readonly)
    <div class="small">{{ $input['photos_caption'][$idx] ?? '' }}</div>
@else
    <label class="form-label small mb-1">キャプション</label>
    <input type="text" name="photos_caption[]" class="form-control form-control-sm" maxlength="200"
           value="{{ $input['photos_caption'][$idx] ?? '' }}">
    <div class="invalid-feedback" data-item="photos_caption.{{ $idx }}">{{ $errors->first("photos_caption.{$idx}") }}</div>
@endif
