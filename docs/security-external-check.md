# 外からのセキュリティの確認

設置したサイトを、外から読むだけで確かめる手順と、その結果の記録です。サイトに何も書き込まない範囲だけを扱います。サーバーを移したときや、公開の前に、同じ手順でやり直せるように書いています。

未対応の課題の一覧は `security-issues.md`、サーバーの側で決めることは利用ガイド（`neobit-framework-guide.md`）の付録にあります。

## この確認で分かること・分からないこと

| | 内容 |
|---|---|
| 分かること | サーバーと Laravel の設定の漏れ。HTTPS・ヘッダー・Cookie・公開されてはいけないファイル・開発用の入口・エラーの画面 |
| 分からないこと | プログラムの穴。SQL インジェクション・XSS・権限の越境・ログインの総当たりへの耐性・アップロードの検査 |

分からないことを確かめるには、ログインの試行やフォームの送信のような、書き込みを伴う検査が要ります。サイトにデータ・操作ログ・メールが残るので、対象のアカウントを決めてから、別に行います。

## 用意するもの

- `curl` と `openssl`。手元の Git Bash に入っているもので足ります。
- `composer`。パッケージの既知の脆弱性を調べるのに使います。
- ベーシック認証を掛けているサイトでは、その ID とパスワード。

以下のコマンドは、Git Bash で動かします。最初に、対象の URL とベーシック認証を変数に入れます。パスワードは、このファイルにも、ほかのファイルにも書きません。

```bash
B="https://dev.neobit.jp"
A="ユーザー名:パスワード"      # ベーシック認証。掛けていなければ、以下の -u $A を外す
```

## 手順

### 1. HTTP から HTTPS へ転送されるか

```bash
curl -sS -o /dev/null -D - http://dev.neobit.jp/ | head -8
```

- **見るところ**：`301` で、`Location:` が `https://` になっていること。
- **だめなとき**：HTTP のまま画面が開くと、通信を盗み見られます。Apache の設定で転送します。

### 2. 認証なしで開けるものが無いか（ベーシック認証を掛けているサイトだけ）

```bash
for p in / /up /admin/login /mail/unsubscribe /build/manifest.json /storage/ /robots.txt /favicon.ico; do
  printf "%-28s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code}' $B$p)"
done
```

- **見るところ**：全部が `401` であること。Laravel を通る画面と、Apache が直接返すファイル（`/build/`・`/storage/`）の両方を見ます。
- **だめなとき**：`200` のものは、認証の外に出ています。Apache の認証の設定の範囲を見直します。

### 3. 証明書と TLS の版

```bash
# 証明書の対象・発行元・期限
echo | openssl s_client -connect dev.neobit.jp:443 -servername dev.neobit.jp 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates -ext subjectAltName

# 古い版の TLS でつながらないこと
for v in tls1 tls1_1 tls1_2 tls1_3; do
  echo | openssl s_client -connect dev.neobit.jp:443 -servername dev.neobit.jp -$v 2>&1 \
    | grep -q "Cipher is (NONE)\|alert\|unsupported\|no protocols" && echo "$v: 拒否" || echo "$v: 接続できる"
done
```

- **見るところ**：期限（`notAfter`）が切れていないこと。`Subject Alternative Name` に、そのドメインがあること。TLS 1.0 と 1.1 が拒否で、1.2 と 1.3 だけがつながること。

### 4. 応答のヘッダーと Cookie

```bash
curl -sS -o /dev/null -D - -u $A $B/

# Cookie の属性だけを見る。長い値は「…」に縮める
curl -sS -o /dev/null -D - -u $A $B/ | grep -i "^set-cookie" | sed -E 's/=[^;]{20,}/=…/'
```

