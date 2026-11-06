<?php

/**
 * ログインの既定の値。アプリのconfig/neobit.phpのauthの節に書いたキーが、これに優先する。
 */
return [

    // 古い方式のパスワードを計算するクラスを、ログインする人の種類（モデルの短い名前。member・company_userなど）ごとに、試す順に書く
    // （Neobit\Core\Auth\LegacyPasswordUserProvider）。既存のシステムから会員を移したサイトだけで使う。
    // ソルトの無いMD5・SHA-1・SHA-256は、Neobit\Core\Auth\Legacyに用意してある。そのサイトだけの方式は、
    // Neobit\Core\Auth\LegacyPasswordを実装したクラスを書いて足す。例：'member' => [Neobit\Core\Auth\Legacy\Md5Password::class]
    // 空なら、古い方式の照合をしない
    'legacy_passwords' => [],

];
