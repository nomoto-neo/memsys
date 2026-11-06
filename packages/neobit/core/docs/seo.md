# SEO（ページのタイトル・説明文・OGP）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`（冒頭のコメント）・`SeoSubject.php`、画面は `packages/neobit/core/resources/views/_seo_head.blade.php`　**実例**：ニュースの詳細（`NewsController::show()`）・固定ページ（`PageController::show()`）

訪問者の側のページの `<head>` に、タイトル・説明文・OGP・X のカードを、サイト全体で揃った形で出します。何も入れなくても、記事の詳細では記事から、ほかの画面ではサイトの既定から値が決まります。運用する人は、記事ごとの SEO の欄と、管理画面の「SEO設定」で値を変えられます。

```blade
{{-- layouts/app.blade.php の <head> --}}
@include('neobit::_seo_head')
```

```php
// モデル。SEOの元になる値を返す
class News extends Model implements SeoSubject
{
    public function seoTitle(): string { return $this->title; }
    public function seoDescriptionSource(): ?string { return $this->body; }
    public function seoImageUrl(): ?string { return $this->list_image_url; }
}

// コントローラーの詳細の画面
Seo::for($news);
```

| 値 | 記事の詳細（`Seo::for()` を呼んだ画面） | ほかの画面 |
|---|---|---|
| タイトル | 記事の SEO の欄か記事のタイトルを、タイトルの形（`{title}｜{site}`）に当てはめる | 「SEO設定」のページのタイトルか、画面の `@section('title')` を当てはめる。無ければサイト名だけ |
| 説明文 | 記事の SEO の欄か、本文のタグを除いた先頭の120文字（長ければ「…」） | 「SEO設定」のページの説明文か、既定の説明文。空なら説明文のタグを出さない |
| シェアの画像 | 記事の SEO の欄か記事の画像。無ければ既定の画像 | 「SEO設定」のページの画像か、既定の画像。無ければ出さない |
| `og:type` | article | website |

- **サイトの既定**：管理画面の「SEO設定」の既定（タイトルの形・説明文・画像）で、空欄なら `config/neobit.php` の `seo`（`title_format`・`description`・`image`）です。本文から作る説明文の文字数は `description_length` です。既定の画像は、`public` の下のパス（例：`/images/ogp.png`）か URL で書きます。
- **Blade で値を使う**：ヘルパーの `seo()` で取り出せます。名前は `'title'`・`'description'`・`'image'`・`'url'`・`'canonical'`・`'type'`・`'site_name'`・`'robots'`・`'site_verification'` です。`_seo_head` も中でこれを使っているので、並べ方を変えたい案件は、部品を使わずにレイアウトで自分で書けます。

    ```blade
    <meta name="description" content="{{ seo('description') }}">
    ```
- **URL**：画像と `og:url` は `https://` から始まる絶対の URL で出します。`og:url` は `?` の後ろを除いた今の URL です。
- **説明文の作り方**：本文の `<script>`・`<style>` は中身ごと除き、段落や改行のタグは空白にしてから、残りのタグを除きます。
- 部品を置くのは、訪問者の側のレイアウトだけです。管理画面と企業会員の画面は、検索に出さない画面のためです。

## 記事ごとの SEO の欄

**ファイル**：`packages/neobit/core/src/Seo/SeoFields.php`（コントローラー）・`HasSeoMeta.php`（モデル）・`src/Models/SeoMeta.php`、画面は `packages/neobit/core/resources/views/_seo_fields.blade.php`　**実例**：ニュースと固定ページの登録・編集（`Admin\NewsController`・`Admin\PageController`）

運用する人が、記事ごとに SEO のタイトル・説明文・シェアの画像の URL・検索に出さない（noindex）を、登録・編集の画面で決められます。値は共通のテーブル `seo_metas` に1記事1行で持つので、コーナーのテーブルに列を足す必要はありません。

```php
// モデル。SeoSubjectと一緒に使う。モデルの短い名前（enforceMorphMap()）にも登録しておく
class News extends Model implements SeoSubject
{
    use HasSeoMeta;
}

// コントローラー
use SeoFields;

] + $this->ajaxUploadRules() + $this->seoRules();
```

```blade
{{-- 入力欄（_fields.blade.php） --}}
@include('neobit::_seo_fields', ['input' => $input, 'readonly' => $readonly])
```

- **空欄のとき**：入れていない項目は、記事から自動で補います（タイトル・本文の先頭・記事の画像かサイトの既定の画像）。全部空なら、`seo_metas` の行を持ちません。
- **画像**：アップロードではなく URL を入れます。`/` から始まるサイトの中のパス（本文に挿入した画像の URL など）か、`http(s)://` から始まる URL です。その画像を後で消すと、シェアの画像は出なくなります。
- **検索に出さない**：入れると `<meta name="robots" content="noindex">` を出します。ヘルパーでは `seo('robots')` が `noindex` を返します。
- **画面**：入力の画面では、普段は閉じた「SEO の設定」の枠で、値かエラーがあれば開いた状態で出ます。確認・詳細の画面では、入れた値だけを並べ、全部空なら「（自動）」と出します。
- **FormFlow が呼ぶ**：値の組み立て・保存・削除は FormFlow が呼ぶので、入口の書き方は変わりません。部品を置いていない画面からの保存では、SEO の欄には触りません。
- CSV の書き出し・取り込みには含めません。SEO の欄の変更は、操作ログの「変わった列」には入りません。

