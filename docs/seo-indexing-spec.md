# 仕様メモ：自動の noindex・canonical・XML サイトマップ・robots.txt（seo-indexing）

- 実装プロンプト：`docs/prompt/seo-indexing.md`
- 実装完了日：2026-10-26
- 計画書の段階4-9（SEO）の4

## A. 概要

**目的・解決する課題**

検索エンジンへの案内を、入れなくても整うようにした。検索に出さないほうがよいページ（ログインが要る画面・絞り込んだ一覧・お礼の画面）に noindex を自動で付け、全部の画面に canonical を出し、公開している記事から XML サイトマップを作り、robots.txt を環境ごとに返す。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | `Seo`（`noindex()`・robots の自動の判断・canonical）、`SitemapSource`、`SitemapController`・`RobotsController` とルート（`/sitemap.xml`・`/robots.txt`）、設定の `seo` のキー |
| 見本のサイト | ニュースと固定ページが `SitemapSource` を実装。ニュースの一覧で絞り込みがあれば `Seo::noindex()`。`config/neobit.php` に `seo` の値。`public/robots.txt` を消した |
| サーバー | `public/robots.txt` を消す必要がある（`check_latest.sh` が知らせる） |

## B. 実装仕様

**noindex**（`Seo::isNoindex()`）

次のどれかなら `seo('robots')` が `noindex` を返し、`neobit::_seo_head` が `<meta name="robots" content="noindex">` を出す。

1. 記事の SEO の欄で検索に出さないにした
2. コントローラーで `Seo::noindex()` を呼んだ（今のリクエストの属性に印を付ける）
3. ルートのミドルウェアに `auth` か `auth:〇〇` がある（`gatherMiddleware()`）
4. ルートの名前が、設定の `seo.noindex_routes` のどれかに当たる（`Route::named()`。`*` が使える）

**canonical**（`seo('canonical')`）

- `url()->current()`（`?` の後ろを除いた URL）。`?page=` があれば、それだけを付け直す。
- `neobit::_seo_head` が `<link rel="canonical" href="…">` を出す。

**サイトマップ**（`SitemapController`、`GET /sitemap.xml`、名前 `seo.sitemap`）

- 設定の `seo.sitemap_routes` のルートの URL（更新日なし）と、`seo.sitemap_models` のモデルの記事の URL・更新日（`updated_at` の ISO 8601）を並べる。
- モデルは `SitemapSource` を実装していなければ `LogicException`。記事は `sitemapQuery()` のうち、`HasSeoMeta` のモデルなら SEO の欄で検索に出さないにしたものを除き、500件ずつ読む。
- 画面は `neobit::sitemap`（XML）。`Content-Type: application/xml`。

**robots.txt**（`RobotsController`、`GET /robots.txt`、名前 `seo.robots`）

- 本番（`APP_ENV=production`）：`User-agent: *`、`seo.robots_disallow` の場所ごとに `Disallow:`、空行、`Sitemap: （サイトマップの絶対の URL）`。
- 本番以外：`User-agent: *` と `Disallow: /`。

**設定**（`config/neobit.php` の `seo` に足したキー）

| キー | フレームワークの既定 | 見本のサイト |
|---|---|---|
| `noindex_routes` | `[]` | `['contact.thanks']` |
| `sitemap_routes` | `[]` | `['top', 'news.index', 'contact.create']` |
| `sitemap_models` | `[]` | `News::class`・`Page::class` |
| `robots_disallow` | `['/admin']` | `['/admin', '/company', '/mypage']` |

`seo` は配列のキーで、アプリが書くと丸ごと置き換わるので、見本のサイトの `config/neobit.php` は全部のキーを書いた（生きた行）。

**見本のサイト**

- `News::sitemapQuery()` は `visibleTo(null)`（表示・一般公開・掲載期間の中）、`Page::sitemapQuery()` は `visible()`。どちらもスコープなので、`static::query()` から呼ぶ。
- `NewsController::index()`：絞り込みの値（`$result['filters']`）に空でないものがあれば `Seo::noindex()`。

## C. 前提・依存

- SEO の出す部品（1）と、記事ごとの SEO の欄（2）
- ルートのミドルウェアの書き方（ログインが要る画面に `auth:〇〇`）
- 本番のサーバーの `APP_ENV=production`

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| 絞り込んだ一覧の見分け方 | コントローラーで `Seo::noindex()` | URL から見分ける：絞り込みはセッションに持っていて、URL に出ない |
| canonical の `?page=` | 残す | 1ページ目にまとめる：2ページ目以降の記事が検索に出にくくなる。今の Google はページ送りをまとめない |
| robots.txt | フレームワークのルートで返し、`public/robots.txt` を消す | 静的なファイル：サイトマップの絶対の URL を環境ごとに書けず、開発サーバーを止める切り替えもできない |
| 本番以外の robots.txt | 全部を巡回させない | 本番と同じ：開発サーバーが検索に出る |
| サイトマップの作り方 | 毎回その場で作る | キャッシュ・分割：記事が数千件までなら要らない。増えたら足す |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | サーバーに `public/robots.txt` が残っていると、Apache がそちらを返し、新しい robots.txt が効かない。送った後に消す必要がある |
| 低 | サイトマップは毎回その場で作るので、記事が数千件を超えると重くなる |
| 低 | `Seo::noindex()` を呼び忘れた絞り込みの一覧は、検索に出る |

## F. セキュリティ・権限・データ整合性の注意

- サイトマップには、訪問者に見せてよい記事（`sitemapQuery()`）だけを載せる。会員限定・非表示・掲載期間の外の記事は入らない（テストで確かめた）。
- robots.txt の巡回させない場所は、隠したい場所を知らせることにもなる。管理画面の守りは、ログインと IP の制限で行う（robots.txt に頼らない）。

## G. テスト状況

**実施済み**（`tests/Feature/SeoTest.php`、全部で15件）

- マイページ（`auth`）とお問い合わせの完了（設定）に noindex が付き、お知らせの一覧には付かない
- 絞り込みをしたお知らせの一覧に noindex が付く
- canonical は `?page=` だけを残し、ほかの `?` の後ろを除く
- サイトマップに、設定のルート・公開の記事・固定ページが載り、更新日が出る。会員限定・非表示・検索に出さないにした記事は載らない
- robots.txt が、本番以外は `Disallow: /`、本番は設定の場所とサイトマップの URL

全体で 438件が通る。

**未実施**

- 実際の検索エンジン（Google Search Console）でのサイトマップの読み込みの確認

## H. 今後の改善提案・TODO

- 計画書の段階4-9の5（記事を持たないページの「SEO 設定」の画面）から続ける
