# 公開ディレクトリの HTML と古い PHP（PublicFiles）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Http/PublicFiles.php`（冒頭のコメント）・`src/Http/Controllers/PublicFileController.php`、入口の `public/index.php`・`public/.htaccess`

デザイナーが作った `.html` や、残しておく古い作りの `.php`（`contact/index.php` など）を公開ディレクトリに置くと、Laravel を通してから返します。IP ブロック・アクセス制限・リダイレクト・セキュリティのヘッダーを、これらのページにも掛けるためです。CSS・JS・画像は、今までどおり Apache が直接返します。

**返し方**

- Laravel のルートが先です。どのルートにも当たらないときにだけ、公開ディレクトリのファイルを探します。見本のサイトの固定ページ（`/{slug}`）のように、どの URL も受けるルートは、自分のページが無ければ `PublicFileController` に渡します（`App\Http\Controllers\PageController::show()`）。
- `.html`・`.htm` は、中身をそのまま返します。`Content-Type` に charset は付けず、ページの meta のとおりに読ませます（Shift_JIS のページのため）。変わっていなければ 304 を返します。
- ディレクトリの URL（`/about/`）は、`index.html`・`index.htm`・`index.php` の順に探します。末尾の `/` が無ければ（`/about`）、`/about/` へ 301 で移します。ページの中の相対のリンクを正しく解くためです。リダイレクトのミドルウェアも、実在するディレクトリの URL では末尾の `/` を外しません。
- `.php` は、Laravel の全体のミドルウェアを通った後、入口の `index.php` が、Laravel の付けたヘッダーを送ってから、**グローバルの場所で** `require` します。`global $db;` などのグローバル変数を当てにした書き方も、そのまま動きます。作業のディレクトリと `$_SERVER['PHP_SELF']`・`SCRIPT_NAME`・`SCRIPT_FILENAME` は、Apache が直接動かしたときと同じく、そのファイルになります。
- 返さないもの：公開ディレクトリの外、`.` で始まる名前、入口の `index.php`、`storage/`（公開のアップロードの置き場所）と `build/` の下、管理画面の URL の下、Laravel の本体を公開ディレクトリの中に置くサーバーでは本体の中。どれも 404 です。

**古い `.php` を置くときの決まり**

- `exit` と `die` は `return` に書き換えます。ファイルの一番上の階層の `return` は、そのファイルだけを終えます。残っていても、ヘッダーは送った後なので、守りは掛かったままです。
- Laravel のセッション・CSRF の確かめ・入力をそろえる処理は通りません。`$_POST`・`$_GET` は送られたままの値で、セッションは PHP の `session_start()` をそのまま使えます。
- 警告を例外に変える Laravel の仕組みは外してから動かします。古い作りのファイルは、警告を出しながら動くことが多いためです。
- ファイルを書き出すもの（Smarty の `compile_dir`・`cache_dir`、ログ、添付の一時ファイルなど）は、`storage/app/` の下にコーナーごとのディレクトリ（`storage/app/legacy/contact/` など）を作り、そこへ書き出すように設定を変えます。Web の利用者が書けるのは `storage/` の下（と `set_permissions.writable` に書いた場所）だけで、公開ディレクトリの中は `set_permissions.sh` を流すと読むだけに戻るためです。古い PHP の書き出し先を公開ディレクトリの中から動かせないときだけ、その場所を `set_permissions.writable` に書きます。公開ディレクトリの中に書ける場所を作ると、書き込んだファイルを URL で開かれるおそれもあります。
- Laravel 一式を公開ディレクトリの外に置く形では、`storage/` は URL から届かないので、`.htaccess` の閲覧の保護は要りません。ただし `storage/app/public/` は `public/storage` から公開されるので、ここには書き出しません。
- 置き換えられるものは、フレームワークのコーナーに作り直します。これは、作り直すまでの間も守りを掛けておくための仕組みです。

**新しいプロジェクトの入口**

`public/index.php` の最後は、`$app->handleRequest(...)` ではなく、次の形にします。`public/.htaccess` は、`.html`・`.htm`・`.php` とディレクトリの URL も `index.php` に回す決まりにします（見本のサイトのものを写します）。

```php
// 公開ディレクトリの古い.phpに当たったときは、グローバルの場所で動かす（Neobit\Core\Http\PublicFiles）
$script = PublicFiles::handle($app, Request::capture());
if ($script !== null) {
    require $script;
}
```
