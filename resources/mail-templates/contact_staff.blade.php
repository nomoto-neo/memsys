FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $staff_mail !!}
REPLY_TO: {!! $email !!}
SUBJECT: 【お問い合わせ】{!! $name !!} 様より

ウェブサイトのお問い合わせフォームより、下記の内容で問い合わせがありました。

■お名前
{!! $name !!}

■フリガナ
{!! $furigana !!}

■メールアドレス
{!! $email !!}

■携帯電話
{!! $phone !!}

■郵便番号
{!! $zip !!}

■住所
{!! $prefecture !!}{!! $city !!}{!! $address_other !!}

■お問い合わせ内容
{!! $body !!}

--
このメールは、お問い合わせフォーム（/contact）からの自動送信です。
返信すると、上記メールアドレス宛に送られます（REPLY_TO指定）。
