<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    // 画面やメールに出す、サイトの名前。日本語で書いてよい。書いていなければ空で、名前は出ない。
    // APP_NAMEは代わりに使わない。APP_NAMEは処理の中で使うサイトの識別名で、人に見せる名前ではないため。
    // APP_NAMEと分けているのは、LaravelがAPP_NAMEから、セッションのCookieやキャッシュの名前を
    // 英数字だけを残して作るため。APP_NAMEを日本語にすると、名前が「-session」のように空になり、
    // 同じドメインやDBに置いたほかのサイトとぶつかる。APP_NAMEは半角の英数字で書く
    'site_name' => (string) env('SITE_NAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'Asia/Tokyo',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'ja'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'ja_JP'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    // 信頼するプロキシのIPアドレス。サーバーの前にロードバランサーやCDNを置いたときに、
    // そのIPアドレスをカンマ区切りで書く（192.168.0.0/24のような範囲も書ける）。
    // ここに書いたプロキシから届いたX-Forwarded-Forを、訪問者のIPアドレスとして使う
    // （App\Providers\AppServiceProvider）。ここに書いたIPアドレスから接続した人は、
    // IPアドレスを偽れるようになるので、正確に書く。空なら、どのプロキシも信頼しない。
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))
    )),

    // サイト（訪問者画面）に制限を掛けているときも開けるIPアドレス。カンマ区切りで書く（192.168.0.0/24のような範囲も書ける）。
    // 制限を掛けるかと、ほかのIPアドレスは、管理画面の「アクセス制限」で決める（Neobit\Core\Security\AccessRestriction）。
    // ここに書いたものは、画面の一覧に足され、画面からは外せない。ここに書いただけでは、制限は掛からない
    'site_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SITE_ALLOWED_IPS', '')))
    )),

    // 管理画面に制限を掛けているときも開けるIPアドレス。書き方はsite_allowed_ipsと同じ。管理画面の「アクセス制限」の
    // 一覧に足され、画面からは外せない。ここに書いただけでは、制限は掛からない。サイトの制限には影響しない
    'admin_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', '')))
    )),

];
