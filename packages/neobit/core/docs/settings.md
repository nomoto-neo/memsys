# サイトの設定

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

## 設定の既定の値と、案件での上書き

- **設定の既定の値は、フレームワークが持ちます**。`vendor/neobit/core/config/neobit/` に、機能ごとのファイルで持ちます（`auth`・`cleanup`・`code_table`・`file_watch`・`form`・`layouts`・`operation_log`・`rebuild`・`route`・`schedule`・`security`・`seo`・`site`・`upload`）。
- **案件は `config/neobit.php` の1ファイルに、変える値だけを書きます**。機能ごとの節で書きます（例：`'seo' => ['redirects' => true]`）。書いたキーが既定に優先し、書いていないキーは既定の値になります。こうしておくと、案件が何を変えたかが1つのファイルで分かり、パッケージの更新で足した既定の値が、案件のファイルを直さずに効きます。
- 節の中の配列（`neobit.upload.image_types`・`neobit.operation_log.report_thresholds` など）は丸ごと置き換わるので、変えない要素も一緒に書きます。その部分は、パッケージの更新が効かなくなるので、更新のときに見比べます。
- `php artisan vendor:publish --tag=neobit-config` で、見本（`packages/neobit/core/stubs/config/neobit.php`）を `config/` へ写せます。
- 案件の機能の設定は、名前の頭に `site_` を付けたファイル（`site_contact.php`・`site_login.php`・`site_bulk_mail.php`）に置きます。決まりは [rules.md](rules.md) の「設定ファイル」にあります。

## サイトの名前（APP_NAME・SITE_NAME）

サイトの名前は、`.env` の2つの項目で決めます。

```
APP_NAME=memsys
SITE_NAME="デモサイト"
MAIL_FROM_NAME="${SITE_NAME}"
```

| 項目 | 中身 | 使われる所 |
|---|---|---|
| `APP_NAME` | 機械の名前。半角の英数字で書く | セッションの Cookie の名前（`memsys-session`）、キャッシュのキーの頭 |
| `SITE_NAME` | 画面やメールに出す名前。日本語で書いてよい | メニューの左上、ブラウザのタブの題名、メールの差出人名、エラーの通知と操作ログの報告の件名 |

- **`APP_NAME` を日本語にしてはいけません**。Laravel は、`APP_NAME` から英数字だけを残して、Cookie とキャッシュの名前を作ります。日本語だけだと全部が消えて、Cookie の名前が `-session` になります。同じドメインに置いたほかの Laravel のサイトと名前が重なり、片方にログインすると、もう片方がログアウトします。
- **`SITE_NAME` を書かなければ、名前は出ません**。空のままになり、`APP_NAME` を代わりに出すことはありません。`APP_NAME` は処理の中で使う識別名で、人に見せる名前ではないためです。サイトを設置するときに、必ず `SITE_NAME` を書きます。
- **画面では `config('neobit.site.name')` で出します**。レイアウト（`layouts/app.blade.php`・`admin.blade.php`・`company.blade.php`）が、メニューと題名に使っています。メンテナンス中の画面（`maintenance.blade.php`）だけは、設定に頼らない1枚の HTML なので、名前を直接書いてあります。
- **`.env` に書くのは、サーバーごとに違う値と、秘密の値だけです**。接続先・宛先・鍵などです。残す日数や回数のように、どのサーバーでも同じ値（サイトの決まりごと）は、`config/` のファイルに書きます。Git に記録されるので、いつ変えたかを追えて、サーバーごとに値がずれません。Laravel の標準の項目のうち、このサイトの `.env` に書いていないものは、控えとして `.env.unused` にあります。Laravel は、このファイルを読みません。
- **`.env` を変えたら `php artisan config:cache` をやり直します**。`APP_NAME` を変えると Cookie の名前が変わるので、ログイン中の人は、ログインし直しになります。

## 管理画面の URL（admin_path）

