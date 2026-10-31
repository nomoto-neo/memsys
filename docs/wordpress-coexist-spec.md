# 仕様メモ：WordPress との共存（wordpress-coexist）

- 実装プロンプト：`docs/prompt/wordpress-coexist.md`
- 実装完了日：2026-10-29
- 計画書の段階4-13 の B。A（入口の見張り）は `docs/entry-gate-spec.md`

## A. 概要

**目的・解決する課題**

既存の WordPress のサイトに Laravel を入れるとき、狙われやすい WordPress を後ろに下げ、自衛の機能が効く Laravel を前に出す。WordPress には決まったコーナーだけを受け持たせ、`public/.htaccess` で URL ごとに振り分ける。振り分けた WordPress の側にも、入口の見張り（IP ブロック・メンテナンスの画面・アクセス制限）と改ざん監視を効かせる。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | 判断（`Gate`）に WordPress の URL と「管理画面の許す IP はサイトの制限でも許す」、状態（`GateState`）に `wordpress` と見張りが掛かっているかの確かめ、`app:gate` に WordPress の行、改ざん監視（`FileWatcher`）に WordPress のアップロードの置き場所と見ない場所、設定 `neobit.wordpress`（既定は null）、アクセス制限の画面の説明 |
| 見本のサイト | `config/neobit.php` に `wordpress` の見本（コメント）、`.env.example` に `WORDPRESS_GATE` |
| 案件のサーバー | WordPress を置く案件だけ。`.env` の `WORDPRESS_GATE` と、PHP-FPM のプールの設定か `.user.ini` か `wp-config.php` |
| WordPress を置かない案件 | 管理画面の許す IP が、サイトの制限でも通るようになることだけ |

## B. 実装仕様

**設定**

| 置き場所 | 値 | 決まるもの |
|---|---|---|
| `.env` の `WORDPRESS_GATE` | `prepend`（`auto_prepend_file`）か `wp-config`（`wp-config.php` の頭の `require`） | サーバー |
| `config/neobit.php` の `wordpress` | `gate`（`env('WORDPRESS_GATE')`）・`admin_paths`・`open_paths`・`hidden_paths`・`wp_config`・`upload_path`・`watch_exclude` | 案件 |

- パッケージの既定は `'wordpress' => null`。WordPress を置く案件だけが節を書き、それが「WordPress を置く」の印になる。
- 見本の値：`admin_paths` は `wp-admin`・`wp-admin/*`・`wp-login.php`、`open_paths` は `wp-admin/admin-ajax.php`・`wp-cron.php`（`wp-admin/admin-post.php` はコメント）、`hidden_paths` は `xmlrpc.php`、`upload_path` は `public/wp-content/uploads`、`watch_exclude` は `public/wp-content/cache`。

**状態と判断**

- `GateState::build()` に `wordpress`：`['admin' => admin_paths, 'open' => open_paths, 'hidden' => hidden_paths]`。節が無いか `gate` が空なら null。
- `Gate::decide()` の順：止めている IP → 404、`hidden` → 404、`open` → 通す、Laravel の管理画面か `admin` → 管理画面の制限、サイトの制限を掛けない URL かサイトの許す IP か管理画面の許す IP → 通す、それ以外 → 503。
- 管理画面の許す IP（画面と `.env` の `ADMIN_ALLOWED_IPS`）は、管理画面の制限を掛けていなくても、サイトの制限で通す（`Gate::opensSite()`）。Laravel のミドルウェア（`RestrictSiteAccess`）も同じ判断を使うので、Laravel の画面にも効く。
- WordPress の URL は `.htaccess` が Laravel より先に回すので、Laravel のミドルウェアには届かない。判断は見張りだけが行う。

**見張りが掛かっているかの確かめ**（`GateState::watchWordPress()`。`RejectBlockedIp` が毎回のアクセスで呼ぶ）

