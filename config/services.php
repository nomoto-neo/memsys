<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 外部のサービス
    |--------------------------------------------------------------------------
    |
    | Resend・Postmark・AWSなど、外部のサービスの認証の情報を置く。
    | パッケージも、サービスの情報をここから読む。
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Cloudflare Turnstile（訪問者向けフォームのスパム対策。Neobit\Core\Security\SpamGuard）。
    // 鍵はCloudflareのダッシュボードでサイトのドメインごとに発行する。手元の開発では、
    // Cloudflareが公開しているテスト用の鍵を使う（SpamGuardのコメント参照）。
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
