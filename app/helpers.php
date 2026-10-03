<?php

/*
 * 画面やコントローラーから短く呼ぶためのヘルパー関数。
 * 中身の多くは、App\Support\の共通部品を呼ぶだけ。
 */

use App\Support\CodeTable;
use App\Support\HtmlSanitizer;
use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('code_table')) {
    // コード表を、値と名称の配列で返す。プルダウンの選択肢やCSVに使う。
    // 列挙型・CSV・DBのどこにあるコード表でも、同じように呼べる（App\Support\CodeTable）。
    function code_table(string $codeName): array
    {
        return CodeTable::get($codeName);
    }
}

if (! function_exists('code_keys')) {
    // コード表の値だけの配列。検証のRule::in(code_keys('prefectures'))などに使う。
    function code_keys(string $codeName): array
    {
        return array_keys(CodeTable::get($codeName));
    }
}

if (! function_exists('code_label')) {
    /**
     * コード表で、値に対応する名称を返す。画面の表示に使う。
     * 値が空か、コード表に無い値なら$defaultを返す。列挙型の値をそのまま渡してもよい。
     *
     *   code_label('prefectures', $member->prefecture, '（未設定）')
     */
    function code_label(string $codeName, mixed $value, string $default = ''): string
    {
        // 列挙型なら、その値で探す
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $table = CodeTable::get($codeName);

        // 空の値や、コード表に無い値なら$default
        if ((! is_int($value) && ! is_string($value)) || ! array_key_exists($value, $table)) {
            return $default;
        }

        return $table[$value];
    }
}

if (! function_exists('safe_html')) {
    /**
     * エディターで入力されたHTMLを、許可したタグと属性だけに絞って返す。
     * エスケープせずに{!! !!}で出すHTMLは、必ずこれを通す。nullならnullを返す。
     * 許可しているタグは、App\Support\HtmlSanitizerを参照。
     *
     *   {!! safe_html($news->body) !!}
     */
    function safe_html(?string $html): ?string
    {
        return HtmlSanitizer::clean($html);
    }
}

if (! function_exists('upload_input_value')) {
    /**
     * アップロード欄のhiddenの値を、$inputから1つ取り出す。見つからないか、形が合わなければnull。
     * $idxがnullなら単数のフィールド、整数なら複数のフィールドの$idx番目の行。
     *
     * 複数のフィールドの1行目は添え字が0なので、$idxは=== nullで判定する。
     * また、hiddenが書き換えられて配列のはずの値が文字列で届いても、1文字目を取り出して
     * しまわないよう、配列かどうかを必ず確かめる。この2つを呼ぶ側に書かせないための関数。
     */
    function upload_input_value(array $input, string $key, ?int $idx = null): ?string
    {
        $value = $input[$key] ?? null;

        // 複数のフィールドなら、その行の値（配列でなければnull）
        if ($idx !== null) {
            $value = is_array($value) ? ($value[$idx] ?? null) : null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}

if (! function_exists('upload_preview_url')) {
    /**
     * アップロード欄に出すプレビューのURLを、$inputの値から求める。$idxの意味は
     * upload_input_value()と同じ。$modelはファイルを持つレコードで、新規登録ではnull。
     * 複数のフィールドでも、$modelには親のレコードを渡す。
     *
     *   upload_preview_url($model, $input, 'list_image')
     *   upload_preview_url($model, $input, 'attach', $i)
     *
     * 入力値から求まる表示用の値なので、$inputには入れず、画面からこれを呼ぶ。
     * $inputには送信する項目だけを入れる決まりのため。
     */
    function upload_preview_url(?Model $model, array $input, string $field, ?int $idx = null): ?string
    {
        return UploadFilePath::previewUrl(
            $model,
            $field,
            upload_input_value($input, $field, $idx),
            upload_input_value($input, "{$field}_tmp", $idx),
            upload_input_value($input, "{$field}_del", $idx) === '1',
        );
    }
}

if (! function_exists('required_mark')) {
    /**
     * 項目名の横に付ける必須マークのHTML。マークは、管理画面用と訪問者向けの画面用を
     * config/form.phpで別々に決めている。どちらを使うかは、今の画面のルート名が
     * admin.で始まるかで決め、違う方を使いたいときだけ$areaで指定する。
     *
     * ふつうはrules()からrequired_fields()で組み立てる。画面に直接書くのは、
     * rules()と連動させない画面（比較用の/contact2など）だけ。
     */
    function required_mark(?string $area = null): string
    {
        // 指定が無ければ、管理画面か訪問者向けかを、今のルート名で決める
        $area ??= request()->routeIs('admin.*') ? 'admin' : 'public';

        return config("form.required_mark.{$area}", '');
    }
}

if (! function_exists('required_fields')) {
    /**
     * 検証ルールから、画面に渡す必須マークの配列を作る。ルールに'required'がある項目は
     * 必須マーク、無い項目は空文字。画面では{!! $required['項目名'] ?? '' !!}で出す。
     *
     * $alsoRequiredには、'required'は無いが必須にしたい項目を渡す。password_confirmationや、
     * acceptedルールの同意のチェックなど。
     * ルールは配列で書く前提で、'required'という文字列だけを見る。required_ifのような
     * 条件付きの必須は拾わないので、必要なら$alsoRequiredで足す。
     */
    function required_fields(array $rules, array $alsoRequired = []): array
    {
        $mark = required_mark();

        // ルールに'required'がある項目に、必須マークを付ける
        $required = [];
        foreach ($rules as $field => $fieldRules) {
            $required[$field] = in_array('required', (array) $fieldRules, true) ? $mark : '';
        }

        // ルールには無いが必須にしたい項目にも付ける
        foreach ($alsoRequired as $field) {
            $required[$field] = $mark;
        }

        return $required;
    }
}
