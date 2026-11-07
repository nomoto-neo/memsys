# WordPress との共存

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Security/Gatekeeper.php`・`GatekeeperState.php`（冒頭のコメントの「WordPressの見張り」）・`src/Console/CheckGatekeeper.php`・`src/Security/FileWatcher.php`、設定は `config/neobit.php` の `wordpress`

既存の WordPress のサイトに Laravel を入れるときの置き方です。狙われやすい WordPress を後ろに下げ、自衛の機能が効く Laravel を前に出します。WordPress には決まったコーナー（`/column/` など）だけを受け持たせ、ほかの URL は Laravel が受けます。

WordPress は `__()` などの関数を条件なしで作るので、Laravel と同じ PHP の中では動かせません。`public/.htaccess` で、URL ごとにどちらかへ振り分けます。

## フレームワークが受け持つ守りと、WordPress 次第のもの

| 守り | WordPress のコーナーの下 |
|---|---|
| IP ブロック・メンテナンスの画面・サイトと管理画面のアクセス制限 | 効く（入口の見張りを WordPress にも掛ける） |
| ベーシック認証・ファイルの権限・改ざん監視 | 効く（Apache とサーバーの仕組みなので） |
| リダイレクト（[redirect.md](redirect.md)） | 効かない。`.htaccess` が Laravel より先に WordPress へ回すため |
| ログインの守り・操作ログ・スパムの守り・SEO・存在しない URL の数え（自動で止める） | WordPress 次第 |

## 置き方

- WordPress の本体（`wp-admin/`・`wp-includes/`・`wp-content/`・`wp-*.php`）を、`public/` の直下に置きます。既存のサイトと同じ `/wp-admin/` の URL のままにするためです。
- 直下の `index.php` は Laravel の入口なので、WordPress の入口は `public/wordpress.php` という名前で置きます。中身は WordPress の `index.php` と同じです。
- WordPress が書き込む場所（アップロードの置き場所と、キャッシュのプラグインが書く場所）は、プロジェクトの直下の `set_permissions.writable` に書き、`set_permissions.sh` を流します（[deploy.md](deploy.md)の「サーバーの権限」）。書かないと、全体と同じく apache は読むだけになり、画像を上げると「ディレクトリを作成できません」で止まります。

    ```
    public/wp-content/uploads
    public/wp-content/cache
    ```


    ```php
    <?php

    /**
     * WordPress の入口。WordPress の index.php の代わり。
     * 最上位の index.php は Laravel の入口なので、WordPress に回す URL だけを public/.htaccess がここへ送る。
     */
    define('WP_USE_THEMES', true);

    require __DIR__.'/wp-blog-header.php';
    ```

- `wp-config.php` は、`public/` の外（プロジェクトの直下）に置きます。WordPress は、本体の1つ上のディレクトリの `wp-config.php` も探すので、そのまま読めます。Web から開けない場所に、DB のパスワードを置くためです。
- `wp-config.php` には、`DISALLOW_FILE_MODS` を書きます。本体・プラグイン・テーマの更新とインストールと、ファイルの画面での編集を、WordPress の管理画面から止めます。管理画面を乗っ取られても裏口の PHP を置かれないようにするためで、apache がコードに書けない権限の形（[deploy.md](deploy.md)）とも合います。更新は、保守の作業として作業する人がファイルを送って当て、`.version` も送ります（[file-watch.md](file-watch.md)）。お客さんが自分でプラグインを入れている案件では、この取り決めを結べるかを先に確かめます。結べなければ、次の「お客さんが画面から更新する案件」の形にします。

    ```php
    define('DISALLOW_FILE_MODS', true);
    define('AUTOMATIC_UPDATER_DISABLED', true);
    ```

- お客さんが画面から更新する案件では、プラグイン「SSH SFTP Updater Support」を使い、WordPress が SSH でつないだ利用者の権限で書くようにします。apache がコードに書けない権限の形は、そのままです。パスワードは保存されないので、管理画面を乗っ取られても、SSH のパスワードを知らなければ裏口のプラグインを入れられません。
    1. 更新の専用の利用者を案件ごとに作り、`dev` グループに入れて、SFTP だけに絞ります。お客さんに devapp や社内の人の利用者のパスワードを渡さないためです。
    2. プラグインを WordPress.org から取り、ファイルで `wp-content/plugins/` へ送って有効にし、管理画面の「改ざん監視」で承認します。SSH の選択肢はこのプラグインが足すので、最初の1回は画面から入れられません。
    3. `wp-config.php` の `DISALLOW_FILE_MODS` を外し、次のように書きます。`DISALLOW_FILE_MODS` を外すとファイルの画面での編集も開くので、`DISALLOW_FILE_EDIT` で止めます。

        ```php
        define('DISALLOW_FILE_EDIT', true);
        define('FS_METHOD', 'ssh2');
        define('AUTOMATIC_UPDATER_DISABLED', true);
        ```

    4. 更新のときは、接続の形に SSH を選び、ホスト名に `localhost`、専用の利用者とパスワードを入れます。
    5. 更新の後、管理画面の「改ざん監視」で変わったファイルを確かめて承認します（[file-watch.md](file-watch.md)）。承認しないと、15分後に改ざんの疑いとして知らせます。
    6. 自動更新は、パスワードを聞けないので動きません。

- `public/.htaccess` は、apache が書けない権限のままにします。WordPress のパーマリンクの設定を保存すると、WordPress は `public/.htaccess` を書き換えようとするためです。書けなければ、WordPress は書くべき内容を画面に出すだけで、何も壊しません。

## `.htaccess` の振り分け

`public/.htaccess` の、Laravel の決まり（`# Send Requests To Front Controller...`）の前に書きます。コーナーを足したら、ここにも足します。