- 節があって `gate` が空：critical のログ「.env の WORDPRESS_GATE がありません」。キャッシュの `Cache::add` で1時間に1回まで。
- `gate` が `prepend`：`ini_get('auto_prepend_file')` と `gate.php` を `realpath()` で比べる。結果と時刻をキャッシュ（`neobit-gate-prepend`）に控える。書くのは結果が変わったときと1時間ごとだけ。掛かっていない状態に変わったときに、critical のログを1回。
- `app:gate`：WordPress の行を出す。`prepend` は控えを出し、掛かっていなければ失敗。控えが無いとき（送った直後でまだ Web の要求が無い）は警告だけにする（0章の手順の `app:gate --export` を失敗に見せないため）。`wp-config` は `wp-config.php` に `gate.php` を読む `require` があるかを見て、無ければ失敗。コメントにした行は数えない。節があって `gate` が空なら失敗。値が正しくなければ失敗。

**改ざん監視**

- `upload_path` は、`storage/app/public` と同じ扱い。PHP として動くものと許可されていない拡張子のもの（許す拡張子も同じ）をすぐ知らせる。`.htaccess`・`.user.ini` だけは基準と比べる（プラグインが置く正しいものは、鍵と一緒に置くか `--accept` で取り込む）。この決まりは、Laravel の `storage/app/public` も同じにそろえた。
- `upload_path` と `watch_exclude` は、基準と比べる走査から外す（`upload_path` の `.htaccess`・`.user.ini` を除く）。前の基準に入っていたファイルは、消えたものにせず黙って基準から外す。
- `watch_exclude` は何も見ない。キャッシュのプラグインが書く場所など、何が置かれるかを決められない場所だけに使う。

**画面**

- アクセス制限の画面に、「管理画面を開けるIPアドレスの人も、訪問者画面を開けます。」と「下のIPアドレスの人は、チェックが無くても、サイトのアクセス制限の間も訪問者画面を開けます。」を足した。

## C. 前提・依存