- **見るところ（ヘッダー）**：次の4つがあること（`SecurityHeaders`。利用ガイド0章）。
  - `X-Frame-Options: SAMEORIGIN`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Strict-Transport-Security: max-age=31536000`
- **見るところ（Cookie）**：セッションの Cookie に `secure`・`httponly`・`samesite=lax` が付いていること。`XSRF-TOKEN` は、JavaScript が読むものなので `httponly` が無くて正常です。
- **見るところ（出すぎている情報）**：`Server:` に版の番号が出ていないこと。`X-Powered-By:` が無いこと。
- **見るところ（Cookie の名前）**：セッションの Cookie の名前が `サイト名-session` の形になっていること。`-session` だけなら、`.env` の `APP_NAME` が、空か、日本語だけです。Laravel は名前から英数字だけを残すので、日本語は消えます（利用ガイド0章「サイトの名前」）。

### 5. 公開されてはいけないファイルが見えないか

```bash
for p in /.env /.env.example /.git/HEAD /.git/config /.gitignore /composer.json /composer.lock \
         /package.json /artisan /CLAUDE.md /docs/security-issues.md /storage/logs/laravel.log \
         /storage/app/private/ /vendor/autoload.php /config/app.php /bootstrap/cache/config.php \
         /database/ /tools/table2rules.php /code/prefectures.csv /phpinfo.php /info.php \
         /server-status /server-info /.htaccess /public/index.php /build/ /storage/ \
         /backup.sql /dump.sql /adminer.php /phpmyadmin/; do
  printf "%-34s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code} %{size_download}B' -u $A $B$p)"
done
```

`301` が返ったものは、転送先を確かめます。

```bash
for p in /storage/app/private/ /database/ /phpmyadmin/; do
  printf "%-24s → %s\n" "$p" "$(curl -sS -o /dev/null -w '%{redirect_url}' -u $A $B$p)"
  printf "%-24s   %s\n" "${p%/}" "$(curl -sS -o /dev/null -w '%{http_code}' -u $A $B${p%/})"
done
```

- **見るところ**：`200` が1つも無いこと。`404` か `403` なら見えていません。`301` は、末尾の `/` を除いた URL への転送なので、転送先も `404` か `403` であること。
- **だめなとき**：`/.env` や `/composer.json` が `200` なら、公開のディレクトリがプロジェクトの直下になっています。Apache の `DocumentRoot` を `public/` にします。`/build/` や `/storage/` が `200` で一覧が出るなら、Apache の `Options -Indexes` が効いていません。
- **サイズの見方**：`404` のサイズがどれも同じなら、Laravel の 404 の画面です。違うサイズのものは、中身を開いて確かめます。

### 6. 開発用の入口が開いていないか

```bash
for p in /telescope /horizon /_debugbar/open /_ignition/health-check /log-viewer /pulse \
         /livewire/livewire.js /api/user /_boost/browser-logs /clockwork; do
  printf "%-28s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code}' -u $A $B$p)"
done
```

- **見るところ**：全部が `404` であること。
- **だめなとき**：`200` のものは、開発用のパッケージがサーバーで動いています。`composer install --no-dev` で入れ直します。

### 7. エラーの画面に、内部の情報が出ないか

```bash
curl -sS -u $A $B/no-such-page-xyz > /tmp/n404.html
grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|APP_KEY|DB_PASSWORD|/home/|/var/www|ignition|Whoops|SQLSTATE" /tmp/n404.html
grep -oE "<title>[^<]*</title>" /tmp/n404.html
```

無い URL への POST と、形の崩れた入力でも、同じように確かめます。

```bash
# 無い URL へ POST を送る
curl -sS -o /tmp/n405.html -w '%{http_code}\n' -u $A -X POST $B/login/verify/none
grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|/home/|/var/www|Whoops" /tmp/n405.html

# 1つの値のはずの項目を、配列で送る
curl -sS -o /tmp/n500.html -w '%{http_code}\n' -u $A "$B/news?page[]=1&page[]=2"
grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|/home/|/var/www|Whoops|SQLSTATE" /tmp/n500.html
```

- **見るところ**：`grep` の数が、どれも `0` であること。スタックトレースやサーバーのパスが出ていません。応答が `500` でも、情報が出ていなければ、この手順としては合格です。`500` になった入力は、プログラムの側で直す対象として控えます。
- **だめなとき**：`.env` の `APP_DEBUG` が `true` です。`false` にして、`php artisan config:cache` をやり直します。DB のパスワードまで画面に出るので、公開しているサイトでは最優先で直します。

### 8. 使わないメソッドが通らないか

```bash
for m in TRACE OPTIONS PUT DELETE; do
  printf "%-8s %s\n" "$m" "$(curl -sS -o /dev/null -w '%{http_code}' -u $A -X $m $B/)"
done