```apache
# WordPress に回す URL。Laravel より先に決める
# WordPress の入口に回した後の、もう一巡りでは何もしない
RewriteRule ^wordpress\.php$ - [L]
# 本体のディレクトリと、最上位の WordPress のファイルは、Apache が直に返す・動かす
RewriteRule ^(wp-admin|wp-includes|wp-content)(/|$) - [L]
RewriteRule ^(wp-[a-z-]+|xmlrpc)\.php$ - [L]
# コーナーの頭の名前の下と、WordPress の API・サイトマップは、WordPress の入口へ
RewriteRule ^(column|topics|wp-json)(/|$) wordpress.php [L]
RewriteRule ^wp-sitemap.*\.xml$ wordpress.php [L]
# 最上位に WordPress の問い合わせ（プレビュー・短い URL・検索）が付いたものも、WordPress の入口へ
RewriteCond %{QUERY_STRING} (^|&)(p|page_id|post_type|preview|s)= [NC]
RewriteRule ^$ wordpress.php [L]
```

- 回すのは、ここに書いた URL だけです。知らない URL は Laravel が受けます（固定ページ・リダイレクト・404）。
- コーナーは、記事・一覧・2ページ目・月別・`/feed/` が全部その頭の名前の下に入るよう、WordPress のパーマリンクを決めます。投稿の記事は「設定」→「パーマリンク」のカスタム構造で、カスタム投稿タイプは登録するときの `rewrite` の `slug` で決まります。
- 最上位（`/`）に `p`・`page_id`・`post_type`・`preview`・`s` の問い合わせが付いたものも WordPress に回します。下書きのプレビュー（`/?post_type=news&p=123&preview=true`）・短い URL・検索は、パーマリンクを決めていても最上位で呼ばれるためです。Laravel の側では、最上位でこれらの名前を使いません（検索を作るときは `/search?q=` のように別の URL にします）。
- 共通のヘッダー・フッターは、Laravel のレイアウトと WordPress のテーマの両方に持ちます。

## 設定

WordPress を置く案件だけが、アプリの `config/neobit.php` に `wordpress` の節を書きます。この節は既定の値を持たないので、全部のキーを書きます。

```php
    'wordpress' => [
        'gatekeeper' => env('WORDPRESS_GATEKEEPER'),
        'admin_paths' => [
            'wp-admin',
            'wp-admin/*',
            'wp-login.php',
        ],
        'open_paths' => [
            'wp-admin/admin-ajax.php',
            'wp-cron.php',
            // 表のフォームの送り先にするプラグインがあるとき
            // 'wp-admin/admin-post.php',
        ],
        'hidden_paths' => [
            'xmlrpc.php',
        ],
        'wp_config' => 'wp-config.php',
        'upload_path' => 'public/wp-content/uploads',
        'watch_exclude' => [
            'public/wp-content/cache',
        ],
    ],
```

