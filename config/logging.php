<?php

use App\Support\ErrorNotifyHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "monthly", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    // 操作ログ（t_operation_logs。App\Support\OperationRecorder）を残す日数。
    // これを過ぎた行は、App\Support\TemporaryDataCleanerが消す
    'operation_log_days' => (int) env('OPERATION_LOG_DAYS', 365),

    // 操作ログの報告のメール（App\Support\OperationLogReport）の宛先。カンマ区切りで複数書ける。
    // 空なら送らない。エラーの通知（ERROR_NOTIFY_TO）は開発者、こちらは管理者と、読む人が違うので分けている
    'operation_report_to' => (string) env('OPERATION_REPORT_TO', ''),

    'channels' => [

        // ERROR_NOTIFY_LEVELがあれば、ファイルに書くのと一緒にerror_notifyでメールでも知らせる
        'stack' => [
            'driver' => 'stack',
            'channels' => array_merge(
                explode(',', (string) env('LOG_STACK', 'single')),
                env('ERROR_NOTIFY_LEVEL') ? ['error_notify'] : [],
            ),
            'ignore_exceptions' => false,
        ],

        // エラーをメールで知らせる。App\Support\ErrorNotifyHandler。
        // levelは知らせる最低のレベル、toはカンマ区切りの宛先、intervalMinutesは同じ内容を送る間隔の分数
        'error_notify' => [
            'driver' => 'monolog',
            'handler' => ErrorNotifyHandler::class,
            'level' => env('ERROR_NOTIFY_LEVEL') ?: 'critical',
            'handler_with' => [
                'to' => (string) env('ERROR_NOTIFY_TO', ''),
                'intervalMinutes' => (int) env('ERROR_NOTIFY_INTERVAL', 10),
            ],
            'replace_placeholders' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // 会員の登録・退会の記録（App\Support\MemberActivityLog）。
        // storage/logs/member-日付.log に1日1ファイルで書き、LOG_MEMBER_DAYS日分
        // （既定は365日）を残す。氏名・メールアドレスなどの個人情報は書かない。
        'member' => [
            'driver' => 'daily',
            'path' => storage_path('logs/member.log'),
            'level' => 'info',
            'max_files' => (int) env('LOG_MEMBER_DAYS', 365),
            'replace_placeholders' => true,
        ],

        'monthly' => [
            'driver' => 'monthly',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