# TRACE が 200 のときは、中身を見る
curl -sS -u $A -X TRACE -H "X-Test: abc" $B/ | head -8
```

- **見るところ**：`TRACE` が `405` か `403` であること。`PUT`・`DELETE` が `405` であること。
- **だめなとき**：`TRACE` が `200` で、送ったヘッダーがそのまま返ってくるなら、Apache の `TraceEnable` が有効です。「対応の方法」のとおりに止めます。

### 9. Apache が直接返すファイルのヘッダー

```bash
asset=$(curl -sS -u $A $B/ | grep -oE '/build/assets/[^"]+' | head -1)
curl -sS -o /dev/null -D - -u $A "$B$asset" | grep -iE "^HTTP|x-frame|x-content|strict-transport|referrer|content-type"
```

- **見るところ**：`X-Content-Type-Options: nosniff` があること。`public/build/` と `public/storage/` は Laravel を通らないので、`SecurityHeaders` のヘッダーは付きません。付けるなら Apache の設定に書きます。

### 10. パッケージの既知の脆弱性

```bash
composer audit
npm audit --omit=dev
```

- **見るところ**：`composer audit` は `No security vulnerability advisories found.`、`npm audit` は、いちばん下の行が `found 0 vulnerabilities` であること。
- **`npm audit` の報告の読み方**：数が出たときは、字下げの無い行だけを拾います。それが原因のパッケージです。字下げされた `Depends on vulnerable versions of …` は、原因のパッケージを使っているので巻き込まれた、という連鎖です。数十件と出ても、原因は数個のことがほとんどです。
- **`--omit=dev` の意味**：本番で使うパッケージだけを調べます。付けないと、手元の開発のときだけ使う道具（Vite など）も調べます。サーバーの画面に入るのは、付けたほうの結果です。
- **`npm audit fix --force` は、提案を読んでから**：`Will install …, which is a breaking change` の行に、入れようとしている版が出ます。今より古い版へ下げる提案のことがあり、その場合は直りません。パッケージの配り方が変わっていないかを調べます。
- **報告が出たとき**：重大度と、そのパッケージを自分のコードが使っているかを見ます。版を上げるのは手元で行い、テストを通してから `lock` のファイルをサーバーへ送ります（利用ガイド0章）。
- 手元の `composer.lock` を調べるものです。サーバーに入っている版が手元と同じ、という前提です。詳しくは、利用ガイドの付録「パッケージの脆弱性の確認」にあります。

## 実施の記録

### 2026-10-07　dev.neobit.jp

デモサイト（ベーシック認証あり）を、手元の Windows 11 の Git Bash から確かめました。読むだけの確認で、ログインの試行・フォームの送信・攻撃用の文字列の送信はしていません。

| 手順 | 結果 | 判定 |
|---|---|---|
| 1. HTTPS への転送 | `301` で `https://dev.neobit.jp/` へ転送 | 良い |
| 2. 認証なし | 8つとも `401` | 良い |
| 3. 証明書 | Let's Encrypt。2026-09-26 〜 2026-12-25。対象は `demo.neobit.jp`・`dev.neobit.jp` | 良い |
| 3. TLS の版 | 1.0・1.1 は拒否。1.2・1.3 はつながる | 良い |
| 4. ヘッダー | 4つとも付いている。HSTS は `max-age=31536000` | 良い |
| 4. Cookie | セッションは `secure; httponly; samesite=lax`。`XSRF-TOKEN` は `secure; samesite=lax` | 良い |
| 4. 出すぎている情報 | `Server: Apache/2.4.63 (AlmaLinux) OpenSSL/3.5.8`。`X-Powered-By` は無い | **所見2** |
| 4. Cookie の名前 | `-session` | **所見3** |
| 5. 見えてはいけないファイル | 31個のうち `200` は無い。`404` が24個、`403` が4個、`301` が3個で、転送先は `404` か `403` | 良い |
| 6. 開発用の入口 | 10個とも `404` | 良い |
| 7. エラーの画面 | 内部の情報は出ない。`<title>Not Found</title>` | 良い |
| 8. メソッド | `TRACE` が `200` で、送ったヘッダーがそのまま返る。`OPTIONS` は `200`、`PUT`・`DELETE` は `405` | **所見1** |
| 9. 静的ファイル | `nosniff` は付いている。ほかの3つは付いていない | 補足 |
| 10. パッケージ（PHP） | `composer audit` で `league/commonmark` に2件 | **所見4** |
| 10. パッケージ（JavaScript） | `npm audit --omit=dev` で68件（低3・中64・高1）。原因は3つで、どれも CKEditor の古いパッケージが連れてきたもの | **所見5** |

手順5の補足です。`/storage/logs/laravel.log` と `/storage/app/private` は `403` で、サイズが Laravel の画面と同じでした。Apache ではなく Laravel が断っています。一覧には無い `/index.php` も確かめました。`200` ですが、トップの画面が出るだけです。

