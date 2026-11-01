<?php

/**
 * 接続元の制限と回数の制限の既定の値。アプリのconfig/neobit.phpのsecurityの節に書いたキーが、これに優先する。
 * IPアドレスはサーバーごとに違うので、.envから読む。
 */
return [

    // ログイン後の管理画面の全体に掛ける、回数の制限（Neobit\Core\Security\AdminRequestLimit）。
    // 1人のスタッフが1分に送れる回数。超えた回は処理せず、操作ログとwarningのログに残す。
    // 人が普通に使って届かない数にする。0にすると、制限を掛けない
    'admin_requests_per_minute' => 120,

    // 上の回数を超えたときに出す、案内の文。画面と、アップロードのようなJavaScriptからの送信に
    // 返すJSONの両方に、この文をそのまま出す。タグは書けない
    'admin_requests_exceeded_message' => '操作の回数が上限を超えました。1分ほど待ってから、もう一度お試しください。',

    // 不正なURLを続けて叩くBOTを、IPアドレスで止める（Neobit\Core\Security\IpBlocker）。止めたIPアドレスからのアクセスには、どのページも404を返す。
    // 存在しないURLへのアクセスが多いIPアドレスを、自動で止めるか。falseなら、管理画面からの登録だけ
    'ip_block_auto' => true,

    // 数える時間（分）
    'ip_block_watch_minutes' => 10,

    // 上の時間に、存在しないURLへのアクセス（応答が404・405）が何回あったら止めるか。
    // ふつうの人が10分に30回も存在しないページを開くことは無く、BOTは数秒で数十回叩くので、その間に置いている
    'ip_block_watch_count' => 30,

    // 自動で止める時間（時間）。携帯電話や会社の回線は大勢で同じIPアドレスを使うので、長くしすぎない
    'ip_block_hours' => 24,

    // 信頼するプロキシのIPアドレス。サーバーの前にロードバランサーやCDNを置いたときに、
    // そのIPアドレスをカンマ区切りで書く（192.168.0.0/24のような範囲も書ける）。
    // ここに書いたプロキシから届いたX-Forwarded-Forを、訪問者のIPアドレスとして使う
    // （App\Providers\AppServiceProvider）。ここに書いたIPアドレスから接続した人は、
    // IPアドレスを偽れるようになるので、正確に書く。空なら、どのプロキシも信頼しない
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))
    )),

    // サイト（訪問者画面）に制限を掛けているときも開けるIPアドレス。カンマ区切りで書く（192.168.0.0/24のような範囲も書ける）。
    // 制限を掛けるかと、ほかのIPアドレスは、管理画面の「アクセス制限」で決める（Neobit\Core\Security\AccessRestriction）。
    // ここに書いたものは、画面の一覧に足され、画面からは外せない。ここに書いただけでは、制限は掛からない
    'site_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SITE_ALLOWED_IPS', '')))
    )),

    // 管理画面に制限を掛けているときも開けるIPアドレス。書き方はsite_allowed_ipsと同じ。管理画面の「アクセス制限」の
    // 一覧に足され、画面からは外せない。ここに書いただけでは、制限は掛からない。サイトの制限には影響しない
    'admin_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', '')))
    )),

];
