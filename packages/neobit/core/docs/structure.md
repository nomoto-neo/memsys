# ディレクトリとルート

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

案件を始めるときに知っておく、ファイルの置き場所と、ルートの書き方の全体です。

## ディレクトリの構成

Laravel の標準の構成に、次のものを足しています。ディレクトリは、要るようになったときに作ります。

| 場所 | 中身 |
|---|---|
| `app/Http/Controllers/` | ルートから呼ばれるプログラム。サブディレクトリの分け方は案件ごとに自由。見本のサイトは、直下が訪問者と個人会員、`Admin/` が管理画面、`Company/` が企業会員 |
| `app/Services/` | 業務の処理をまとめたクラス。画面とコマンドの両方から呼ぶ処理を置く |
| `app/Enums/`・`code/` | 選択肢の定義。列挙型と、CSV で持つ区分表（[code-table.md](code-table.md)） |
| `routes/` | 画面の種類ごとのルート（下の「ルートのファイル」） |
| `resources/views/` | 画面のテンプレート（Blade）。サブディレクトリの分け方は案件ごとに自由。見本のサイトは、管理画面が `admin/`、企業会員が `company/`。どのコーナーからも使う部分ビューは、直下に `_` で始まる名前で置く |
| `resources/js/` | 画面ごとのスクリプト。足したら `vite.config.js` の一覧にも足す（[blade.md](blade.md)） |
| `resources/mail-templates/` | メールのテンプレート（[mail.md](mail.md)） |
| `config/neobit.php` | フレームワークの設定のうち、案件で変えるものだけ（[settings.md](settings.md)） |
| `vendor/neobit/core/` | フレームワーク本体。直さない。この利用ガイドもここにある |

## ルートのファイル

ルートは4つのファイルに分けています。`bootstrap/app.php` が次の順に読み、URL の頭・ルート名の頭・ミドルウェアを、ファイルごとにまとめて付けます。ルートのファイルには、それより後ろの部分だけを書きます。ルートのファイルを増やす・減らすときは、`bootstrap/app.php` も直します。

| 順 | ファイル | 中身 | URL の頭 | ルート名の頭 | ミドルウェア |
|---|---|---|---|---|---|
| 1 | `routes/web.php` | 訪問者と個人会員 | — | — | `web` |
| 2 | `routes/company.php` | 企業会員 | `/company` | `company.` | `web` |
| 3 | `routes/admin.php` | 管理画面 | 設定の `route.admin_path` | `admin.` | `web`・`admin.ip`（管理画面の IP アドレスの制限）・noindex のヘッダー |
| 4 | `routes/pages.php` | 固定ページ | — | — | `web` |

- 固定ページ（`/{slug}`）は、`/` を含む URL も受けます。ほかの画面より先に当たらないよう、最後に読みます。
- 同じファイルの中でも、`/news/csv` のような固定の URL は、`/news/{news}` のような値を受ける URL より前に書きます。後ろに書くと、`csv` が id として扱われます。
- 管理画面の URL の頭は、設定で変えられます（[settings.md](settings.md) の「管理画面の URL」）。ルート名は変わらないので、画面とコントローラーは `route('admin.news.index')` のようにルート名で書きます。
- ログインしていないときの移動先は、ルート名の頭で決まります（`admin.*` は管理画面のログイン、`company.*` は企業会員のログイン、ほかは個人会員のログイン）。
- ルートのファイルを分けて足すときは、`bootstrap/app.php` で、同じ種類の画面のファイルと同じ頭とミドルウェアを付けます。付け忘れると、管理画面なら IP アドレスの制限が掛からず、企業会員ならログインしていないときに個人会員のログインへ移ります。

フレームワークも、自分のルートを読みます。案件で書く必要はありません。

- アップロードしたファイルの表示（`/uploads/...`）
- サイトマップ（`/sitemap.xml`）と `robots.txt`（[seo.md](seo.md)）
- どのルートにも当たらない URL を、公開ディレクトリの HTML と古い PHP に渡すもの（[public-files.md](public-files.md)）

## 管理画面のルートのグループ

`routes/admin.php` は、誰が開けるかでグループに分けています。

```php
// ログインしていないスタッフ：ログイン画面
Route::middleware('guest:admin')->group(function () { ... });

// ログイン中のスタッフ
Route::middleware(['auth:admin', 'auth.session', 'throttle:admin-screen'])->group(function () {
    // コーナーのルート（会員管理・ニュースなど）

    // 管理者だけ
    Route::middleware('acl.manager')->group(function () {
        // フレームワークの画面。使わない画面は、その行を消す
        FrameworkRoutes::codes('/codes');
        FrameworkRoutes::ipBlocks('/ip-blocks');
        // ...
    });
});
```

- 新しいコーナーのルートは、ログイン中のスタッフのグループに足します。管理者だけに見せるなら `acl.manager` のグループに足します（[authorization.md](authorization.md)）。
- `FrameworkRoutes` は、権限のミドルウェアを付けません。必ず `acl.manager` のグループの中で呼びます。渡す URL からルート名の頭が決まります（`/redirects` は `admin.redirects.`）。
