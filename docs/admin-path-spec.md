# 仕様メモ：管理画面の URL を設定で決める（admin-path）

- 実装プロンプト：`docs/prompt/admin-path.md`
- 実装完了日：2026-10-27

## A. 概要

**目的・解決する課題**

`/admin` は、URL を順に叩く攻撃で真っ先に狙われる。管理画面の頭の URL を設定で決める形にし、既定を `/app/admin` にした。あわせて、robots.txt から管理画面を外し（隠した URL を教えないため）、Laravel を公開ディレクトリの外に置けないサーバーでの置き方を文書にした。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | 設定の `admin_path`（既定 `app/admin`）、`RestrictSiteAccess` が管理画面を自分で外す、新しいミドルウェア `NoIndexHeader`、`robots_disallow` の既定を空に |
| 見本のサイト | `routes/web.php` の管理画面のグループの `prefix` と `NoIndexHeader`、`bootstrap/app.php` の `except()` から管理画面を外した、`robots_disallow` から `/admin` を外した、コメントの中の URL |
| テスト | `/admin/…` の文字を `/app/admin/…` に直した。slug の予約の名前を `admin` から `app` に直した。新しく `AdminPathTest` |
| サーバー | 管理画面の URL が `/app/admin` に変わる。ベーシック認証の VirtualHost の決まりを直す |

## B. 実装仕様

**設定**（`config/neobit.php`）

- `admin_path`：管理画面の頭の URL。先頭と末尾の `/` は付けない。フレームワークの既定も `app/admin`。

**使うところ**

| 部品 | 使い方 |
|---|---|
| `routes/web.php` | `Route::prefix(config('neobit.admin_path'))->name('admin.')->middleware(['admin.ip', NoIndexHeader::class])` |
| `RestrictSiteAccess` | `except()` で渡されたパスに、`admin_path` と `admin_path/*` を足して、制限の外にする |
| `NoIndexHeader` | 応答に `X-Robots-Tag: noindex, nofollow` を付ける。管理画面のグループに付ける |

- ルートの名前（`admin.*`）は変えない。画面・コントローラー・管理画面の制限（`RestrictAdminAccess`）・回数の制限（`AdminRequestLimit`）は、ルートの名前とグループで動くので直していない。
- 前の `/admin` には何も置かない（404）。固定ページの `/{slug}` にも当たらない（`admin` という名前のページが無ければ）。
- robots.txt の `robots_disallow` には管理画面を書かない。フレームワークの既定は `[]`、見本のサイトは `['/company', '/mypage']`。

**Laravel を公開ディレクトリの外に置けないサーバー**（利用ガイドの付録）

- A：公開ディレクトリの外に置く（おすすめ）。シンボリックリンクか、`index.php` のパスの書き換え。
- B：公開ディレクトリを Laravel の `public` そのものにし、本体を公開ディレクトリの `app/system/` に置く。`index.php` に `usePublicPath(__DIR__)`、`vite.config.js` の出力先を公開ディレクトリに。公開ディレクトリの `.htaccess` で `/app/system/` の下を 404 にし、`app/system/.htaccess` にも `Require all denied`。`app/` には `.htaccess` を置かない。置いた後に、外から本体のファイルが 404 になることを確かめる（`docs/security-external-check.md` の5）。

## C. 前提・依存

- 管理画面のルートが、名前の頭 `admin.` のグループにまとまっていること
- サイトのアクセス制限（利用ガイドの24章）・ベーシック認証（25章）・robots.txt（27章）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| フレームワークの既定 | `app/admin` | `admin`：案件で変え忘れると、狙われる URL のまま置かれる |
| 管理画面を外すパス | `RestrictSiteAccess` が設定から自分で外す | `bootstrap/app.php` に書く：URL を変えたときに外し忘れる |
| 検索に出さない | `X-Robots-Tag` のヘッダー | robots.txt に書く：誰でも読めるので、隠した URL を教える |
| 前の `/admin` | 404 | 新しい URL へ移す：移した先を教える |
| B の置き場所 | 公開ディレクトリの `app/system/` | 直下に `laravel/` などを置く：直下にシステムのディレクトリが増え、デザイナーの置くファイルとまぎれる |
| B の本体の閉じ方 | `.htaccess` で 404 ＋ `Require all denied` の2枚 | どちらか1枚：書き損じで `.env` が見える |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | URL を隠すのは、攻撃の数を減らすためで、守りの本体ではない。ログイン・2段階認証・アクセス制限・ベーシック認証は、今までどおり要る |
| 低 | `admin_path` を変えたら、ベーシック認証の VirtualHost の決まりも手で直す必要がある（利用ガイドに書いた） |
| 低 | B の置き方は、サーバーで試していない |

## F. セキュリティ・権限・データ整合性の注意

- robots.txt と前の `/admin` から、管理画面の URL が分からないようにした。
- 管理画面の全部の応答に `X-Robots-Tag` を付け、検索エンジンに載せない。
- B の置き方では、本体を2枚の守りで閉じ、置いた後に外から確かめる手順を決めた。

## G. テスト状況

**実施済み**

- `AdminPathTest`：管理画面のルートが `/app/admin` の下にある。管理画面の応答に `X-Robots-Tag` が付き、訪問者の画面には付かない。`/admin`・`/admin/login` は 404
- robots.txt に管理画面の URL が出ない（`SeoTest`）
- ほかのテストの `/admin/…` を `/app/admin/…` に直して、全部が通る（アクセス制限で管理画面が制限の外になることも含む）
- 固定ページの slug で、`app`・`app/help` がほかの画面の名前として断られる

全体で 464件が通る。

**未実施**

- B の置き方の、サーバーでの確かめ

## H. 今後の改善提案・TODO

- 改ざん監視（計画書の段階4-11）を続ける
- B の置き方を、レンタルサーバーで試して手順を確かめる
