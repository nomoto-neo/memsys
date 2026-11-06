<?php

return [

    /*
    |--------------------------------------------------------------------
    | 会員の設定
    |--------------------------------------------------------------------
    |
    | ログインする人の種類ごとに、サイトで変わるものを書く。
    | キーは、種類の名前（モデルの短い名前。Neobit\Core\Auth\IsLoginAccount）。member・company_userなど。
    | ガード・ルート・メールのテンプレートの名前は、種類の名前から決まりで作るので、
    | ここには書かない。設計は docs/member-types-spec.md。
    | 古い方式のパスワードの照合は、フレームワークの設定（config/neobit.phpのauthの節）に書く。
    |
    | registration_staff_email
    |   企業会員だけで使う。企業の登録の申請があったことを知らせるメールを送る、
    |   スタッフ側の受信アドレス。.envのCOMPANY_REGISTRATION_STAFF_EMAILで指定する。
    |   カンマ区切りで複数指定できる。
    |
    | identity_check_column
    |   既存のシステムから企業を移したサイトだけで使う。移した企業の最初の担当者が、初回の
    |   ログインで登録するときに、入力させて照合する企業（t_companies）の列
    |   （App\Http\Controllers\Company\FirstLoginSetupController）。列を変えたら、
    |   登録の画面（company/auth/login-setup.blade.php）の入力欄の見出しも合わせて直す。
    |
    */

    // 企業の担当者
    'company_user' => [
        'registration_staff_email' => env('COMPANY_REGISTRATION_STAFF_EMAIL', 'staff@example.com'),
        'identity_check_column' => 'tel',
    ],

];
