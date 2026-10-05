<?php

return [

    /*
    |--------------------------------------------------------------------
    | 会員の設定
    |--------------------------------------------------------------------
    |
    | 訪問者側でログインする会員について、サイトごとに変わるものを書く。
    | キーは、会員の種類の名前（モデルのMEMBER_TYPE。App\Support\MemberAccount）。
    | ガード・ルート・メールのテンプレートの名前は、種類の名前から決まりで作るので、
    | ここには書かない。設計は docs/member-types-spec.md。
    |
    | legacy_passwords
    |   既存のシステムから会員を移したサイトだけで使う。古い方式のパスワードを計算する
    |   クラスを、試す順に書く（App\Support\LegacyPasswordUserProvider）。
    |   ソルトの無いMD5・SHA-1・SHA-256は、App\Support\Legacyに用意してある。
    |   そのサイトだけの方式は、App\Support\LegacyPasswordを実装したクラスを書いて足す。
    |   新しく始めるサイトでは、空のままにする。
    |
    | registration_staff_email
    |   企業会員だけで使う。企業の登録の申請があったことを知らせるメールを送る、
    |   スタッフ側の受信アドレス。.envのCOMPANY_REGISTRATION_STAFF_EMAILで指定する。
    |   カンマ区切りで複数指定できる。
    |
    */

    // 個人会員
    'member' => [
        'legacy_passwords' => [
            // App\Support\Legacy\Md5Password::class,
            // App\Support\Legacy\Sha1Password::class,
            // App\Support\Legacy\Sha256Password::class,
        ],
    ],

    // 企業の担当者
    'company' => [
        'legacy_passwords' => [
            //
        ],
        'registration_staff_email' => env('COMPANY_REGISTRATION_STAFF_EMAIL', 'staff@example.com'),
    ],

];
