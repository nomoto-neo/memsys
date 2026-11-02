<?php

/**
 * ネオビットフレームワークの設定のうち、このサイトで変える値だけを、機能ごとの節で書く。
 * 既定の値と各項目の説明は、vendor/neobit/core/config/neobit/ の同じ名前のファイルにある。書いていないキーは、既定の値になる。
 * 節の中の配列は丸ごと置き換わるので、変えない要素も一緒に書く。
 */
return [

    // フレームワークの画面が継承する、アプリのレイアウト
    // 'layouts' => [
    //     'admin' => 'layouts.admin',
    //     'app' => 'layouts.app',
    // ],

    // 'route' => ['admin_path' => 'app/admin'],
    // 'form' => ['required_mark' => [...]],
    // 'code_table' => ['db' => [...], 'method' => [...]],
    // 'operation_log' => ['actions' => [...], 'models' => [...], 'route' => 'admin.operation-logs.index'],
    // 'security' => ['ip_block_auto' => true],
    // 'file_watch' => ['release_file' => '.version'],
    // 'seo' => ['noindex_routes' => [...], 'sitemap_routes' => [...]],
    // 'schedule' => ['cron_enabled' => env('CRON_ENABLED', true)],
    // 'upload' => ['max_kb' => 10240],
    // 'cleanup' => ['tasks' => [...]],

    // 公開ディレクトリにWordPressを置き、決まったコーナーだけを受け持たせるときに書く。この節があることが「WordPressを置く」の印になる。
    // 使い方は利用ガイドの「WordPressとの共存」。
    // - gatekeeper    WordPressに入口の見張りを掛ける段。サーバーで決まるので.envのWORDPRESS_GATEKEEPERに書く。
    //                 prepend（PHPのauto_prepend_file）かwp-config（wp-config.phpの頭のrequire）。
    //                 この節があるのに空なら、WordPressが守られていないとしてcriticalのログを出す
    // - admin_paths   管理画面とログインのURL。サイトの制限は掛けず、管理画面の制限を掛ける
    // - open_paths    いつも通すURL。サイトの制限も管理画面の制限も掛けず、止めているIPだけは止める
    // - hidden_paths  いつも404にするURL。使わない入口を、無いことにして隠す
    //                 URLは先頭の/を付けずに書き、*で下を全部にする（サイトの制限を掛けないURLと同じ書き方）
    // - wp_config     wp-config.phpの場所（プロジェクトの直下からのパス）。gatekeeperがwp-configのときに確かめる
    // - upload_path   WordPressのアップロードの置き場所（プロジェクトの直下からのパス）。改ざん監視は、storage/app/publicと
    //                 同じく、ファイルの種類（PHPと許可されていない拡張子）を確かめ、.htaccess・.user.iniだけを基準と比べる
    // - watch_exclude 改ざん監視で何も見ない場所（プロジェクトの直下からのパス）。キャッシュのプラグインが書く場所など
    // 'wordpress' => [
    //     'gatekeeper' => env('WORDPRESS_GATEKEEPER'),
    //     'admin_paths' => [
    //         'wp-admin',
    //         'wp-admin/*',
    //         'wp-login.php',
    //     ],
    //     'open_paths' => [
    //         'wp-admin/admin-ajax.php',
    //         'wp-cron.php',
    //         // 表のフォームの送り先にするプラグインがあるとき
    //         // 'wp-admin/admin-post.php',
    //     ],
    //     'hidden_paths' => [
    //         'xmlrpc.php',
    //     ],
    //     'wp_config' => 'wp-config.php',
    //     'upload_path' => 'public/wp-content/uploads',
    //     'watch_exclude' => [
    //         'public/wp-content/cache',
    //     ],
    // ],

];