手順7の補足です。確かめたのは、無い URL を開いたときの 404 だけです。無い URL へ POST を送ったときも `404` で、情報は出ませんでした。`/news?page[]=1&page[]=2` のような形の崩れた入力は `200` で、エラーにはなりませんでした。プログラムの中で例外が起きたときの 500 の画面は、確かめていません。

#### 所見

| # | 所見 | 重さ | 直す場所 |
|---|---|---|---|
| 1 | `TRACE` メソッドが有効 | 低 | Apache の設定 |
| 2 | サーバーの版が応答に出ている | 低 | Apache の設定 |
| 3 | `APP_NAME` が日本語で、Cookie の名前が空になっている | 低 | サーバーの `.env` |
| 4 | `league/commonmark` に既知の脆弱性が2件（重大度：高・中） | 今は実害なし | `composer.lock` |
| 5 | CKEditor の古いパッケージに既知の脆弱性（貼り付けの XSS など） | CKEditor を使うサイトでは中 | `package.json` |

大きな穴は、この範囲では見つかりませんでした。どれもサイトを止めたり、情報が漏れたりするものではありません。ただ、1と2は、脆弱性診断を受けるとまず指摘される項目です。

**所見1　TRACE メソッドが有効**

- **起きていること**：`TRACE` を送ると、リクエストの中身がそのまま応答に返ります。ベーシック認証のヘッダーも返りました。
- **なぜ直すか**：ほかの穴と組み合わせると、JavaScript から読めないはずのヘッダーや Cookie を読む手掛かりになります。今のブラウザは JavaScript から `TRACE` を送れないので、悪用はされにくいですが、使わない機能は止めておくのが原則です。

**所見2　サーバーの版が応答に出ている**

- **起きていること**：全部の応答に、Apache・OS・OpenSSL の版が出ています。
- **なぜ直すか**：攻撃する側が、その版の既知の穴を探す手掛かりになります。

**所見3　`APP_NAME` が日本語で、Cookie の名前が空になっている**

- **起きていること**：セッションの Cookie の名前が `-session` です。Laravel は、`APP_NAME` から英数字だけを残して、Cookie とキャッシュの名前を作ります。サーバーの `APP_NAME` は日本語のサイト名だったので、全部が消えて、名前の部分が空になっていました。この回の最初の報告では「`APP_NAME` が空」と書きましたが、誤りでした。空の名前と、日本語だけの名前は、外からは同じに見えます。
- **なぜ直すか**：1つのサイトだけなら、動きに問題はありません。同じドメインに別の Laravel のサイトを置くと、Cookie の名前が重なって、片方にログインするともう片方がログアウトします。DB やキャッシュを共有すると、キャッシュのキーも重なります。

**所見4　`league/commonmark` の既知の脆弱性**

- **起きていること**：2.10.1 以下に、重大度「高」（表の変換で処理が極端に遅くなる）と「中」（許可していない HTML のタグがすり抜ける）が1件ずつあります。Laravel の本体が連れてくる、Markdown を変換する部品です。
- **実害**：memsys のコードは、Markdown の変換を使っていません（`Str::markdown()` などの呼び出しが無いことを確かめました）。今の時点で、外から突ける入口はありません。
- **なぜ直すか**：後から Markdown を使う機能を足したときに、穴のある版のまま使ってしまうためです。

**所見5　CKEditor の古いパッケージ**

- **起きていること**：`@ckeditor/ckeditor5-build-classic`（41.4.2）と、それが連れてくる `lodash-es`・`jsdom` に、既知の脆弱性があります。原因は、貼り付けの処理の XSS（中）、`lodash-es` のコードの実行など（高）、`@tootallnate/once`（低）の3つです。
- **実害**：見本のサイトは、ニュースの入力に summernote を使っていて、CKEditor を読み込む画面がありません。CKEditor に切り替えたサイトでは、管理画面でスタッフが外から持ってきた内容を貼り付けたときに、働く可能性があります。
- **なぜ `npm audit fix` で直らないか**：このパッケージは、42版より後は更新されていません。CKEditor は、42版から `ckeditor5` という1つのパッケージに変わりました。`npm audit fix --force` の提案は、39.0.2 へ下げるものでした。

この回の `npm audit` は、最初に流したときに応答が返らず、打ち切りました。結果は、後から PowerShell で流し直したものです。

#### 対応の方法