| キー | 中身 |
|---|---|
| `gatekeeper` | 入口の見張りを掛ける段。サーバーで決まるので、`.env` の `WORDPRESS_GATEKEEPER` に書きます（下の「見張りの掛け方」） |
| `admin_paths` | 管理画面とログインの URL。Laravel の管理画面と同じく、サイトの制限は掛けず、管理画面の制限（許す IP の外は 404）を掛けます。ログインの URL を変えるプラグインを使う案件では、ここを書き換えます |
| `open_paths` | いつも通す URL。サイトの制限も管理画面の制限も掛けません。止めている IP だけは止めます。`admin-ajax.php` は表の画面からも呼ばれ、`wp-cron.php` は WordPress がサーバー自身から HTTP で呼ぶ（止めると予約投稿などが止まる）ためです |
| `hidden_paths` | いつも 404 にする URL。使わない入口を、無いことにして隠します。`xmlrpc.php` を使う案件では、ここから外します |
| `wp_config` | `wp-config.php` の場所（プロジェクトの直下からのパス） |
| `upload_path` | WordPress のアップロードの置き場所。改ざん監視は、`storage/app/public` と同じ扱いにします。ファイルの種類を確かめ、`.htaccess`・`.user.ini` だけを基準と比べます（[file-watch.md](file-watch.md)） |
| `watch_exclude` | 改ざん監視で何も見ない場所。キャッシュのプラグインが書く場所など。中身の書き換えに気付けなくなるので、要る場所だけを書きます |

- URL は、先頭の `/` を付けずに書き、`*` で下を全部にします（サイトの制限を掛けない URL と同じ書き方です）。
- `admin-post.php` は、表のフォームの送り先にするプラグインがあるときだけ `open_paths` に足します。足さずに管理画面の制限を掛けると、訪問者のフォームが 404 になります。
- `wp-json`（REST API）は、どこにも書きません。表の URL と同じくサイトの制限が掛かります。ブロックエディタの保存も `wp-json` を通りますが、管理画面の許す IP はサイトの制限でも許す（[access-restriction.md](access-restriction.md)）ので、メンテナンス中も記事を保存できます。表のフォーム（Contact Form 7 など）が `wp-json` を使っていても、管理画面の制限には掛かりません。
- 設定を変えたら、`php artisan config:cache` と `php artisan neobit:gatekeeper --export` を実行します。

## 見張りの掛け方

`index.php` を通らない WordPress には、`public/index.php` の見張り（[ip-block.md](ip-block.md)）が掛かりません。次のどれかで掛け、その段を `.env` に書きます。上のものほど強いので、使えるものを上から選びます。

| 段 | `.env` | 掛け方 | 使うサーバー |
|---|---|---|---|
| PHP-FPM のプールの設定 | `WORDPRESS_GATEKEEPER=prepend` | プールの設定に `php_admin_value[auto_prepend_file]` を書く。`.user.ini` や `ini_set()` で後から外せない | 自前の VPS |
| `.user.ini` | `WORDPRESS_GATEKEEPER=prepend` | `public/.user.ini` に `auto_prepend_file` を書く | プールの設定に触れない共用のサーバー |
| `wp-config.php` | `WORDPRESS_GATEKEEPER=wp-config` | `wp-config.php` の頭で `require` する | どちらも使えないとき |

```
; PHP-FPM のプールの設定（/etc/php-fpm.d/サイトの名前.conf）
php_admin_value[auto_prepend_file] = /var/www/memsys_dev/vendor/neobit/core/gatekeeper.php

; public/.user.ini
auto_prepend_file = /var/www/memsys_dev/vendor/neobit/core/gatekeeper.php
```

```php
// wp-config.php の頭（<?php の次の行）
require __DIR__.'/vendor/neobit/core/gatekeeper.php';
```

- **プールはサイトごとに分けます**。`php_admin_value` はプールの全部のスクリプトに効くので、ほかのサイトと同じプールに書くと、ほかのサイトにもこのサイトの止める IP が掛かります。書き換えたら、`systemctl reload php-fpm` で読み直します（root の作業です）。
- **`.user.ini` は、効くまで5分ほどかかります**。PHP-FPM が読み直す間隔（`user_ini.cache_ttl`、既定で5分）のためです。置いた直後の `neobit:gatekeeper` は「掛かっていない」と出ることがあります。
- `prepend` の2つは、`public/index.php` にも掛かります。`index.php` の `require` と両方で掛かっても、2回目は何もしません。
- **`wp-config.php` の段の弱いところ**：WordPress が PHP で返すもの（表のページ・`wp-login.php`・`wp-admin/` の下・`wp-json/`・`xmlrpc.php`・`wp-cron.php`）は全部 `wp-config.php` を読むので、見張りが掛かります。プラグインの `.php` を URL で直に開かれたときは掛からないので、`.htaccess` で `wp-content/` の下の `.php` を直に開けないようにして補います。動かなくなるプラグインが無いかは、案件ごとに確かめます。この行は、WordPress のファイルを Apache に直に返させる行（`wp-admin|wp-includes|wp-content`）より**上**に置きます。下に置くと、上の行で書き換えが終わり、この行まで届きません。

    ```apache
    RewriteRule ^wordpress\.php$ - [L]
    # wp-content の下の .php を、URL で直に開かせない（wp-config の段のとき）。下の行より先に決める
    RewriteRule ^wp-content/.*\.php$ - [R=404,L]
    # 本体のディレクトリと、最上位の WordPress のファイルは、Apache が直に返す・動かす
    RewriteRule ^(wp-admin|wp-includes|wp-content)(/|$) - [L]
    ```