管理画面の頭の URL は、`config/neobit.php` の `route.admin_path` で決めます。既定は `app/admin`（`/app/admin/login` など）です。`/admin` は URL を順に叩く攻撃で真っ先に狙われるので、既定でも `/admin` には置きません。

- `bootstrap/app.php` は、管理画面のルートのファイルを `->prefix(config('neobit.route.admin_path'))` で読みます。ルートの名前（`admin.*`）は URL に関係なく同じなので、画面とコントローラーは直しません。
- サイトのアクセス制限（[access-restriction.md](access-restriction.md)）は、`route.admin_path` の下をいつも制限の外にします。`bootstrap/app.php` に書く必要はありません。
- **robots.txt には書きません**。robots.txt は誰でも読めるので、隠した URL を教えることになるためです。管理画面のルートのグループには `NoIndexHeader` を付け、全部の応答に `X-Robots-Tag: noindex, nofollow` を付けて、検索に出ないようにします。
- **前の `/admin` は 404 にします**。新しい URL へ移すと、移した先を教えることになるためです。
- **変えたら合わせて直すもの**：ベーシック認証の VirtualHost の決まり（[basic-auth.md](basic-auth.md)）の `m#^/app/admin#`。
- Laravel を公開ディレクトリの外に置けないサーバー（[security.md](security.md)）では、`app/system/` に本体を置くので、`route.admin_path` の最初の区切りを `app` 以外にしても、本体の場所とは重なりません。

## 管理画面の見た目（admin.css）

管理画面のレイアウト（`resources/views/layouts/admin.blade.php`）は、フレームワークの `admin.css` と `admin_layout.js` で見た目と動きを決めます。

