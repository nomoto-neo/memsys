# 仕様メモ：SEO の出す部品とサイトの既定（seo-head）

- 実装プロンプト：`docs/prompt/seo-head.md`
- 実装完了日：2026-10-25
- 計画書の段階4-9（SEO）の1

## A. 概要

**目的・解決する課題**

訪問者の側のページの `<head>` に、タイトル・説明文・OGP・X のカードを、サイト全体で揃った形で出す部品を足した。それまでは、レイアウトの `<title>` だけで、説明文や OGP はどの画面にも無かった。記事の詳細では記事から、ほかの画面ではサイトの既定から値を決め、入れなくても最低限は整う。Blade の中では、ヘルパーの `seo()` で値を取り出せる。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | `Seo`・`SeoSubject`・ヘルパーの `seo()`・部品の `neobit::_seo_head`・設定の `seo` |
| 見本のサイト | 訪問者の側のレイアウトの `<title>` を部品に置き換えた。ニュースと固定ページが `SeoSubject` を実装し、詳細の画面で `Seo::for()` を呼ぶ |
| 画面の題名 | `@section('title')` のある画面の題名が、「ログイン」から「ログイン｜サイト名」の形に変わった |

## B. 実装仕様

**値の決め方**（`Neobit\Core\Seo\Seo::value()`）

| 名前 | 記事がある（`Seo::for()` を呼んだ） | 記事が無い |
|---|---|---|
| title | `seoTitle()` を `seo.title_format`（`{title}｜{site}`）に当てはめる | `@section('title')` を当てはめる。無ければサイト名（`app.site_name`）だけ |
| description | `seoDescriptionSource()` から作る（下） | `seo.description`。空なら空 |
| image | `seoImageUrl()`、無ければ `seo.image`。絶対の URL | `seo.image`。無ければ空 |
| url | 今の URL（`url()->current()`。`?` の後ろを除いた絶対の URL） | 同じ |
| type | article | website |
| site_name | `app.site_name` | 同じ |

- 説明文の作り方：`<script>`・`<style>` は中身ごと除く（タグだけを除くと中身が文として残るため）。段落や改行のタグ（`</p>`・`<br>` など）は空白にする（除くと前後の文がくっつくため）。残りのタグを除き、文字の参照を戻し、続く空白を1つにまとめる。`seo.description_length`（120）文字より長ければ、その文字数で切って「…」を付ける（全角も1文字として数える）。
- 記事は、今のリクエストの属性に持つ（`Seo::for()` が入れる）。リクエストが変われば、前の記事は引き継がない。
- 知らない名前は `LogicException`（「seo()の名前には、'title'・…のどれかを渡してください：…」）。

**部品**（`neobit::_seo_head`）

- `<title>`・`<meta name="description">`・`og:title`・`og:description`・`og:url`・`og:type`・`og:site_name`・`og:image`・`twitter:card`（画像があれば summary_large_image、無ければ summary）を並べる。説明文と画像が空なら、そのタグを出さない。
- 値は全部 `seo()` で読むので、並べ方を変えたい案件は、部品を使わずにレイアウトで書ける。

**設定**（`config/neobit.php` の `seo`）

```php
'seo' => [
    'title_format' => '{title}｜{site}',
    'description' => '',
    'description_length' => 120,
    'image' => null,   // publicの下のパスか、URL
],
```

フレームワークの既定に置き、設定の写す元（`stubs/config/neobit.php`）と見本のサイトの `config/neobit.php` には、コメントにした見本を置いた。

**見本のサイト**

- `layouts/app.blade.php`：`<title>@yield('title', ...)</title>` を `@include('neobit::_seo_head')` に置き換えた。
- `News`：タイトル・本文・一覧用画像。`Page`：タイトル・本文、画像は無し（既定の画像）。
- `NewsController::show()`・`PageController::show()` で `Seo::for()` を呼ぶ。

## C. 前提・依存

- サイト名（`config('app.site_name')`。`.env` の `SITE_NAME`）
- 絶対の URL は `url()` で作る。サーバーの前にプロキシを置くときは `TRUSTED_PROXIES` を書かないと、`https://` にならない（利用ガイド0章）
- 画面の題名の `@section('title')`（今までの書き方のまま使う）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| サイトの既定の置き場所 | 設定（`config/neobit.php`） | 最初から管理画面の「SEO 設定」：計画の5で作る。そのときに、設定の値を画面の値で上書きする形にする |
| 記事からの値 | モデルが `SeoSubject` を実装する | コントローラーで毎回値を組み立てる：コーナーごとに書き方が食い違う |
| 記事の持ち方 | 今のリクエストの属性 | コンテナの1つの部品（scoped）：テストの中で続けて開いた画面に、前の記事が残った |
| Blade での使い方 | ヘルパーの `seo()` | 部品だけ：画面の中で説明文を使ったり、並べ方を変えたりできない |
| 題名の形 | `@section('title')` のある画面も、タイトルの形に当てはめる | 記事の画面だけ当てはめる：画面によって題名の形が食い違う |
| 管理画面・企業会員の画面 | 部品を置かない | 置く：検索に出さない画面で、説明文や OGP は要らない |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | 画面の題名が「〇〇｜サイト名」の形に変わった。題名を文字どおり確かめていたテストが1つあり、直した |
| 低 | サーバーの前にプロキシを置き、`TRUSTED_PROXIES` を書いていないと、画像と URL が `http://` で出る |

## F. セキュリティ・権限・データ整合性の注意

- 値は Blade の `{{ }}` で出すので、エスケープされる。本文の `<script>` の中身は、説明文に入らない（テストで確かめた）。
- 会員限定の記事の説明文も、その記事の詳細を開ける人にだけ出る（記事の詳細の画面と同じ）。

## G. テスト状況

**実施済み**（`tests/Feature/SeoTest.php`、6件）

- ニュースの詳細：題名・本文から作った説明文（段落の間は空白）・og:type が article・絶対の URL の画像・`?` を除いた og:url・大きな画像のカード
- 長い本文は、決めた文字数で切って「…」を付ける（全角も1文字）
- 画像の無い固定ページは、og:image を出さず、小さなカード
- 記事の無い画面は、サイト名だけの題名・説明文のタグなし。既定の説明文と画像を決めれば出る。`@section('title')` の画面は「ログイン｜デモサイト」
- 記事の詳細の後に開いた画面に、前の記事が残らない
- 知らない名前は例外

あわせて、`PageTest`（本文の `<script>` が説明文に入らないこと）と `TwoFactorMethodTest`（題名の形）が通る。全体で 426件が通る。

**未実施**

- 実際の SNS（LINE・Facebook・X）でのシェアの見え方の確認

## H. 今後の改善提案・TODO

- 計画書の段階4-9の2（記事ごとの SEO の欄）から続ける