- 入口の見張り（A。`gate.php`・`Gate`・`GateState`）
- WordPress は `public/` の直下に置き、入口は `public/wordpress.php`。`wp-config.php` はプロジェクトの直下。`public/.htaccess` の振り分けは人が書く（利用ガイドの31章の見本）
- `prepend` の段は、PHP-FPM のプールをサイトごとに分けていること（`php_admin_value` はプールの全部に効く）
- `.user.ini` は `user_ini.cache_ttl`（既定5分）の後に効く
- WordPress のアップロードの置き場所とキャッシュの場所を、apache が書けること。`set_permissions.writable` に書いて `set_permissions.sh` でそろえる（2026-10-29 に開発サーバーで、書いていないと画像の保存が「ディレクトリを作成できません」で止まった）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| 見張りを掛ける段の置き場所 | `.env`（サーバーで決まる） | `config/neobit.php`：同じ案件でも開発サーバーと本番で段が変わり得る |
| WordPress を置くかの印 | `config/neobit.php` に `wordpress` の節があるか | `WORDPRESS_GATE` が空かどうか：書き忘れと「置かない」の見分けが付かず、守られないまま気付けない |
| 書き忘れの知らせ | critical のログ（1時間に1回）と `app:gate` の失敗 | 何もしない：WordPress が守られないまま気付けない |
| WordPress の URL の決まり | 設定の `admin_paths`・`open_paths`・`hidden_paths` | 決め打ち：ログインの URL を変えるプラグインや、`admin-post.php` を表で使う案件に合わせられない |
| 使わない入口の名前 | `hidden_paths`（404 で無いことにする） | `closed_paths`：意味が通じにくい。`blocked`・`deny`：IP ブロックや 403 と紛れる |
| `wp-json` | どこにも書かず、表の URL と同じくサイトの制限に任せる | `open_paths`：公開前のサイトを隠しているときも、公開済みの記事を API で読める。`admin_paths`：管理画面の制限を掛けると、表のフォーム（Contact Form 7）が送れない |
| メンテナンス中の記事の保存 | 管理画面の許す IP を、サイトの制限でも許す（Laravel の画面にも効く） | 運用でサイトの許す IP にも書く：入れ忘れると保存できない。`wp-json` だけ特別に通す：決まりが増える |
| 管理画面の許す IP を見るとき | 管理画面の制限のチェックに関係なく、一覧を見る | チェックがあるときだけ：チェックが無いと、記事を書く人をサイトの許す IP にも書く必要が戻る |
| `prepend` の確かめ | Web の要求で `ini_get()` を見て、キャッシュに控える | コマンドで見る：コマンドの PHP の設定は Web と別で、分からない |
| WordPress の 404 の数え | 数えない | 数える：見張りが応答を見る作りになり、大きくなる |
| WordPress のアップロード | `storage/app/public` と同じ扱い | 基準と比べる：画像を上げるたびに改ざんとして知らせる |
| アップロードの置き場所の `.htaccess` | Laravel と WordPress の両方で、基準と比べる | すぐ知らせる危ないファイルのまま：プラグインが置く正しいものを知らせ続ける。`watch_exclude` で外す：その場所に置かれた JavaScript や PHP にも気付けなくなる。決まったファイルの一覧を書かせる：見る手間のわりに得るものが少ない |
| `.htaccess` の振り分け | 人が書く（利用ガイドの見本） | 設定から書き出す：今回は後に回した（計画書の案の1） |
| 見本のサイトの WordPress | 仮の WordPress と `.htaccess` の決まりはコミットしない | コミットする：WordPress を使わない案件に、要らない決まりを持ち込む |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | 管理画面の許す IP は、管理画面の制限のチェックが無くても、サイトの制限で通る。古い IP が一覧に残っていると、その IP もメンテナンス中のサイトに入れる。画面に説明を書いた |
| 中 | `wp-config` の段では、プラグインの `.php` を直に開かれたときに見張りが掛からない。`.htaccess` で `wp-content/` の下の `.php` を閉じて補う（WordPress のファイルを通す行より上に置く）。閉じると動かなくなるプラグインが無いかは案件ごとに確かめる |
| 中 | WordPress の画面と見張りの応答には、`SecurityHeaders` のヘッダーが付かない。Apache の HTTPS の VirtualHost に `Header always set` で付ける（利用ガイドの見本）。`always` を付けないと、Laravel と WordPress の画面で二重になる |
| 中 | `watch_exclude` の場所（キャッシュ）は何も見ないので、キャッシュの HTML・JavaScript の書き換えに気付けない。キャッシュはどこに置いても（Redis などのメモリでも）PHP から書けるので、改ざん監視では見られない。外から監視するサービスと組み合わせる |
| 低 | アップロードの置き場所の悪い `.htaccess` は、15分待ってから知らせる。Apache の `AllowOverride AuthConfig Limit` で、そもそも PHP を動かす設定を効かなくできる（利用ガイドの31章） |
| 低 | WordPress の 404 は数えないので、WordPress のコーナーの下だけを叩く攻撃は、自動では止まらない |
| 低 | リダイレクト（28章）は WordPress のコーナーの下には効かない |

## F. セキュリティ・権限・データ整合性の注意

- `public/.htaccess` は apache が書けない権限のままにする。WordPress がパーマリンクの保存で書き換えようとするため。
- `DISALLOW_FILE_MODS` で、WordPress の管理画面からのコードの書き換えを止める。止めない案件では、apache がコードを書ける分、守りが弱まる。お客さんが画面から更新する案件は、これを外して「SSH SFTP Updater Support」を使い、SSH でつないだ利用者の権限で書く（利用ガイドの31章）。apache はコードに書けないままで、変わったファイルは改ざん監視の承認で取り込む。
- `open_paths` に書いた URL は、サイトの制限も管理画面の制限も掛からない。書き込みに WordPress のログインが要るものだけを書く。
- 見張りが読む状態のファイルの書き換えで止める向きにされる心配は、A と同じ（`docs/entry-gate-spec.md` の F）。

## G. テスト状況

**実施済み**

