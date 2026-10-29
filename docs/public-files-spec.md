# 仕様メモ：公開ディレクトリの HTML と古い PHP（public-files）

- 実装プロンプト：`docs/prompt/public-files.md`
- 実装完了日：2026-10-29
- 計画書の段階4-12

## A. 概要

**目的・解決する課題**

既存の取引先のサイトには、デザイナーが作った `.html` や、すぐには作り直せない古い作りの `.php`（`contact/index.php` など）が公開ディレクトリにある。Laravel の標準の `.htaccess` は実在するファイルを Apache がそのまま返すので、これらのページには IP ブロック・アクセス制限・リダイレクト・セキュリティのヘッダーが掛からなかった。これらも Laravel の全体のミドルウェアを通してから返し、サイトを守る仕組みが全部のページに掛かるようにする。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | `PublicFiles`（探し方と入口の処理）、`PublicFileController`（フォールバックのルート `public-files`）、`PublicScriptResponse`（古い `.php` を動かす目印の応答）、`ApplyRedirects`（実在するディレクトリの URL では末尾の `/` を外さない）、`RedirectChecker::opensPage()`（フォールバックのルートに当たったときは、公開ディレクトリにファイルがあるかで決める） |
| 見本のサイト | `public/index.php`（最後を `PublicFiles::handle()` に）、`public/.htaccess`（`.html`・`.htm`・`.php` とディレクトリの URL も `index.php` へ）、`PageController::show()`（固定ページが無ければ `PublicFileController` へ渡す） |
| サーバー | `public/index.php`・`public/.htaccess` とフレームワークのファイルを送る |

## B. 実装仕様

**流れ**

1. `public/.htaccess` が、`index.php` 以外の `.html`・`.htm`・`.php`、ディレクトリの URL、実在しない URL を `index.php` に回す。CSS・JS・画像などの実在するファイルは Apache がそのまま返す
2. `index.php` が `PublicFiles::handle()` を呼ぶ。Laravel が全体のミドルウェア（IP ブロック・セキュリティのヘッダー・アクセス制限・リダイレクト）を通す
3. どのルートにも当たらなければ、フォールバックのルート（`Route::any('{path}')->fallback()`、web のグループの外）が `PublicFileController` で公開ディレクトリのファイルを探す
4. `.html`・`.htm` は中身を返す。`.php` は `PublicScriptResponse` を返し、`handle()` がヘッダーを送って後片付け（`terminate`）を済ませ、動かす準備をしてからパスを返す。`index.php` がそれをグローバルの場所で `require` する

**探し方**（`PublicFiles::file()`・`isDirectory()`）

| URL | 返すもの |
|---|---|
| `/page.html`・`/page.htm` | そのファイル |
| `/legacy/form.php` | そのファイルを動かす |
| `/about/` | `about/` の中の `index.html`・`index.htm`・`index.php` の順に、最初にあるもの |
| `/about`（ディレクトリ） | `/about/` へ 301（クエリは引き継ぐ） |
| 上に当たらないもの | 404 |

返さないもの（どれも 404）

- 公開ディレクトリの外（`realpath()` で確かめる。シンボリックリンクで外へ出るものも）と、`.` で始まる名前（`..` を含む）
- 入口の `index.php`
- `storage/`（公開のアップロードの置き場所）と `build/` の下
- 管理画面の URL（設定の `neobit.admin_path`）の下
- Laravel の本体を公開ディレクトリの中に置くサーバー（置き方 B の `app/system/`）では、本体の中
- `.html`・`.htm`・`.php` 以外の拡張子

**HTML の応答**

- `Content-Type: text/html`。charset は付けない（Symfony が足す charset を、応答の `prepare()` で外す）。Shift_JIS のページも、ページの meta のとおりに読ませるため
- `Last-Modified` はファイルの更新の日時。`If-Modified-Since` が同じか新しければ 304

**古い `.php` を動かす前に整えること**（`PublicFiles::handle()`）

- Laravel の付けたヘッダーを送る。`Content-Type` は外し、PHP と古い `.php` に任せる（Apache が直接動かしていたときと同じ）
- Laravel の後片付け（`terminate`）を、動かす前に済ませる。古い `.php` の `exit` で後片付けが飛ばないようにするため
- 警告を例外に変える Laravel のエラーの受け手と例外の受け手を外し、`error_reporting` を php.ini の値に戻す
- 作業のディレクトリをそのファイルのディレクトリにし、`$_SERVER['SCRIPT_FILENAME']`・`SCRIPT_NAME`・`PHP_SELF` をそのファイルにする
- `$_GET`・`$_POST` は Laravel がそろえる前のまま（Laravel は自分の `Request` だけをそろえる）

**リダイレクトの検証**

管理画面の「リダイレクト」で、新しい URL に開ける画面があるかを確かめる `RedirectChecker::opensPage()` は、フォールバックのルートがどの URL にも当たるので、フォールバックのルートに当たったときは、公開ディレクトリに返すファイルかディレクトリがあるかで決める。

## C. 前提・依存

- Apache の `mod_rewrite`。`.htaccess` の決まりは、Laravel の標準のものに足す形
- 見本のサイトのように、どの URL も受けるルート（固定ページの `/{slug}`）があるときは、そのコントローラーが自分のページが無いときに `PublicFileController` へ渡す必要がある。渡さないと、1段の URL（`/about`）がフォールバックのルートまで届かない
- 管理画面の URL は設定の `neobit.admin_path`

