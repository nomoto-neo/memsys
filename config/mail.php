<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 既定のメールの送り方
    |--------------------------------------------------------------------------
    |
    | メールを送るときに、送り方（mailer）を指定しなければここを使う。
    | 送り方は下のmailersに書く。
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | メールの送り方
    |--------------------------------------------------------------------------
    |
    | このアプリケーションで使う送り方と、その設定。見本を足してあるので、
    | 要るものを足してよい。
    |
    | 送り方ごとに、実際に送る仕組み（transport）を選ぶ。
    |
    | 使える値："smtp"・"sendmail"・"mailgun"・"ses"・"ses-v2"・
    |          "postmark"・"resend"・"log"・"array"・
    |          "failover"・"roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 共通の差出人
    |--------------------------------------------------------------------------
    |
    | このアプリケーションが送るメールの、共通の差出人のアドレスと名前。
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'staff@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    // 届かなかったときのエラーのメール（バウンス）が戻る先。サイトが送るすべてのメールのReturn-Pathと、
    // SMTPの封筒の送信元（MAIL FROM）になる。空なら付けず、送信元のアドレスに戻る。
    // SPFの確認に通るよう、送信元と同じドメインのアドレスにする
    'return_path' => [
        'address' => env('MAIL_RETURN_PATH'),
    ],

];
