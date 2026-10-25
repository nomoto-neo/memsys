# 仕様メモ：構造化データ（JSON-LD）とパンくずリスト（seo-structured-data）

- 実装プロンプト：`docs/prompt/seo-structured-data.md`
- 実装完了日：2026-10-26
- 計画書の段階4-9（SEO）の6

## A. 概要

**目的・解決する課題**

訪問者の側の画面に、パンくずリストと構造化データ（JSON-LD）を、サイト全体で揃った形で出せるようにした。検索結果にパンくずや記事の日付が出やすくなり、訪問者も今いる場所が分かる。今まではどちらも無く、案件ごとにテンプレートへ書く必要があった。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | `Seo`（`breadcrumb()`・`breadcrumbs()`・`jsonLd()`）、新規 `SeoArticle`・`neobit::_breadcrumbs`、`_seo_head`（JSON-LD）、`SeoSettings` と「SEO設定」の画面（運営者の名前・ロゴ）、設定の `seo.organization_name`・`organization_logo` |
| 見本のサイト | レイアウトに `_breadcrumbs`。ニュースが `SeoArticle` を実装し、詳細で「お知らせ」の段を足す。固定ページは入れ子の slug の親を段にする。お知らせの一覧とお問い合わせ（入力・確認・完了）に `@section('title')` を足した |
| DB | 変更なし（「SEO設定」の値は `settings` テーブルにキーで足す） |

## B. 実装仕様

**パンくずの段**（`Seo::breadcrumbs()`）

1. 先頭は「トップ」（`url('/')`）
2. 途中は、コントローラーが `Seo::breadcrumb($label, $url = null)` で足した順。URL を省いた段は文字だけ
3. 末尾は今のページ名。URL を付けない

- 今のページ名は、記事の SEO の欄のタイトル → 記事のタイトル → 「SEO設定」のページのタイトル → `@section('title')` の順で、最初にあるもの（タイトルの形に当てはめる前）。`<title>` と同じ決め方（`Seo::pageName()`）。
- トップページ（`/`）と、ページ名の無い画面では空。段はリクエストの属性に持つ。

**表示**（`neobit::_breadcrumbs`）

- Bootstrap の `<nav aria-label="パンくずリスト"><ol class="breadcrumb">`。末尾は `active` と `aria-current="page"`。

**構造化データ**（`Seo::jsonLd()`。`_seo_head` が `<script type="application/ld+json">` で出す）

| 種類 | 出す画面 | 中身 |
|---|---|---|
| `BreadcrumbList` | パンくずのある画面 | `ListItem` の `position`・`name`・`item`（末尾は `item` 無し） |
| `Article` | `SeoArticle` を実装した記事の詳細 | `headline`（ページ名）・`image`・`datePublished`・`dateModified`（ISO 8601）・`mainEntityOfPage`（正規の URL）・`author`・`publisher`（どちらも運営者。名前とサイトのトップの URL、ロゴがあれば `ImageObject`） |
| `WebSite`・`Organization` | トップページだけ | サイト名と URL、運営者の名前・ロゴ・URL |

- 1つの `@graph` にまとめる。当てはまるものが無いページと、検索に出さないページでは出さない。
- `json_encode` に `JSON_HEX_TAG | JSON_HEX_AMP` を付け、`<`・`>`・`&` を `<` などにする。`</script>` で抜け出させないため。

**運営者**（「SEO設定」のサイト全体の既定）

| 項目 | 設定のキー | 空のとき |
|---|---|---|
| 運営者の名前（100文字まで） | `seo.organization_name` | 設定ファイルの `organization_name`、それも空ならサイト名 |
| 運営者のロゴの URL | `seo.organization_logo` | 設定ファイルの `organization_logo`。それも無ければ出さない。形は `Seo::IMAGE_URL_PATTERN` |

- 操作ログの項目の名前は「運営者の名前」「運営者のロゴ」。
- `SeoSettings::save()` は、既定を名前 => 値の配列で受け取る形にした（項目が増えても引数が増えないため）。

