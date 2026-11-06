<?php

/**
 * サイトの既定の値。アプリのconfig/neobit.phpのsiteの節に書いたキーが、これに優先する。
 */
return [

    // 画面やメールに出す、サイトの名前。.envのSITE_NAMEに書く。日本語で書いてよい。書いていなければ空で、名前は出ない。
    // APP_NAMEは代わりに使わない。APP_NAMEは処理の中で使うサイトの識別名で、人に見せる名前ではないため。
    // APP_NAMEと分けているのは、LaravelがAPP_NAMEから、セッションのCookieやキャッシュの名前を
    // 英数字だけを残して作るため。APP_NAMEを日本語にすると、名前が「-session」のように空になり、
    // 同じドメインやDBに置いたほかのサイトとぶつかる。APP_NAMEは半角の英数字で書く
    'name' => (string) env('SITE_NAME', ''),

];