- 画像・CSS などの静的なファイルには、どの段でも見張りは掛かりません（Laravel の側と同じです）。
- **掛かっているかの確かめ**：`php artisan neobit:gatekeeper` が、`WordPress の保護機能：` の行で知らせます。
    - `prepend` は、コマンドの PHP の設定が Web と別なので、コマンドからは分かりません。Laravel が Web の要求の中で `auto_prepend_file` を見て、結果を控えておき、`neobit:gatekeeper` はそれを出します。Laravel のページ（管理画面など）を1回も開いていなければ「まだ確かめていません」と警告を出します（送った直後の手順を失敗に見せないよう、失敗にはしません）。
    - `wp-config` は、`wp-config.php` に `gatekeeper.php` の `require` があるかを見ます。
    - 掛かっていないときは、`neobit:gatekeeper` は失敗で終わります。
    - `wp-config` の確かめでは、コメントにした行（`//`・`#`・`*` で始まる行）は数えません。
- **書き忘れたとき**：`wordpress` の節があるのに `.env` の `WORDPRESS_GATEKEEPER` が空なら、WordPress が守られていないので、critical のログを書きます（エラーの通知のメールが届きます。1時間に1回まで）。`prepend` なのに `auto_prepend_file` が見張りを指していないと分かったときも、critical のログを書きます。

## アップロードの置き場所の `.htaccess`

アップロードの置き場所は apache が書けるので、悪い `.htaccess` を置かれると、`AddHandler` で `.jpg` を PHP として動かす設定にされます。改ざん監視は知らせます（[file-watch.md](file-watch.md)）が、Apache の設定で、そもそも効かないようにしておくと確かです。`.htaccess` に書けるのを、アクセスの許可・拒否だけにします。プラグインが置く「開かせない」ための `.htaccess` は、そのまま効きます。

```apache
# VirtualHost の中。WordPress のアップロードの置き場所では、.htaccess に書けるのをアクセスの許可・拒否だけにする
<Directory /var/www/memsys_dev/public/wp-content/uploads>
    AllowOverride AuthConfig Limit
</Directory>
```

## セキュリティのヘッダー

WordPress の画面と、見張りが返す 404・503 には、`SecurityHeaders`（[settings.md](settings.md)）のヘッダーが付きません。Laravel を通らないためです。Apache の設定で付けます。HTTPS の VirtualHost（certbot で証明書を取ったサーバーでは、certbot が作る `〜-le-ssl.conf` の `<VirtualHost *:443>`）に書きます。HTTP の VirtualHost は HTTPS へ移すだけなので、書いても HTTPS の画面には付きません。

```apache
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == 'on'"
```

```bash
sudo apachectl configtest && sudo systemctl reload httpd
```

- **`always` を付けます**。PHP-FPM（Laravel・WordPress・見張り）が返したヘッダーは、Apache の `always` の側に入ります。`Header always set` はそれを上書きするので、Laravel が付けた同じヘッダーと二重になりません。`always` を付けない `Header set` は別に足すので、Laravel と WordPress の画面で同じヘッダーが2行になります（2026-10-30 に開発サーバーで確かめた）。
- 書いたら、`curl -sI` で Laravel の画面（管理画面のログイン画面など）・WordPress の画面（`/wp-login.php` など）・見張りの 404（`/xmlrpc.php`）・503（サイトの制限の間）を開き、どれにも1行ずつ付くことを確かめます。
- 見張りの 404・503 にもヘッダーが付くので、止められた人が、ほかの画面とヘッダーを見比べても気付きません。WordPress を置かないサイトでも、書いておくと同じ効き目があります。

## コーナーを足すとき

1. WordPress の管理画面で、投稿タイプかカテゴリーを作り、パーマリンクがその頭の名前の下に入るようにします。
2. `public/.htaccess` の `RewriteRule ^(column|topics|wp-json)(/|$) wordpress.php [L]` に、頭の名前を足します。
3. Laravel の側で、同じ頭の名前の URL（ルート・固定ページ・リダイレクト）を使っていないことを確かめます。