## SEO 設定の画面（記事を持たないページとサイト全体の既定）

**ファイル**：`packages/neobit/core/src/Seo/SeoSettings.php`（冒頭のコメント）・`src/Http/Controllers/SeoSettingsController.php`、画面は `packages/neobit/core/resources/views/admin/seo_settings/index.blade.php`　**実例**：管理画面の「運用」の「SEO設定」

運用する人が管理画面から、サイト全体の既定（タイトルの形・説明文・シェアの画像・Google Search Console の確認タグ）と、記事を持たないページ（トップページ・一覧・お問い合わせなど）ごとのタイトル・説明文・画像・検索に出さないを変えられます。値は `settings` テーブルに持ちます。

```php
// routes/admin.php（管理者だけのグループの中）
FrameworkRoutes::seoSettings('/seo-settings');

// config/neobit.php の seo の節。画面に出すページ。ルート名 => 画面に出す名前
'page_routes' => [
    'top' => 'トップページ',
    'news.index' => 'お知らせの一覧',
],
```

- **画面に出すページ**：設定の `seo.page_routes` に並べたルートです。引数の要らないルートだけを書きます。引数の要るルートを書くと、画面に URL を出しません。
- **値の優先**：記事の SEO の欄 > 記事の値 > 「SEO設定」のページごとの値 > 画面の `@section('title')` > 「SEO設定」の既定 > 設定ファイルの順です。「SEO設定」のページの値を `@section('title')` より先にするのは、運用する人が画面で入れた値だからです。記事のあるページでは記事の値が先に決まるので、ページごとの値は、記事を持たないページで効きます。
- **空欄**：既定を空欄にすると、設定ファイルの値を使います。入力欄の見本に、その設定ファイルの値を出します。ページの欄を全部空にすると、そのページの設定を持ちません。
- **タイトルの形**：`{title}` を必ず入れます。`{site}` は任意です。
- **運営者**：運営者の名前（空欄なら設定ファイルの `organization_name`、それも空ならサイト名）とロゴの URL を、構造化データで出します。
- **確認タグ**：Search Console が示す `<meta name="google-site-verification" content="…">` をそのまま貼っても、値だけを入れてもかまいません。content の値（英数字・`_`・`-`）だけを保存し、`_seo_head` が全部のページの `<head>` に出します。
- **検索に出さない**：チェックしたページには noindex を付け、サイトマップからも外します。
- **キャッシュ**：訪問者の全部のページで読むので、画面の設定をキャッシュに置き、保存のたびに作り直します。DB を直接書き換えたときは、キャッシュを消します。
- **操作ログ**：保存すると「SEO設定の変更」に、変えた項目の名前だけを残します。説明文などの値は残しません。何も変わっていなければ残しません。確認画面は挟みません。

## 検索に出さないページ・canonical・サイトマップ・robots.txt

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`・`SitemapSource.php`・`src/Http/Controllers/SitemapController.php`・`RobotsController.php`

- **検索に出さない（noindex）**：次のどれかの画面には、`<meta name="robots" content="noindex">` が自動で付きます。
    - ルートに `auth` のミドルウェアが付いた画面（マイページなど、ログインしないと開けない画面）
    - コントローラーで `Seo::noindex()` を呼んだ画面。一覧の絞り込みはセッションに持っていて URL からは見分けられないので、絞り込みのある一覧で呼びます（見本はニュースの一覧）
    - 設定の `seo.noindex_routes` に並べたルートの名前。`*` が使えます（見本はお問い合わせの完了 `contact.thanks`）
    - 記事の SEO の欄で、検索に出さないにしたもの
    - 「SEO設定」のページごとの設定で、検索に出さないにしたもの
- **canonical**：全部の画面に `<link rel="canonical">` を出します。今の URL から `?` の後ろを除いたもので、ページ送りの `?page=` だけは残します（2ページ目以降も別のページとして検索に出すため）。
- **XML サイトマップ**（`/sitemap.xml`。フレームワークのルート `seo.sitemap`）：設定の `seo.sitemap_routes`（記事を持たないページのルートの名前）と、`seo.sitemap_models`（記事のモデル）から作ります。モデルは `SitemapSource` を実装し、訪問者に見せてよい記事のクエリ（`sitemapQuery()`）と記事の URL（`sitemapUrl()`）を返します。SEO の欄で検索に出さないにした記事と、「SEO設定」で検索に出さないにしたページは載せず、`updated_at` を更新日として出します。毎回その場で作るので、記事が数千件を超えるサイトでは、分割やキャッシュを考えます。

    ```php
    class News extends Model implements SeoSubject, SitemapSource
    {
        public static function sitemapQuery(): Builder { return static::query()->visibleTo(null); }
        public function sitemapUrl(): string { return route('news.show', $this); }
    }
    ```
- **robots.txt**（`/robots.txt`。フレームワークのルート `seo.robots`）：本番（`APP_ENV=production`）では、設定の `seo.robots_disallow` の場所を巡回させず、サイトマップの絶対の URL を伝えます。本番以外（開発サーバーなど）では、全部を巡回させません（`Disallow: /`）。`public/robots.txt` があると Apache がそちらを返すので、置きません。管理画面は、隠した URL を教えないよう `robots_disallow` に書かず、`X-Robots-Tag` のヘッダーで検索に出さないようにします（[settings.md](settings.md)「管理画面の URL」）。
- **設定を書くとき**：アプリの `config/neobit.php` の `seo` の節には、変えるキーだけを書きます（見本のサイトの `config/neobit.php` を参照）。

## パンくずリストと構造化データ（JSON-LD）

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`・`SeoArticle.php`、画面は `packages/neobit/core/resources/views/_breadcrumbs.blade.php`・`_seo_head.blade.php`　**実例**：ニュースの詳細（`NewsController::show()`）・固定ページ（`PageController::show()`）

