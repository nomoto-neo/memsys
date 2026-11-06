# サイトの設置と設定

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

## サーバーへ送った後に実行するコマンド

ファイルをサーバーへ送った後、サイトのディレクトリで、上から順に要るものだけを実行します。実行するのは、デプロイ用のユーザーです。開発サーバーでは devapp で、root になる必要はありません。

| 順 | コマンド | 要るとき |
|---|---|---|
| 1 | `composer install --no-dev --optimize-autoloader` | `composer.json`・`composer.lock` を変えたとき |
| 2 | `npm ci` | `package.json`・`package-lock.json` を変えたとき |
| 3 | `npm run build` | `resources/js`・`resources/css`・`vite.config.js` を変えたとき、フレームワークを更新したとき。JS・CSS だけの差し替えは、cron の `neobit:rebuild` に任せられる（下の「画面と JS・CSS の作り直し」） |
| 4 | `php artisan migrate` | マイグレーションを足したとき |
| 5 | `php artisan config:cache` | 毎回。`.env` か `config/` を変えたときは必ず |
| 6 | `php artisan view:cache` | 毎回。apache はコンパイル済みの画面を作れないので、ここで全部作る。Blade だけの差し替えは、cron の `neobit:rebuild` に任せられる |
| 7 | `php artisan neobit:gatekeeper --export` | 毎回。入口の見張りの状態と画面を、今の設定で書き出し直す（[ip-block.md](ip-block.md)） |

- **`config:cache` は必須です**。サーバーでは、PHP-FPM を apache で動かし、`.env` は apache から読めないようにしています。`config:cache` は `.env` の値を取り込んだ `bootstrap/cache/config.php` を作り、Laravel はそれだけを読むようになるので、apache が `.env` を読めなくても画面も cron も動きます。
- **composer のコマンドの後は、`config:cache` をやり直します**。`composer install`・`composer dump-autoload`・`composer update` は、Laravel の決まり（`post-autoload-dump`）で `bootstrap/cache/config.php` を消します。やり直さないと、下の `config:clear` と同じく DB が SQLite になって止まります。
- **`config:clear` は実行しません**。`bootstrap/cache/config.php` が消えて設定の出どころが無くなり、DB が既定の SQLite になって、画面も cron（[scheduler.md](scheduler.md)）も動かなくなります。
- `.env` を変えただけでは効きません。`config:cache` をやり直したときに効きます。
- `bootstrap/cache/` は、apache が読めて書けない状態にしています。ディレクトリに setgid と既定の ACL（`default:user:apache:r-x`）を付けてあるので、中に作ったファイルは誰が作っても apache が読めます。作った後に権限を直す必要はありません。`ls -l` では 660 に見えますが、末尾の `+` が ACL の印で、中身は `getfacl` で確かめます。
- 画面が「The bootstrap/cache directory must be present and writable」のエラーで止まったときは、中のファイルが古くなっています。apache は書けないので自分では作り直せません。`php artisan config:cache` をやり直します。
- `storage/` の下は、全部を apache が書けるようにします。ログ、エラーの通知の間引きの記録（`storage/framework/cache/data`）、PDF の作業用（`storage/framework/mpdf`）、アップロードしたファイルと一時ファイル（`storage/app`）を置くためです。**コードとして動くものは `storage/` に置きません**。
- **コンパイル済みの画面は `bootstrap/cache/views` に置きます**（`config/view.php`）。PHP としてそのまま動くので、apache は読むだけにします。書けると、侵入されたときに裏口を埋められ、改ざん監視の cron（devapp）にも動かされます。
- **`view:cache` を忘れると、直した画面がエラーになります**（`.env` の `VIEW_CHECK_CACHE_TIMESTAMPS` が `true` のとき。サーバーでは `false` にし、cron の `neobit:rebuild` に作り直させます）。元のファイルのほうが新しいと、apache が作り直そうとして、書けずに止まります（`tempnam(): file created in the system's temporary directory`・`touch(): Utime failed` など）。知らせのメールも同じ理由で止まります。メールのテンプレートはファイルに書かず、メモリの上で展開するので、`view:cache` の対象ではありません。
- `APP_DEBUG=true` のときの Laravel のエラーの詳細の画面は、コンパイルできないので、簡単なエラーの表示になります。
- devapp が `storage/` の下に作ったファイル（artisan を実行したときのログなど）も、既定の ACL（`default:user:apache:rwx`）で apache が書けます。作った後に権限を直す必要はありません。
- 設定が効いているかは、画面を開いて確かめます。sudo を使えるときは `sudo -u apache php artisan config:show database.default` でも確かめられ、`mariadb` と出れば apache が読めています。devapp で同じコマンドを実行しても、devapp は `.env` を読めるので確かめになりません。
- 手元の開発環境は `.env` を直接読むので、`config:cache` は要りません。
- フレームワークを更新して、見本のサイトの `public/index.php`・`public/.htaccess` が変わったときは、それも案件のサーバーへ写します（[ip-block.md](ip-block.md)の入口の見張り、[public-files.md](public-files.md)）。
- WordPress を置くサイトは、`php artisan neobit:gatekeeper` で `WordPress の保護機能：` の行も確かめます（[wordpress.md](wordpress.md)）。`prepend` の段は、Laravel のページ（管理画面など）を1回開いてから確かめます。WordPress のページは Laravel を通らないので、確かめになりません。
- **改ざん監視を使っているサイトは、ファイルを送るときに鍵のファイル（既定は `.version`）も、中身を変えて送ります**（[file-watch.md](file-watch.md)）。送り忘れると、更新したファイルが改ざんとして知らされます。

