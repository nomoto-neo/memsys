<?php

use App\Support\CodeTable;

if (! function_exists('code_table')) {
    /**
     * コード表を [値 => 名称] の配列で返す（プルダウンの選択肢、CSVの一覧など）。
     * \App\Support\CodeTable::get()の短縮呼び出し用。一覧の出どころ（列挙型・
     * code/<コード名>.csv・t_codesテーブル）はCodeTableのコメント参照。
     */
    function code_table(string $codeName): array
    {
        return CodeTable::get($codeName);
    }
}


if (! function_exists('code_keys')) {
    /**
     * コード表の値だけの配列（検証のRule::in(code_keys('prefectures'))など）。
     */
    function code_keys(string $codeName): array
    {
        return array_keys(CodeTable::get($codeName));
    }
}


if (! function_exists('code_label')) {
    /**
     * コード表で、値に対応する名称を返す（一覧・詳細・確認画面の表示など）。
     * 値が空、またはコード表に無い値なら$defaultを返す。
     * 列挙型の値（例: $staff->acl）をそのまま渡してもよい。
     *
     * 例: code_label('prefectures', $member->prefecture, '（未設定）')
     */
    function code_label(string $codeName, mixed $value, string $default = ''): string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $table = CodeTable::get($codeName);

        if ((! is_int($value) && ! is_string($value)) || ! array_key_exists($value, $table)) {
            return $default;
        }

        return $table[$value];
    }
}


if (! function_exists('safe_html')) {
    /**
     * WYSIWYGエディターで入力されたHTMLを、許可したタグ・属性だけに
     * 絞って返す。\App\Support\HtmlSanitizer::clean()の短縮呼び出し用。
     *
     * エスケープせずに{!! !!}で出力するHTMLは、必ずこれを通すこと
     * （例: {!! safe_html($news->body) !!}）。理由と許可しているタグの一覧は
     * HtmlSanitizerのコメント参照。
     *
     * nullを渡すとnullをそのまま返す（{!! !!}で出力すると何も表示されない）。
     */
    function safe_html(?string $html): ?string
    {
        return \App\Support\HtmlSanitizer::clean($html);
    }
}