所見1・2は、Apache の設定に書きます。全部のサイトに効くので、`/etc/httpd/conf.d/` の下に1つファイルを作るのが分かりやすいです。書いた後に、設定の確認と再読み込みをします。

```apache
# /etc/httpd/conf.d/security.conf
TraceEnable Off
ServerTokens Prod
ServerSignature Off
```

```bash
sudo apachectl configtest && sudo systemctl reload httpd
```

所見3は、サーバーの `.env` の `APP_NAME` を半角の英数字にし、日本語のサイト名は `SITE_NAME` に書いて、`php artisan config:cache` をやり直します（利用ガイド0章「サイトの名前」）。Cookie の名前が変わるので、ログイン中の人は、ログインし直しになります。

所見4は、手元で `composer update league/commonmark` を実行し、テストを通してから、`composer.lock` をサーバーへ送ります。

手順9の補足（静的ファイルのヘッダー）は、必要なら同じ Apache の設定に足します。

```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == 'on'"
```

Laravel を通る画面には、同じヘッダーが `SecurityHeaders` からも付きます。`always set` は上書きなので、二重にはなりません。

#### 対応した内容（2026-10-07）

| 所見 | 対応 |
|---|---|
| 1・2 | サーバーの側の作業。まだ対応していない |
| 3 | 表示用のサイト名を `SITE_NAME` に分けた（利用ガイド第3.3版）。サーバーの `.env` の書き換えは、サーバーの側の作業 |
| 4 | `composer update league/commonmark` で更新した。`composer audit` は報告なし |
| 5 | `@ckeditor/ckeditor5-build-classic` を外し、`ckeditor5`（48.5.2）に入れ替えた。`npm audit --omit=dev` は `found 0 vulnerabilities` |

手元の開発用のパッケージには、`npm audit`（`--omit=dev` なし）で `shell-quote` の報告が残っています。`concurrently` が、穴のある版（1.9.0）に固定して使っているためです。`concurrently` は最新の版でも同じ指定で、直った版がまだ出ていません。サーバーの画面には入らないので、次の版を待ちます。同じ報告にあった `source-map-js` は、`npm audit fix` で更新しました。

2026-10-08 に、`concurrently` を `package.json` から外しました。このプロジェクトのどのスクリプトからも呼んでいなかったためです。`npm audit`（`--omit=dev` なし）も `found 0 vulnerabilities` になりました。

#### この回で実際に流したコマンド

「手順」のコマンドは、読みやすいように整理してあります。この回に実際に流したものを、順番のまま残します。変えてあるのは、ベーシック認証のパスワードを伏せたことだけです。一時ファイルの置き場所の `$TEMP` は、Windows の Git Bash の一時ディレクトリです。