## D. 設計判断とトレードオフ

| 項目 | 決めたこと | ほかの案と、選ばなかった理由 |
|---|---|---|
| 置き場所 | 今までどおり公開ディレクトリの下 | `resources/static/` などへ移す：既存の案件のファイルを動かす手間がかかり、デザイナーの FTP の手順も変わる |
| 順番 | Laravel のルートが先 | ファイルが先：フレームワークの画面の URL を、置いたファイルで乗っ取れてしまう |
| 通すミドルウェア | 全体のミドルウェアだけ（web のグループは通さない） | web のグループも通す：古い `.php` への POST が CSRF の確かめで 419 になり、`$_POST` を読む古いフォームが動かない |
| 古い `.php` の動かし方 | 入口の `index.php` が、グローバルの場所で `require` する | コントローラーの中で `include`：ファイルの一番上の変数がメソッドの中の変数になり、`global $db;` が動かない。`routes/web.php` や `index.php` の頭で `include`：ミドルウェアより前に動き、守りが掛からない |
| `exit` | `return` に書き換えてもらう。残っていても、ヘッダーと後片付けは済ませてある | `try`・`catch` で拾う：`exit` は例外ではないので拾えない |
| 警告の扱い | Laravel のエラーの受け手を外して動かす | そのまま：古いファイルの未定義の変数の警告などが例外になり、ページが止まる |
| charset | 付けない | Laravel の標準（UTF-8 を足す）：Shift_JIS のページが文字化けする |
| ディレクトリの URL の末尾の `/` | 実在するディレクトリでは外さず、無ければ付ける | 今までどおり外す：`/about/` の HTML の中の相対のリンク（`img/a.png`）が `/img/a.png` を指してしまう |
| `storage/`・`build/` | 中の `.php`・`.html` を返さない | 返す：アップロードの置き場所に置かれた `.php` を動かしてしまう。Apache が直接返していたときより守りが弱くなる |

## E. 課題・懸念点

| 重さ | 内容 |
|---|---|
| 中 | 古い `.php` の中の `exit` は、Laravel の後片付けの後なので守りには響かないが、`exit` の後ろに Laravel の処理は無いので、出力の途中で終わったページはそのまま届く |
| 中 | 古い `.php` が致命的なエラーで止まると、Laravel の終わりの受け手（`register_shutdown_function`）が動き、エラーの画面を足して、ログとエラーの通知に残す。外せないので、そのままにしている |
| 低 | 入口の `index.php` の変数（`$app`・`$script`・`$maintenance`）は、古い `.php` からも見える。同じ名前の変数を使う古いファイルでは、上書きされるか、思わぬ値が見える |
| 低 | `.html` は1回ごとに Laravel を起動する分、Apache が直接返していたときより遅い。304 で、2回目からの転送は減らしている |
| 低 | `/storage/` の下は、Laravel のファイルを渡すルート（`storage/{path}`）が先に受け、見つからなければ 403 になる。フォールバックのルートには届かない |

## F. セキュリティ・権限・データ整合性の注意

- 返すのは公開ディレクトリの中だけ。`realpath()` で外へ出るものを断り、`.` で始まる名前（`.env`・`.git`・`..`）は見ない
- `storage/` と `build/` の下の `.php`・`.html` は、Apache が直接返していたときと違い、返さない。アップロードされたファイルが動かないようにするため
- 管理画面の URL の下は、Laravel のルートだけ。置いたファイルで管理画面の URL を作れないようにするため
- 古い `.php` は CSRF の確かめを通らない。古いフォームの守りは、そのファイル自身のものだけ。置き換えられるものは、フレームワークのコーナーに作り直す

## G. テスト状況

`tests/Feature/PublicFilesTest.php`（6件）。一時のディレクトリを公開ディレクトリにして試す。

- HTML を中身のまま返し、`Content-Type` は `text/html`（charset なし）、全体のミドルウェアのヘッダーが付く。`If-Modified-Since` で 304
- ディレクトリの URL：`/about?x=1` は `/about/?x=1` へ 301、`/about/` は末尾の `/` を外さずに `index.html` を返す。ディレクトリでなければ、今までどおり末尾の `/` を外す
- 公開ディレクトリに `news/` があっても、`/news` は Laravel のルート
- `.php` は GET でも POST でも（CSRF で断らず）目印の応答になる。ディレクトリの URL でも `index.php` を動かす
- 返さないもの：`/index.php`・`/build/page.html`・`/.hidden/page.html`・管理画面の URL の下・`.txt`・無いファイル・`..` で外へ出るもの。`storage/` の下は `file()` が `null`
- 入口の処理：古い `.php` のパスを返し、作業のディレクトリと `$_SERVER['PHP_SELF']`・`SCRIPT_FILENAME` をそのファイルにする。HTML は送って `null` を返す

`.htaccess` の決まりと、グローバルの場所で動くことは、Apache を通さないと試せないので、サーバーで確かめる。

## H. 今後の改善提案・TODO

- サーバーで確かめること：`/about`・`/about/`・`.html`・古い `.php` のフォームの送信・`/storage/` の画像・`/build/` の CSS が、どれも期待どおりに返ること
- WordPress との共存は、計画書の段階4-13で別に作る
- 古い `.php` の利用が分かったら、作り直すコーナーの候補として一覧にする
