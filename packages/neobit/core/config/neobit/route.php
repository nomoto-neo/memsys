<?php

/**
 * URLの既定の値。アプリのconfig/neobit.phpのrouteの節に書いたキーが、これに優先する。
 */
return [

    // 管理画面の頭のURL（先頭と末尾の/は付けない）。/adminはURLを順に叩く攻撃で真っ先に狙われるので、
    // 既定もapp/adminにしている。アプリのbootstrap/app.phpが管理画面のグループのprefix()に使い、
    // サイトのアクセス制限（RestrictSiteAccess）は、ここの下を制限の外にする。
    // 変えたら、ベーシック認証のVirtualHostの決まり（利用ガイドの vendor/neobit/core/docs/guide/basic-auth.md）も合わせて直す。
    // robots.txtには書かない。誰でも読めるので、隠したURLを教えることになるため
    'admin_path' => 'app/admin',

];
