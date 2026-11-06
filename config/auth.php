<?php

use App\Models\CompanyUser;
use App\Models\Member;
use App\Models\Staff;

return [

    /*
    |--------------------------------------------------------------------------
    | 認証の既定
    |--------------------------------------------------------------------------
    |
    | 既定で使う認証のガードと、パスワードの再設定の仕組み（broker）。
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 認証のガード
    |--------------------------------------------------------------------------
    |
    | ログインする人の種類ごとのガード。ガードごとに、ログインの状態を
    | どこに持つか（driver）と、利用者をどこから読むか（provider）を決める。
    |
    | 使える値："session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
        // 企業会員。ログインするのは、企業に属する担当者（App\Models\CompanyUser）
        'company' => [
            'driver' => 'session',
            'provider' => 'company_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 利用者の読み込み元
    |--------------------------------------------------------------------------
    |
    | ガードが利用者を読み込む元。ふつうはEloquentのモデルから読む。
    | 利用者のテーブルやモデルが複数あるときは、それぞれに1つずつ書き、
    | ガードに割り当てる。
    |
    | 使える値："database"・"eloquent"
    |
    */

    'providers' => [
        // 会員。driverのeloquent-legacyは、既存のシステムから移した会員の、古い方式のパスワードも
        // 受け付ける照合（Neobit\Core\Auth\LegacyPasswordUserProvider）。移した会員がいなければ、
        // 標準のeloquentと同じに働く
        'users' => [
            'driver' => 'eloquent-legacy',
            'model' => env('AUTH_MODEL', Member::class),
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => Staff::class,
        ],
        // 企業の担当者。会員と同じく、古い方式のパスワードも受け付ける
        'company_users' => [
            'driver' => 'eloquent-legacy',
            'model' => CompanyUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | パスワードの再設定
    |--------------------------------------------------------------------------
    |
    | Laravelのパスワードの再設定の仕組みの設定。トークンを置くテーブルと、
    | 利用者を読む元（provider）を決める。
    |
    | expireは、再設定のトークンが使える分数。推測される時間を減らすため、
    | 短くしておく。
    |
    | throttleは、次のトークンを作れるまでに待つ秒数。短い間に大量の
    | トークンを作られないようにするため。
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | パスワードの確認の有効時間
    |--------------------------------------------------------------------------
    |
    | パスワードを確かめてから、もう一度入力を求めるまでの秒数。
    | 既定は3時間。
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