- **色は組み合わせの名前で選びます**。レイアウトの `<html data-theme="navy">` に、`navy`・`green`・`orange`・`rose`・`light` のどれかを書きます。書かなければ `navy` です。本文の地は白で、ヘッダーにはメニューと同じ系統の薄い色を付けます。
- **見本のサイトは、ヘッダーのプルダウンで色を切り替えられます**。プレゼンで見比べるためのもので、選んだ色はそのブラウザーだけに覚え、ほかの人の画面は変わりません。色を決めたサイトでは、レイアウトからプルダウンを消します。動きは `admin_layout.js` が `data-nb-theme-select` の付いたプルダウンに付けます。
- **色の値は CSS 変数です**（`--nb-header-bg`・`--nb-sidebar-bg`・`--nb-sidebar-text`・`--nb-sidebar-current-bg` など。全部は `admin.css` の頭にあります）。案件だけの色にするときは、案件の CSS で `[data-theme="navy"]` の変数を上書きします。
- **組み合わせで変わるのは、ヘッダーとサイドメニューだけです**。本文の中（ボタン・リンク・入力欄・チェック・ページ送り・タブ・Bootstrap の primary の色）は、説明で「青いボタン」と言えるよう、どの組み合わせでも同じ青（`--nb-primary`、`#1a73e8`）です。
- **文字の大きさは、`<html data-font-size="medium">` で選びます**。`small`（90%）・`medium`（100%。既定）・`large`（110%）で、Bootstrap の部品と余白も同じ割合で変わります。見本のサイトは、ヘッダーのプルダウンで選べます（`data-nb-font-size-select`。選んだ大きさはそのブラウザーだけに覚えます）。書体は端末の日本語の書体です。
- **本文は横幅の95%を使います**。入力欄の少ない画面だけ、`@section('content-width', '800px')` のように書いて最大幅を絞れます。
- **広い画面のメニュー**：ヘッダーの ☰ で、固定表示とアイコンだけの表示を切り替えます。アイコンだけの表示では、マウスを乗せるかキーボードで入ると、本文の上に重なって開きます。選んだ表示は、ブラウザーに覚えます。
- **メニューのグループ**：見出しを押すと開閉します（`<details>`）。同じ `name` を付けているので、1つを開くとほかは閉じます。今の画面のグループを開いて出します。グループとアイコンは `admin/_menu.blade.php` に書きます。
- **メニューのアイコン**：Bootstrap Icons（CDN）を使います。`admin/_menu.blade.php` のグループに、アイコンの名前（`bi-people` など）を書きます。名前は [Bootstrap Icons の一覧](https://icons.getbootstrap.com/) から選びます。
- **狭い画面のメニュー**：☰ で左から引き出します（Bootstrap の `offcanvas-lg`）。

## サーバーの前にプロキシを置くとき（TRUSTED_PROXIES）

ロードバランサーや CDN をサーバーの前に置くと、サーバーに直接つないでくるのはそのプロキシになります。訪問者の IP アドレスは、プロキシが付ける `X-Forwarded-For` というヘッダーで届きます。

```
# .env（プロキシの IP アドレス。カンマ区切り。範囲は 192.168.0.0/24 の形）
TRUSTED_PROXIES=10.0.0.5,10.0.0.6
```

- ここに書いたプロキシから届いたヘッダーだけを、訪問者の IP アドレスとして使います（`AppServiceProvider`）。`https` かどうかやホスト名も、同じプロキシのヘッダーから取ります。
- **プロキシを置いたのに書かないと**、全員の IP アドレスがプロキシのものになります。ログインの試行制限が1人の失敗で全員に効く、回数の制限が全員で共有される、操作ログやエラーの通知の IP アドレスが全部同じになる、といったことが起きます。
- **プロキシの IP アドレスだけを、正確に書きます**。ここに書いた IP アドレスから接続した人は、IP アドレスを偽れるようになります。このヘッダーは送る側が自由に書けるためです。偽れると、試行制限を逃れたり、接続元の IP アドレスの制限をすり抜けたりできます。広い範囲や、プロキシではない機器の IP アドレスは書きません。プロキシを置いていなければ、空のままにします。
- 訪問者の側がプロキシや VPN を通してきたときは、そのプロキシの IP アドレスが残ります。本当の IP アドレスは、こちらからは分かりません。
- `.env` を変えたら `php artisan config:cache` をやり直します。

## セキュリティのヘッダー（SecurityHeaders）

**ファイル**：`packages/neobit/core/src/Http/Middleware/SecurityHeaders.php`（冒頭のコメント）

ブラウザに守り方を伝えるヘッダーを、全部の応答に付けます。`bootstrap/app.php` で全体のミドルウェアに足しているので、コントローラーと画面に書くものはありません。

| ヘッダー | 値 | 働き |
|---|---|---|
| `X-Frame-Options` | `SAMEORIGIN` | ほかのサイトの `<iframe>` の中に、このサイトの画面を出させない |
| `X-Content-Type-Options` | `nosniff` | ファイルの種類を、ブラウザに中身から決めつけさせない |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | ほかのサイトへ移るときは、開いていた URL のうちドメインまでしか渡さない |
| `Strict-Transport-Security` | `max-age=31536000` | ブラウザが1年の間、このドメインを HTTPS だけで開く。HTTPS で届いたときだけ付く |

- **環境ごとの切り替えは要りません**。`Strict-Transport-Security` は、HTTPS で届いたリクエストにだけ返します。HTTP で動かす手元の開発環境では出ません。
- **HTTPS をやめる予定のあるドメインでは、先に外します**。一度受け取ったブラウザは、1年の間そのドメインを HTTP で開けなくなります。外すときは、`max-age=0` を返す期間を置いてから HTTPS をやめます。
- サブドメインは対象にしていません。同じドメインの下に HTTP で動かしているサイトがあっても、開けなくなることはありません。
- プロキシを前に置くときは、`TRUSTED_PROXIES` を書きます。書かないと、HTTPS で届いたことが分からず、`Strict-Transport-Security` が付きません。
- **Apache が直接返すファイルには付きません**。`public/build/` の中と、公開のアップロードファイル（`public/storage/`）は、Laravel を通らないためです。そちらにも付けるときは、Apache の設定に書きます。
- 読み込んでよい場所を決める `Content-Security-Policy` は、まだ付けていません。