```bash
B="https://dev.neobit.jp"; A="dev:（パスワード）"

# 1回目：HTTPS への転送、認証なし、証明書、ヘッダー
curl -sS -o /dev/null -D - --max-time 15 http://dev.neobit.jp/ | head -8
for p in / /up /admin/login /mail/unsubscribe /build/manifest.json /storage/ /robots.txt /favicon.ico; do printf "%-28s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 $B$p)"; done
curl -sSv -o /dev/null --max-time 15 -u $A $B/ 2>&1 | grep -iE "SSL connection|subject:|issuer:|expire date|start date|TLSv|ALPN: server"
curl -sS -o /dev/null -D - --max-time 15 -u $A $B/

# 2回目：Cookie の属性、証明書と TLS の版、見えてはいけないファイル
curl -sS -o /dev/null -D - --max-time 15 -u $A $B/ | grep -i "^set-cookie" | sed -E 's/=[^;]{20,}/=…/'
echo | openssl s_client -connect dev.neobit.jp:443 -servername dev.neobit.jp 2>/dev/null | openssl x509 -noout -subject -issuer -dates
for v in tls1 tls1_1 tls1_2 tls1_3; do r=$(echo | openssl s_client -connect dev.neobit.jp:443 -servername dev.neobit.jp -$v 2>&1 | grep -c "Cipher is (NONE)\|no protocols available\|alert\|wrong version\|unsupported"); echo "$v: $([ "$r" = "0" ] && echo 接続できる || echo 拒否)"; done
for p in /.env /.env.example /.git/HEAD /.git/config /.gitignore /composer.json /composer.lock /package.json /artisan /CLAUDE.md /docs/security-issues.md /storage/logs/laravel.log /storage/app/private/ /vendor/autoload.php /config/app.php /bootstrap/cache/config.php /database/ /tools/table2rules.php /code/prefectures.csv /phpinfo.php /info.php /server-status /server-info /.htaccess /public/index.php /index.php /build/ /storage/ /backup.sql /dump.sql /adminer.php /phpmyadmin/; do printf "%-34s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code} %{size_download}B' --max-time 15 -u $A $B$p)"; done

# 3回目：証明書の対象、301 の転送先、開発用の入口、エラーの画面、メソッド、静的ファイル
echo | openssl s_client -connect dev.neobit.jp:443 -servername dev.neobit.jp 2>/dev/null | openssl x509 -noout -ext subjectAltName
for p in /storage/app/private/ /database/ /phpmyadmin/; do printf "%-24s → %s\n" "$p" "$(curl -sS -o /dev/null -w '%{redirect_url}' --max-time 15 -u $A $B$p)"; printf "%-24s   %s\n" "${p%/}" "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 -u $A $B${p%/})"; done
for p in /telescope /horizon /_debugbar/open /_ignition/health-check /log-viewer /pulse /livewire/livewire.js /api/user /_boost/browser-logs /clockwork /storage/app/public /.well-known/security.txt; do printf "%-28s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 -u $A $B$p)"; done
curl -sS --max-time 15 -u $A $B/no-such-page-xyz > "$TEMP/n404.html"; grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|APP_KEY|DB_PASSWORD|/home/|/var/www|ignition|Whoops" "$TEMP/n404.html"; grep -oE "<title>[^<]*</title>" "$TEMP/n404.html"
curl -sS -o "$TEMP/n405.html" -w '%{http_code}' --max-time 15 -u $A -X POST $B/login/verify/none; grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|/home/|/var/www|Whoops" "$TEMP/n405.html"
curl -sS -o "$TEMP/n500.html" -w '%{http_code}' --max-time 15 -u $A "$B/news?page[]=1&page[]=2"; grep -ciE "stack trace|vendor/laravel|Illuminate\\\\|/home/|/var/www|Whoops|SQLSTATE" "$TEMP/n500.html"
for m in TRACE OPTIONS PUT DELETE; do printf "%-8s %s\n" "$m" "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 -u $A -X $m $B/)"; done
curl -sS --max-time 15 -u $A $B/ | grep -oE '/build/assets/[^"]+' | head -1 > "$TEMP/asset.txt"; curl -sS -o /dev/null -D - --max-time 15 -u $A "$B$(cat $TEMP/asset.txt)" | grep -iE "^HTTP|x-frame|x-content|strict-transport|referrer|cache-control|content-type"

# 4回目：TRACE の中身、パッケージ
curl -sS --max-time 15 -u $A -X TRACE -H "X-Test: abc" $B/ | sed -E 's/(Authorization: Basic ).*/\1…（伏せた）/' | head -8
composer audit --no-interaction
npm audit --omit=dev          # 応答が返らず、打ち切った
composer audit --no-interaction --format=json
composer why league/commonmark
```

結果について、補足が3つあります。

- 1回目の証明書のコマンド（`curl -sSv … | grep …`）は、Windows の `curl` では証明書の行が出ず、`ALPN: server accepted http/1.1` の1行しか取れませんでした。証明書は、2回目の `openssl` で確かめ直しています。
- 3回目の開発用の入口の一覧には、種類の違うものが2つ混ざっています。`/storage/app/public` は `403`、`/.well-known/security.txt` は `404` でした。「手順」の6からは外しています。
- 無い URL への POST は、`405` ではなく `404` でした。「GET だけのルートへ POST を送ったとき」の画面は、確かめられていません。

Markdown の変換を使っていないことは、プロジェクトの `app/`・`resources/`・`routes/`・`config/` の下を、`Str::markdown`・`->markdown(`・`Markdown`・`inlineMarkdown`・`CommonMark` で検索して、当たりが無いことで確かめました。

#### この回で確かめていないこと

- 書き込みを伴う検査（「この確認で分かること・分からないこと」の「分からないこと」）
- プログラムの中で例外が起きたときの 500 の画面
- GET だけのルートへ POST を送ったときの 405 の画面
- ベーシック認証を外した状態での確認。公開のときは、手順2を除いて、もう一度行います
- 本番のサーバー。今回は開発用のデモサイトだけです
- 入れ替えた後の CKEditor の、画面での動作
- `docs/security-issues.md` に残っている課題（`Content-Security-Policy` など）
