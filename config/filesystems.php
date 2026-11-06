<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 既定のディスク
    |--------------------------------------------------------------------------
    |
    | ファイルを置くときに、ディスクを指定しなければここを使う。"local"の
    | ほか、クラウドのディスクも使える。
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | ディスク
    |--------------------------------------------------------------------------
    |
    | ファイルを置くディスク。いくつ書いてもよく、同じ仕組み（driver）で
    | 複数作ってもよい。
    |
    | 使える仕組み："local"・"ftp"・"sftp"・"s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            //'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | シンボリックリンク
    |--------------------------------------------------------------------------
    |
    | php artisan storage:linkで作るシンボリックリンク。キーがリンクの場所、
    | 値がリンクの先。
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    'permissions' => [
        'file' => ['public' => 0664, 'private' => 0660],
        'dir' => ['public' => 0775, 'private' => 0770],
    ],
];
