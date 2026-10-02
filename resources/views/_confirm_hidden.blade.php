{{--
    確認画面の「戻る」「登録する（更新する）」フォームに埋め込むhidden一式を、
    $input（送信される項目だけを集めた配列）から機械的に組み立てる共通パーツ。

    管理画面・訪問者側のどちらからも使うので、admin/配下ではなく
    resources/views直下に置いている（@include('_confirm_hidden', ...)）。

    ■ このパーツが前提にしている規約

    コントローラーが画面へ渡す$inputには、実際にフォームから送信される
    項目だけを入れる。表示専用の値（プレビューURL、表示用に整形した
    行データ等）は1つも混ぜない。

    表示にしか使わない値が必要なときは、$inputに足すのではなく、ビューの
    中でヘルパーを呼んでその場で求める。たとえば都道府県の名称は
    code_table()、アップロードファイルのプレビューURLは
    upload_preview_url()で、どちらも$inputの値を引数にして求める。
    こうしておけば$inputの中身は常に「送信される項目」のままなので、
    このパーツで$inputを丸ごとhidden展開しても、フォーム項目ではない
    値が紛れ込むことは無い。

    ■ 呼び出し側が用意する変数

    - $input            hidden展開したい値の配列。スカラー値・配列値
                        （例: category_ids、複数展開アップロードの
                        attach・attach_tmp等）のどちらが混ざっていても
                        よい。$request->validate()の戻り値や
                        $request->except([...制御用フィールド])を想定して
                        いる（$request->validate()の戻り値は「rules()に
                        ルールがあり、かつ実際にリクエストに含まれていた
                        キー」だけを返す点に注意）。
    - $exclude          (省略可) hidden展開そのものから除外したいキー名の
                        配列。★重要★: パスワードのような「確認画面を
                        経由しても平文のままページソースに残すべきでは
                        ない値」がある画面では、必ずこれで除外すること
                        （$inputに含まれる値は無条件にそのままhidden化
                        されるため、指定し忘れると機密値がHTMLソースに
                        そのまま出力される）。実際にadmin/staff・
                        admin/members・auth/regist-confirmの「戻る」側は、
                        これでpassword・password_confirmationを除外して
                        いる。

    配列値の展開は、name="{{ $key }}[]"の形（PHP側で
    $request->input($key)が配列として届く）にしている。連想配列
    (キー付き配列)を渡した場合はキーが失われる点に注意（このプロジェクト
    ではcategory_ids・attach系はいずれも0始まりの連番配列なので問題ない）。

    本文(body)のように改行やHTMLを含む値も、そのまま<input type="hidden">で
    出している。hiddenのvalue属性は改行を含めて値を変えずに送信され、
    <や"などは{{ }}のエスケープで崩れない。<textarea>で持ち回すと、開始タグの
    直後の改行1つがHTMLの仕様で捨てられるので、値が改行で始まる場合に
    1つ消えてしまう。
--}}
@php
    $exclude ??= [];
@endphp
@foreach ($input as $key => $value)
    @continue(in_array($key, $exclude, true))
    @if (is_array($value))
        @foreach ($value as $item)
            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
@endforeach
