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

    // 画面やメールに出す、サイトの名前。日本語で書いてよい。書いていなければ、APP_NAMEを使う。
    // APP_NAMEと分けているのは、LaravelがAPP_NAMEから、セッションのCookieやキャッシュの名前を
    // 英数字だけを残して作るため。APP_NAMEを日本語にすると、名前が「-session」のように空になり、
    // 同じドメインやDBに置いたほかのサイトとぶつかる。APP_NAMEは半角の英数字で書く
    'site_name' => env('SITE_NAME', env('APP_NAME', 'Laravel')),

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

    // ログイン後の管理画面で、1人のスタッフが1分に送れる回数（App\Support\AdminRequestLimit）。
    // 超えた回は処理せず、操作ログとwarningのログに残す。人が普通に使って届かない数にする
    'admin_requests_per_minute' => (int) env('ADMIN_REQUESTS_PER_MINUTE', 120),

    // 信頼するプロキシのIPアドレス。サーバーの前にロードバランサーやCDNを置いたときに、
    // そのIPアドレスをカンマ区切りで書く（192.168.0.0/24のような範囲も書ける）。
    // ここに書いたプロキシから届いたX-Forwarded-Forを、訪問者のIPアドレスとして使う
    // （App\Providers\AppServiceProvider）。ここに書いたIPアドレスから接続した人は、
    // IPアドレスを偽れるようになるので、正確に書く。空なら、どのプロキシも信頼しない。
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))
    )),

    // サイト全体を開けるIPアドレス。公開前のデモの運用やメンテナンスの間に、カンマ区切りで書く
    // （192.168.0.0/24のような範囲も書ける）。ここに無いIPアドレスには、メンテナンス中の画面を出す
    // （App\Http\Middleware\RestrictSiteAccess）。空なら、制限しない。
    'site_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SITE_ALLOWED_IPS', '')))
    )),

    // 管理画面を開けるIPアドレス。書き方はsite_allowed_ipsと同じ。ここに無いIPアドレスには、
    // ログイン画面も含めて404を返す（App\Http\Middleware\RestrictAdminAccess）。空なら、制限しない。
    'admin_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', '')))
    )),

    // お問い合わせ（t_inquiries）と添付ファイルを残す日数。これを過ぎたものは、
    // App\Support\TemporaryDataCleanerが消す。空か0なら、消さずに残し続ける。
    'inquiry_keep_days' => ((int) env('INQUIRY_KEEP_DAYS', 0)) ?: null,

];