## 画面と JS・CSS の作り直し（neobit:rebuild）

Blade・JS・CSS だけを差し替えるとき（デザイナーの直しなど）は、シェルで `view:cache`・`npm run build` を流さずに済みます。コードを更新する利用者（開発サーバーでは devapp）の cron が、毎分、元のファイルの変化を見つけて作り直します。コンパイル済みの画面と JS・CSS を書くのはその利用者だけで、apache には書かせません。

**デザイナーの手順**

1. Blade・JS・CSS を送る
2. 1分ほど待つ
3. 管理画面の「改ざん監視」で「承認する」を押す（[file-watch.md](file-watch.md)）

**サーバーの設定**（1回だけ。サーバーを管理する人）

- `/etc/cron.d/` の改ざん監視の行の下に、1行を足します。Laravel のスケジューラー（apache の cron）には入れません。

  ```
  *   * * * * devapp cd /var/www/memsys_dev && /usr/bin/php artisan neobit:rebuild > /dev/null
  ```

- `.env` に `VIEW_CHECK_CACHE_TIMESTAMPS=false` を書き、`config:cache` をやり直します。元の画面のファイルが新しくても、apache が作り直そうとせず、作り直すまでは前の画面を出すようになります。手元の開発環境は `true`（既定）のままにし、直したらすぐ画面に出します。
- cron の中で `npm` の場所が見つからないサーバーは、`.env` の `REBUILD_NPM` に絶対のパスを書きます（例：`/usr/bin/npm`）。
- `sudo bash set_permissions.sh` で、devapp が `bootstrap/cache/views`・`public`・`node_modules` に書けることを確かめます。

**動き**（`Neobit\Core\Operations\AssetRebuilder`）

- 元のファイルの一覧（パス・大きさ・日時）のしるしを、前に作り直したときのしるし（`bootstrap/cache/rebuild.json`）と比べます。足した・直した・消した・名前を変えた、のどれでも作り直します。
  - 画面：登録された画面の場所の全部（`resources/views`・フレームワークの `neobit::` の画面）
  - JS・CSS：`resources/js`・`resources/css`・フレームワークの `resources/js`・`resources/css`・`vite.config.js`
- 画面は、`bootstrap/cache/views_next` に全部をコンパイルしてから `bootstrap/cache/views` と入れ替えます。作っている間も、前の画面が出ます。元のファイルが無くなった画面のコンパイル済みファイルは、新しい置き場所に作られないので残りません。
- JS・CSS は、`public/build_next` にビルドしてから `public/build` と入れ替えます。作っている間も、前の JS・CSS が出ます。
- **失敗したときは、前のものを残します**。失敗はエラーのログに書くので、エラーの通知のメールで届きます（[error-notify.md](error-notify.md)）。元のファイルがまた変わるまでは作り直さないので、直して送り直します。
- `package.json`・`package-lock.json` が変わったときは、JS・CSS を作らずに「npm ci と npm run build を流してください」とエラーで知らせます。外からパッケージを取ってくる `npm ci` は、人が行います。
- 初めて動いたときは、作り直さずにしるしだけを記録します。
- ファイルを消すときは、使っているところ（`@include`・コントローラー・`vite.config.js`・`@vite()`）も一緒に直します。残っていると、作り直した後にその画面がエラーになるか、ビルドが失敗します。
- cron の無いサーバー（`CRON_ENABLED=false`）では動きません。上の「サーバーへ送った後に実行するコマンド」の `npm run build`・`view:cache` を流します。

## サーバーの権限（set_permissions.sh）

サーバーのファイルの持ち主・グループ・権限・setgid・ACL は、プロジェクトの直下の `set_permissions.sh` でそろえます。サーバーへ最初に置いたときと、権限が崩れたとき（画面が書き込みのエラーで止まったときなど）に、プロジェクトのルートで root で実行します。何度流しても同じ形になります。

