{{--
    添付ファイルの組の1行（neobit::_repeatable_groupから使う）。ファイルの欄だけ。
    入力・確認・詳細の画面で同じものを使い、$readonlyで入力の欄と表示だけを切り替える。
    行のidのhiddenと、行を消す×は、組の部品が置くのでここには書かない。

    受け取る変数:
    - $model     ニュース。新規登録の画面と、行のひな型ではnull
    - $input     画面の入力値。attach_file・attach_file_originなどの、列ごとの配列が入っている
    - $idx       何行目か
    - $readonly  ' readonly'または''
    - $uploadUrl Ajaxアップロード先のURL
--}}
@include('neobit::_ajax_upload_block', [
    'model' => $model,
    'input' => $input,
    'field' => 'attach_file',
    'idx' => $idx,
    'readonly' => $readonly,
    'uploadUrl' => $uploadUrl,
])