```blade
{{-- layouts/app.blade.php の本文の上 --}}
@include('neobit::_breadcrumbs')
```

```php
// コントローラー。途中の段を、浅い段から順に足す
Seo::for($news);
Seo::breadcrumb('お知らせ', route('news.index'));

// 段の数が決まっていないとき（親子のカテゴリーなど）は、親をたどって足す
foreach ($category->ancestorsAndSelf() as $node) {
    Seo::breadcrumb($node->name, route('products.category', $node));
}
```

- **パンくずの段**：先頭の「トップ」と、末尾の今のページ名は自動で付きます。ページ名は `<title>` と同じ順（記事の SEO の欄 → 記事 → 「SEO設定」のページ → `@section('title')`）で決まる、タイトルの形に当てはめる前の名前です。トップページと、ページ名の無い画面には出しません。段を足していない画面は「トップ > ページ名」です。
- **URL の無い段**：`Seo::breadcrumb('製品情報')` のように URL を省くと、リンクにせず文字だけを出します。
- **固定ページの入れ子**：見本の `PageController::show()` は、slug（`company/history`）を「/」で区切り、公開している親のページを段にします。親は1回の問い合わせでまとめて探し、非公開や無いページの段は飛ばします。slug の持ち方はコーナーごとに違うので、部品にはしていません。
- **構造化データ**：`_seo_head` が `<script type="application/ld+json">` を1つ出します。中身は今のページに当てはまるものだけです。
    - パンくず（`BreadcrumbList`）：パンくずのある画面。今のページの段には URL を付けません
    - 記事（`Article`）：`SeoArticle`（`seoPublishedAt()`・`seoModifiedAt()`）を実装したモデルの記事。題・画像・公開日・更新日・正規の URL と、書いた側・出した側（運営者）
    - サイトと運営者（`WebSite`・`Organization`）：トップページだけ。運営者の名前とロゴは「SEO設定」
- **出さないとき**：検索に出さないページでは、構造化データを出しません。パンくずの表示は出します。
- **エスケープ**：JSON の `<`・`>`・`&` は `\u003C` などに置き換えるので、記事の題に `</script>` があっても抜け出せません。

    ```php
    class News extends Model implements SeoArticle, SitemapSource
    {
        public function seoPublishedAt(): ?DateTimeInterface { return $this->article_date; }
        public function seoModifiedAt(): ?DateTimeInterface { return $this->updated_at; }
    }
    ```

## slug（URL の名前）の検証のルール

**ファイル**：`packages/neobit/core/src/Rules/SlugRule.php`・`NotReservedPathRule.php`　**実例**：固定ページ（`Admin\PageController`）

slug はコーナーのテーブルの列に持ち、検証のルールだけを共通の部品にしています。重なりの検証は Laravel の `Rule::unique`、URL から探すのは `where()` で足ります。

```php
'slug' => [
    'required', 'string', 'max:100',
    new SlugRule(nested: true),                           // 形。nested: true なら「/」で区切ってつなげられる
    Rule::unique(Page::class, 'slug')->ignore($page?->id),
    new NotReservedPathRule(),                            // URL の直下（/{slug}）に置くコーナーだけ
],

// routes/pages.php。検証と同じパターンを使う
Route::get('/{slug}', ...)->where('slug', SlugRule::NESTED_PATTERN);
```

- **形**：半角の英小文字・数字・ハイフン・アンダーバーで、先頭は英小文字か数字。`nested: true` なら「/」でつなげられ、先頭・末尾の「/」と「//」は通しません。エラーの文は、通常の `validation.regex` の文です。
- **ほかの画面と重ならない**：名前の最初の区切りが、ほかのルートの URL の最初の区切りと同じなら断ります。`/news/{slug}` のように頭の付いた URL に置くコーナーでは要りません。