```bash
sudo bash set_permissions.sh            # 実行するコマンドと、今の確かめの結果を並べるだけ
sudo bash set_permissions.sh --apply    # 実際に変える
```

| 場所 | 持ち主 | 作業のグループ | Web の利用者（ACL） | その他の人 |
|---|---|---|---|---|
| 全体（コード・`vendor` など） | `devapp:dev` | 読み書き | 読むだけ | なし |
| `bootstrap/cache/` | `devapp:dev` | 読み書き | 読むだけ（設定のキャッシュ、コンパイル済みの画面、改ざん監視の記録を、Web の側から書き換えられないようにする） | なし |
| `storage/` の下 | `apache:dev` | 読み書き | 読み書き | なし |
| `set_permissions.writable` に書いたディレクトリ | `apache:dev` | 読み書き | 読み書き | なし |
| `.env` と、`.env.` で始まる控え（`.env.example`・`.env.unused` を除く） | `devapp:dev` | 読み書き | 読めない | なし |

- ディレクトリは全部、setgid と既定の ACL を付けます。中に新しく作ったファイルも、誰が作っても同じ形になります。
- **`storage/` のほかに apache が書き込むディレクトリ**は、プロジェクトの直下の `set_permissions.writable` に、プロジェクトの直下からのパスを1行に1つ書きます（WordPress のアップロードの置き場所など。[wordpress.md](wordpress.md)）。`storage/` と同じ形にそろえ、無ければ作ります。`#` で始まる行と空の行は読み飛ばします。プロジェクトの外を変えないよう、`/` で始まるパス・`..` を含むパス・シンボリックリンクがあれば、何も変えずに止まります。案件ごとの一覧なので、`set_permissions.sh` とは別のファイルにしています。フレームワークを更新して `set_permissions.sh` を差し替えても、一覧は残ります。
- apache が書ける場所は、侵入されたときに書き換えられる場所です。要る場所だけを書きます。公開ディレクトリの中の場所は、書き込まれたファイルを URL で開かれるので、改ざん監視の見方（[file-watch.md](file-watch.md)）も合わせて決めます。
- 利用者の名前は `--owner`（持ち主）・`--group`（作業のグループ）・`--web-user`（Web の利用者）で変えられます。省けば `devapp`・`dev`・`apache` です。
- 次のときは、何も変えずに止まります：root でない、`artisan` が無い、利用者やグループが無い、`setfacl` が無い、SELinux が `Disabled` でない（このサーバーでは最初に Disabled にしているので、設定の漏れとして止める）。
- 次のときは、警告だけを出します：Web の利用者が作業のグループに入っている（グループで書けるコードを Web の側から書き換えられる）、PHP-FPM のプールが Web の利用者で動いていない。
- PHP-FPM のプールは、`/etc/php-fpm.d/` の設定の `user` で確かめます。`pm = ondemand` では暇な間ワーカーが止まっていて、プロセスからは分からないためです。ディレクトリと同じ名前のプール（`[memsys_dev]` か `memsys_dev.conf`）を、このサイトのプールとみなします。そのプールを、ディレクトリと同じ名前にしておきます。
- 最後に、Web の利用者の権限で、`.env` を読めない・`bootstrap/cache`・`bootstrap/cache/views` に書けない・`storage/logs` に書ける・`public/index.php` を読める・`app/` に書けない・`set_permissions.writable` に書いたディレクトリに書ける、を試して並べます。
- **改ざん監視を使っているサイトでは、`--apply` の前に `php artisan neobit:file-watch` を流し、「改ざんの疑い」と「判断待ち」が0件であることを確かめます**。`chown`・`chmod`・`setfacl` は、中身を変えなくても全部のファイルの ctime を新しくします。鍵のファイル（`.version`）の ctime も新しくなるので、改ざん監視から見ると `.version` を送ったのと同じになり、流した時刻の15分後より前に変わったファイルは、知らせ中のものも含めて全部、通常の更新として取り込まれます（[file-watch.md](file-watch.md)）。ほかのファイルは中身が同じなので、知らせません。
- 作業する人は、作業のグループ（`dev`）に入れた自分の利用者で作業します。抜けるときは、グループから外します。
- PHP-FPM のプールは、1つのサーバーに1つのサイトなら apache のままでかまいません。同じサーバーに複数のサイトを置くときは、サイトごとにプールの利用者を分けると、1つのサイトから侵入されても、ほかのサイトのファイルを読まれません。そのときは `--web-user` にそのサイトのプールの利用者を渡します。

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
