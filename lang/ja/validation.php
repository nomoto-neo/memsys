<?php

// Laravelのバリデーションが標準で使うメッセージの日本語版。
// 英語版の原本は resources/lang ではなく lang/en/validation.php
// （`php artisan lang:publish` で作られる）にあり、この ja版は
// それと同じキー構成で、値だけ日本語にしたもの。
//
// :attribute というプレースホルダーは、下の 'attributes' 配列で
// 定義したフィールド名（例: email→メールアドレス）に自動的に置き換わる。
// 未対応（この配列に無い）フィールド名は、'phone' のように
// 英語のフィールド名そのままがここに埋め込まれる。

return [

    // 個別ルールに対応するメッセージが無い場合のフォールバック。
    'default' => ':attributeの値が不正です。',

    // ここから下は、ルール名 → メッセージ のペア。
    // "The email has already been taken." に対応するのは 'unique'。
    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLではありません。',
    'after' => ':attributeには:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeには:date以降の日付を指定してください。',
    'alpha' => ':attributeは英字のみ使用できます。',
    'alpha_dash' => ':attributeは英数字とダッシュ(-)および下線(_)のみ使用できます。',
    'alpha_num' => ':attributeは英数字のみ使用できます。',
    'array' => ':attributeは配列でなければなりません。',
    'before' => ':attributeには:dateより前の日付を指定してください。',
    'before_or_equal' => ':attributeには:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個までの間で指定してください。',
        'file' => ':attributeは:min KBから:max KBの間のファイルを指定してください。',
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
        'string' => ':attributeは:min文字から:max文字の間で指定してください。',
    ],
    'boolean' => ':attributeはtrueかfalseにしてください。',
    'confirmed' => ':attributeと確認用の値が一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeには有効な日付を指定してください。',
    'date_equals' => ':attributeには:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で指定してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁までの間で指定してください。',
    'dimensions' => ':attributeの画像サイズが不正です。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_end_with' => ':attributeは、次のいずれかで終わってはいけません: :values',
    'doesnt_start_with' => ':attributeは、次のいずれかで始まってはいけません: :values',
    'email' => ':attributeには正しい形式のメールアドレスを指定してください。',
    'ends_with' => ':attributeは、次のいずれかで終わる必要があります: :values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'extensions' => ':attributeは次の拡張子のファイルを指定してください: :values',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeに値を指定してください。',
    'gt' => [
        'array' => ':attributeは:value個より多くなければなりません。',
        'file' => ':attributeは:value KBより大きくなければなりません。',
        'numeric' => ':attributeは:valueより大きい値にしてください。',
        'string' => ':attributeは:value文字より長くしてください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上でなければなりません。',
        'file' => ':attributeは:value KB以上でなければなりません。',
        'numeric' => ':attributeは:value以上の値にしてください。',
        'string' => ':attributeは:value文字以上にしてください。',
    ],
    'image' => ':attributeには画像ファイルを指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeは:otherに存在しません。',
    'integer' => ':attributeは整数で指定してください。',
    'ip' => ':attributeには、有効なIPアドレスを指定してください。',
    'ipv4' => ':attributeには、有効なIPv4アドレスを指定してください。',
    'ipv6' => ':attributeには、有効なIPv6アドレスを指定してください。',
    'json' => ':attributeには、有効なJSON文字列を指定してください。',
    'lowercase' => ':attributeは小文字にしてください。',
    'lt' => [
        'array' => ':attributeは:value個より少なくなければなりません。',
        'file' => ':attributeは:value KBより小さくなければなりません。',
        'numeric' => ':attributeは:valueより小さい値にしてください。',
        'string' => ':attributeは:value文字より短くしてください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下でなければなりません。',
        'file' => ':attributeは:value KB以下でなければなりません。',
        'numeric' => ':attributeは:value以下の値にしてください。',
        'string' => ':attributeは:value文字以下にしてください。',
    ],
    'mac_address' => ':attributeには、有効なMACアドレスを指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下指定してください。',
        'file' => ':attributeには:max KB以下のファイルを指定してください。',
        'numeric' => ':attributeには:max以下の数字を指定してください。',
        'string' => ':attributeは:max文字以下で指定してください。',
    ],
    'max_digits' => ':attributeは:max桁以下にしてください。',
    'mimes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'mimetypes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'min' => [
        'array' => ':attributeは:min個以上指定してください。',
        'file' => ':attributeには:min KB以上のファイルを指定してください。',
        'numeric' => ':attributeには:min以上の数字を指定してください。',
        'string' => ':attributeは:min文字以上で指定してください。',
    ],
    'min_digits' => ':attributeは:min桁以上にしてください。',
    'missing' => ':attributeを指定しないでください。',
    'missing_if' => ':otherが:valueの場合、:attributeを指定しないでください。',
    'missing_unless' => ':otherが:valueでない限り、:attributeを指定しないでください。',
    'missing_with' => ':valuesを指定する場合、:attributeを指定しないでください。',
    'missing_with_all' => ':valuesを指定する場合、:attributeを指定しないでください。',
    'multiple_of' => ':attributeは:valueの倍数でなければなりません。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => 'この項目には数字を指定してください。',
    'password' => [
        'letters' => ':attributeは1文字以上の英字を含めてください。',
        'mixed' => ':attributeは1文字以上の大文字と小文字を含めてください。',
        'numbers' => ':attributeは1文字以上の数字を含めてください。',
        'symbols' => ':attributeは1文字以上の記号を含めてください。',
        'uncompromised' => '指定された:attributeは漏えいしています。別の:attributeを選択してください。',
    ],
    'present' => ':attributeが存在していません。',
    'present_if' => ':otherが:valueの場合、:attributeが存在している必要があります。',
    'present_unless' => ':otherが:valueでない限り、:attributeが存在している必要があります。',
    'present_with' => ':valuesが存在する場合、:attributeも存在している必要があります。',
    'present_with_all' => ':valuesが存在する場合、:attributeも存在している必要があります。',
    'prohibited' => ':attributeは許可されていません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは許可されていません。',
    'prohibited_unless' => ':otherが:valuesに含まれない限り、:attributeは許可されていません。',
    'prohibits' => ':attributeは:otherの入力を許可しません。',
    'regex' => 'この項目の形式が正しくありません。',
    'required' => 'この項目は必須です。',
    'required_array_keys' => ':attributeには:valuesを含めてください。',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'required_if_accepted' => ':otherを承認する場合、:attributeは必須です。',
    'required_unless' => ':otherが:valuesでない場合、:attributeは必須です。',
    'required_with' => ':valuesを指定する場合、:attributeも指定してください。',
    'required_with_all' => ':valuesを指定する場合、:attributeも指定してください。',
    'required_without' => ':valuesを指定しない場合、:attributeを指定してください。',
    'required_without_all' => ':valuesのいずれも指定しない場合、:attributeを指定してください。',
    'same' => ':attributeと:otherには同じ値を指定してください。',
    'size' => [
        'array' => ':attributeは:size個指定してください。',
        'file' => ':attributeのサイズには:size KBを指定してください。',
        'numeric' => ':attributeには:sizeを指定してください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeは、次のいずれかで始まる必要があります: :values',
    'string' => ':attributeは文字列を指定してください。',
    'timezone' => ':attributeには、有効なタイムゾーンを指定してください。',
    'unique' => ':attributeは既に使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字にしてください。',
    'url' => ':attributeには、有効なURLを指定してください。',
    'ulid' => ':attributeには、有効なULIDを指定してください。',
    'uuid' => ':attributeには、有効なUUIDを指定してください。',

    // ルール名だけでなく「フィールド名.ルール名」で個別に上書きしたいときは
    // ここに追加する。例えば email の unique だけ文言を変えたい場合など。
    // 'custom' => [
    //     'email' => [
    //         'unique' => 'このメールアドレスは既に登録されています。',
    //     ],
    // ],
    'custom' => [
        //
    ],

    // :attributeプレースホルダーが実際にどの日本語に置き換わるかの対応表。
    // ここに無いフィールド名は、英語のキー名（例: phone）がそのまま出る。
    'attributes' => [
        'name' => '氏名',
        'kana' => 'フリガナ',
        'email' => 'メールアドレス',
        'phone' => '電話番号',
        'birthdate' => '生年月日',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'body' => 'お問い合わせ内容',
    ],

];