- `tests/Unit/GateTest.php`：管理画面の許す IP はサイトの制限でも通る（管理画面の制限が無くても、一覧の IP だけ）。WordPress の `hidden` は誰にも 404、`open` は制限なしで止めている IP だけ 404、管理画面とログインは管理画面の制限だけ、記事と `wp-json` はサイトの制限で管理画面の許す IP は通る、WordPress を置かなければふつうの URL
- `tests/Feature/GateStateTest.php`：段があれば WordPress の決まりを書き出し、見張りを別の PHP で動かして `xmlrpc.php`・`wp-login.php` が Laravel と同じ 404、`wp-cron.php` と管理画面の許す IP の `wp-admin`・`wp-json` は通る。段が空なら書き出さない。書き忘れは1時間に1回の critical と `app:gate` の失敗。置かない案件では何も言わない。`prepend` は Web で見るまで「まだ確かめていません」の警告（失敗にしない）、外れていれば1回だけ critical、掛かっていれば時刻を出す。`wp-config` は `require` の有無を見て、コメントにした行は数えない
- `tests/Feature/AccessRestrictionTest.php`：Laravel の側でも、管理画面の許す IP（画面と `.env`）はメンテナンス中のサイトを開ける
- `tests/Feature/FileWatchTest.php`：WordPress のアップロードの画像とキャッシュは知らせず、前の基準のものも消えたものにしない。アップロードの `.php` はすぐ知らせる。テーマの書き換えは知らせる。アップロードの置き場所の `.htaccess` は、鍵と一緒なら通常の更新、鍵の無い追加と書き換えは両方の置き場所で改ざんとして知らせる。前の版の基準からは、最初の回だけ知らせずに取り込む
- 全体で 513件（成功 512件、飛ばし 1件）

**開発サーバーで確かめたこと**（2026-10-29〜30。dev.neobit.jp。仮の WordPress に `/column/`・`/topics/`・`/info/`。止める IP には、サーバーの中から `curl --resolve dev.neobit.jp:443:127.0.0.1` で開いた 127.0.0.1 を使った）

- 段の書き忘れ：`wordpress` の節を書いて `WORDPRESS_GATE` が空のとき、`app:gate` が知らせて失敗で終わる
- `prepend`：PHP-FPM のプールの設定に `php_admin_value[auto_prepend_file]` を書き、`.env` に `WORDPRESS_GATE=prepend`。管理画面を1回開いた後の `app:gate` で、掛かっていることを確かめた。開く前は警告だけ
- IP ブロック：127.0.0.1 を止めると、`/column/1/`・`/wp-login.php`・`/xmlrpc.php` が全部 404。WordPress のヘッダー（`Expires: 1984` など）が無く、WordPress を動かす前に見張りが返している
- 解除した後：`/column/1/` はサイトの制限で 503、`/wp-login.php` は管理画面の扱いで 200、`/xmlrpc.php` はいつも隠す URL で 404
- アップロード：WordPress で画像を上げても、改ざん監視は知らせない。アップロードの置き場所は、`set_permissions.writable` に書いて `set_permissions.sh --apply` でそろえるまで apache が書けず、画像の保存が「ディレクトリを作成できません」で止まった
- アップロードの置き場所の `.htaccess`：`.version` の期間に置いたものは通常の更新として取り込み、期間の外の書き換えは15分後に「変わったファイル」として知らせた。中身を戻すと知らせが止まる
- `set_permissions.sh --apply` は、全部のファイルの ctime を新しくするので、改ざん監視から見ると `.version` を送ったのと同じになる（利用ガイドの0章・29章に書いた）
- ヘッダー：443 の VirtualHost に `Header always set` で4つを書くと、見張りの 503・404、Laravel の画面、WordPress の画面のどれにも1行ずつ付く。`always` を付けない `Header set` では、Laravel と WordPress の画面で2行になった
- 管理画面に入れる人：サイトの制限を掛け、管理画面の制限は掛けていない状態で、管理画面の許す IP だけに入っている回線から、ブロックエディタで記事を保存できた（保存が通る `wp-json` は、表の URL と同じくサイトの制限が掛かる）

## H. 今後の改善提案・TODO

- 計画書の案の1：WordPress に回す URL を設定に書き、固定ページやリダイレクトで重なる URL を登録できないようにする。設定から `.htaccess` の決まりを書き出すコマンド。`/wp-sitemap.xml` と Laravel のサイトマップの分け方
- 案件のサイトで、パーマリンクの設定と、見張りを掛ける段と、`DISALLOW_FILE_MODS` の取り決めを確かめる（計画書の4-13 の「実装の前に、案件のサイトで確かめること」）