if (! function_exists('upload_input_value')) {
    /**
     * アップロード欄の値（hiddenで持ち回るlist_image・list_image_tmp等）を、
     * $inputから1つ取り出す。
     *
     * - $idxがnull … 単独のフィールド（例: list_image）。$input[$key]を返す。
     * - $idxが整数 … 複数展開のフィールド（例: attach）の$idx番目の行。
     *                 $input[$key][$idx]を返す。
     *
     * 見つからない場合や、形が合わない場合はnullを返す。形が合わない場合と
     * いうのは、単独のはずが配列だった、複数展開のはずが配列ではなかった、
     * などのこと。
     *
     * $idxの判定は必ず`=== null`で行っている。複数展開の1行目の添え字は0で、
     * `if ($idx)`や`$idx ?? ...`のように書くと0を「添え字なし」と取り違える。
     *
     * 配列かどうかを必ずis_array()で確かめているのは、hiddenが書き換えられて
     * attachが配列ではなく文字列で送られてきた場合に備えるため。PHPでは
     * 文字列に[0]を付けると1文字目が返ってくるので、確かめずに
     * $input['attach'][0]とすると、ファイル名の1文字目を取り出してしまう。
     *
     * この2つの判断を呼ぶ側に書かせないために、このヘルパーを用意している。
     */
    function upload_input_value(array $input, string $key, ?int $idx = null): ?string
    {
        $value = $input[$key] ?? null;

        if ($idx !== null) {
            $value = is_array($value) ? ($value[$idx] ?? null) : null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}


if (! function_exists('upload_preview_url')) {
    /**
     * アップロード欄に表示するプレビューのURLを、$inputの値から求める。
     * $idxの意味はupload_input_value()と同じ（nullなら単独のフィールド、
     * 整数なら複数展開のフィールドの$idx番目の行）。
     *
     *   upload_preview_url($model, $input, 'list_image')
     *   upload_preview_url($model, $input, 'attach', $i)
     *
     * $modelはファイルを持っているレコード（ニュースなら$news）で、
     * 新規登録の画面ではnull。複数展開の添付ファイルも保存先は親の
     * ディレクトリなので、$modelには親のレコードを渡す。
     *
     * $inputの中から{field}・{field}_tmp・{field}_delの3つを読み、
     * \App\Support\UploadFilePath::previewUrl()に渡す。キー名の付け方
     * （_tmp・_del）は、AjaxFileUploadで決めているこのプロジェクト共通の
     * 規則なので、呼ぶ側がそれを毎回書かなくて済むように、ここに
     * まとめている。
     *
     * 都道府県の名称をcode_table()で引くのと同じく、入力値から計算で
     * 求まる表示用の値なので、コントローラーで作って$inputに足すのではなく、
     * ビューからこれを呼ぶ（$inputには送信される項目だけを入れる、という
     * 規約については_confirm_hiddenのコメント参照）。
     */
    function upload_preview_url(?\Illuminate\Database\Eloquent\Model $model, array $input, string $field, ?int $idx = null): ?string
    {
        return \App\Support\UploadFilePath::previewUrl(
            $model,
            upload_input_value($input, $field, $idx),
            upload_input_value($input, "{$field}_tmp", $idx),
            upload_input_value($input, "{$field}_del", $idx) === '1',
        );
    }
}


if (! function_exists('required_mark')) {
    /**
     * 項目名の横に付ける必須マークのHTMLを返す。マークの文字列は
     * config/form.phpのrequired_markで、管理画面用（admin）と訪問者向けの
     * 画面用（public）を別々に決めている。
     *
     * どちらを使うかは、今の画面のルート名で決める（routes/web.phpで
     * 管理画面のルートはすべてadmin.で始まる名前にしているので、admin.で
     * 始まればadmin、それ以外はpublic）。画面と違う方のマークを使いたい
     * ときだけ、$areaに'admin'か'public'を渡す。
     *
     * 必須マークは、ふつうはrules()からrequired_fields()で組み立てる。
     * これをビューに直接書く（{!! required_mark() !!}）のは、rules()と
     * 連動させない画面（比較用の/contact2など）だけにする。
     */
    function required_mark(?string $area = null): string
    {
        $area ??= request()->routeIs('admin.*') ? 'admin' : 'public';

        return config("form.required_mark.{$area}", '');
    }
}


if (! function_exists('required_fields')) {
    /**
     * 検証ルールの配列から、ビューに渡す必須マークの配列（項目名 => マーク）を作る。
     * ルールに'required'がある項目は必須マーク、無い項目は空文字になる。
     * ビューでは{!! $required['項目名'] ?? '' !!}で出力する。
     *
     * $alsoRequiredには、ルールに'required'は無いが必須マークを付けたい項目を渡す。
     * - password_confirmation：confirmedルールでpassword側と照合するので、
     *   ルールには載せていない（必須にすると、passwordが未入力のときに
     *   確認用の方のエラーが先に出て紛らわしいため）
     * - acceptedルールの同意チェック：未チェックも弾くが、'required'という
     *   文字列を含まない
     *
     * ルールは配列で書く前提（'required|string'のような文字列の書き方は判定できない）。
     * 'required'という文字列だけを見ているので、required_ifなどの条件付きの
     * 必須や、Ruleオブジェクトでの必須は拾わない（必要なら$alsoRequiredで足す）。
     */
    function required_fields(array $rules, array $alsoRequired = []): array
    {
        $mark = required_mark();

        $required = [];
        foreach ($rules as $field => $fieldRules) {
            $required[$field] = in_array('required', (array) $fieldRules, true) ? $mark : '';
        }

        foreach ($alsoRequired as $field) {
            $required[$field] = $mark;
        }

        return $required;
    }
}
