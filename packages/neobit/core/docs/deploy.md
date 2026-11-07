# サーバーへの設置と更新

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

cron を使わなくても、シェルで `php artisan neobit:rebuild` を流せば、`view:cache` と `npm run build` を個別に流さずに済みます（初めての1回と、`package.json` が変わったときを除く）。

### デザイナーの手順

1. Blade・JS・CSS を送る
2. 1分ほど待つ
3. 管理画面の「改ざん監視」で「承認する」を押す（[file-watch.md](file-watch.md)）

### サーバーの設定（1回だけ。サーバーを管理する人）

- `/etc/cron.d/` の改ざん監視の行の下に、1行を足します。Laravel のスケジューラー（apache の cron）には入れません。

  ```
  *   * * * * devapp cd /var/www/memsys_dev && /usr/bin/php artisan neobit:rebuild > /dev/null
  ```

- `.env` に `VIEW_CHECK_CACHE_TIMESTAMPS=false` を書き、`config:cache` をやり直します。元の画面のファイルが新しくても、apache が作り直そうとせず、作り直すまでは前の画面を出すようになります。手元の開発環境は `true`（既定）のままにし、直したらすぐ画面に出します。
- cron の中で `npm` の場所が見つからないサーバーは、`.env` の `REBUILD_NPM` に絶対のパスを書きます（例：`/usr/bin/npm`）。
- `sudo bash set_permissions.sh` で、devapp が `bootstrap/cache/views`・`public`・`node_modules` に書けることを確かめます。

### 動き（`Neobit\Core\Operations\AssetRebuilder`）

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