**見本のサイト**

- `News` が `SeoArticle` を実装。公開日は `article_date`、更新日は `updated_at`。
- `NewsController::show()`：`Seo::breadcrumb('お知らせ', route('news.index'))`。
- `PageController::show()`：slug を「/」で区切った親の slug（`company`・`company/history`）を `whereIn` で1回にまとめて探し、公開しているものだけを浅い順に段にする。slug に「/」が無ければ問い合わせは増えない。

## C. 前提・依存

- SEO の出す部品（段階4-9の1）・記事ごとの SEO の欄（2）・自動の noindex（4）・「SEO設定」の画面（5）
- Bootstrap のパンくずの CSS（レイアウトで読み込み済み）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| 段の決め方 | コントローラーで `Seo::breadcrumb()` | パッケージ（diglactic/laravel-breadcrumbs）・ルートごとの定義ファイル：パッケージが増え、記事の題のような値はコントローラーにしか無い |
| 段を足していない画面 | 「トップ > ページ名」を出す | 足したときだけ出す：1段の画面でも、出すためだけに呼ぶ必要がある |
| 記事の日付 | 別のインターフェース `SeoArticle` | `SeoSubject` に足す：日付の無い固定ページにも実装を求める |
| JSON-LD の出す場所 | `_seo_head` の1か所 | パンくずの部品と一緒に本文へ：種類ごとに場所が分かれ、`<head>` だけを差し替える案件で抜ける |
| 固定ページの入れ子 | 公開している親を段にする（1回の問い合わせ） | 親を作らない：固定ページの多いサイトで階層が見えない。重さは slug に「/」があるときの1回だけ |
| 親を探す処理 | 見本のコントローラーに書く | 部品にする：slug の持ち方がコーナーごとに違う |
| 運営者の値 | 「SEO設定」と設定ファイル | 設定ファイルだけ：運用する人が社名やロゴを変えられない |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | パンくずのある画面の HTML が増えた（レイアウトを自前で作る案件は `_breadcrumbs` を置くか決める） |
| 低 | `@section('title')` の無い画面にはパンくずが出ない。見本ではお知らせの一覧とお問い合わせに足した（`<title>` もサイト名だけから変わる） |
| 低 | 住所や電話番号の入る `LocalBusiness`・よくある質問の `FAQPage`・商品の `Product` は無い。案件で要るときに足す |
| 低 | Google のリッチリザルトのテストでの確認はまだ |

## F. セキュリティ・権限・データ整合性の注意

- JSON-LD は `{!! !!}` で出すが、`JSON_HEX_TAG`・`JSON_HEX_AMP` で `<`・`>`・`&` を置き換えてあるので、記事の題に `</script>` があっても抜け出せない（テストで確かめた）。
- パンくずの文字は `{{ }}` で出す。
- 固定ページの親は、公開しているもの（`visible()`）だけを段にする。非公開のページの題は出さない。

## G. テスト状況

**実施済み**（`tests/Feature/SeoTest.php` に5件。全部で26件）

- 記事の詳細は「トップ > お知らせ > 記事の題」、お知らせの一覧は「トップ > お知らせ」、トップページには出さない
- 入れ子の固定ページで、公開している親だけを段にし、非公開の親を飛ばす
- 記事の詳細に `BreadcrumbList` と `Article`（公開日・正規の URL・書いた側）が出る。記事の題の `</script>` で抜け出せない
- トップページに `WebSite` と、「SEO設定」の運営者の名前・ロゴの `Organization` が出る
- 検索に出さないページでは、パンくずは出て、構造化データは出ない

全体で 449件が通る。

**未実施**

- Google のリッチリザルトのテスト・Search Console での確認

## H. 今後の改善提案・TODO

- 計画書の段階4-9の7（301 リダイレクトの管理）から続ける
- 案件で要るときに、`LocalBusiness`・`FAQPage`・`Product` などの種類を足す
