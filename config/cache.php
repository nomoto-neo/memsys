<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | 既定のキャッシュの置き場所
    |--------------------------------------------------------------------------
    |
    | キャッシュを使うときに、置き場所を指定しなければここを使う。
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | キャッシュの置き場所
    |--------------------------------------------------------------------------
    |
    | キャッシュの置き場所と、その仕組み（driver）。同じ仕組みで、
    | 置き場所を複数作ってもよい。
    |
    | 使える仕組み："array"・"database"・"file"・"memcached"・
    |             "redis"・"dynamodb"・"storage"・"octane"・
    |             "session"・"failover"・"null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | キャッシュのキーの頭
    |--------------------------------------------------------------------------
    |
    | APC・DB・Memcached・Redis・DynamoDBのキャッシュは、ほかの
    | アプリケーションと同じ置き場所を使うことがある。キーがぶつからない
    | よう、すべてのキーの頭にこの文字を付ける。
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | キャッシュから戻せるクラス
    |--------------------------------------------------------------------------
    |
    | キャッシュから戻せる（unserializeできる）クラス。既定ではどのクラスも
    | 戻さない。APP_KEYが漏れたときに、ガジェットチェーンの攻撃を
    | 受けないようにするため。
    |
    */

    'serializable_classes' => false,

];
