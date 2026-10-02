<?php

return [

    /*
    |--------------------------------------------------------------------
    | 問い合わせ通知の送信先
    |--------------------------------------------------------------------
    |
    | お問い合わせフォーム（/contact）が届いたときの通知メールを送る、
    | スタッフ側の受信アドレス。開発環境・デモ環境で別のアドレスを
    | 使えるよう、.envのCONTACT_STAFF_EMAILで指定する
    | （resources/mail-templates/contact_staff.blade.phpのTO_MAIL:行は、
    | この設定値を$staff_mailという変数として受け取る）。
    |
    | カンマ区切りで複数指定できる（MailTemplateParserが
    | カンマ区切りをそのまま複数宛先として扱うため）。
    |
    */

    'staff_email' => env('CONTACT_STAFF_EMAIL', 'staff@example.com'),

];
