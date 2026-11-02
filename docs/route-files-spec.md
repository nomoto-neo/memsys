# 仕様メモ：ルートのファイル分けと、フレームワークの画面を1行で書く形（route-files）

- 実装プロンプト：`docs/prompt/route-files.md`
- 実装完了日：2026-11-01
- 計画書の段階4-10

## A. 概要

**目的・解決する課題**

`routes/web.php` が682行になり、訪問者・個人会員・企業会員・管理画面の全部が1つのファイルに入っていた。フレームワークの画面は、案件が組の名前で何行も書く必要があり、フレームワークの側で URL を足すと、全部の案件のルートを直すことになっていた。

**変えたこと**

- ルートのファイルを4つに分けた。頭の URL・名前・ミドルウェアは `bootstrap/app.php` が付ける
- フレームワークの8画面のルートを、`Neobit\Core\Routing\FrameworkRoutes` の1行で書けるようにした

ルートの名前・URL・メソッド・ミドルウェア・コントローラーは変えていない。

**変更したファイル**

| 場所 | ファイル |
|---|---|
| ルート | `routes/web.php`・`routes/company.php`・`routes/admin.php`・`routes/pages.php`・`bootstrap/app.php` |
| フレームワーク | `packages/neobit/core/src/Routing/FrameworkRoutes.php`（新規）、8画面のコントローラーの説明、`routes/web.php` を指していたコメント |
| 見本のサイト | `routes/web.php` を指していたコメント（`app/`・`config/neobit.php`） |
| テスト | `tests/Feature/RouteFilesTest.php` |
| 道具と文書 | `check_latest.sh`・利用ガイド・`docs/neobit-framework-names.md`・`docs/new-project-files.md`・`docs/project-overview.md`・計画書・`routes/web.php` を今の説明として書いていた仕様メモ |

## B. 実装仕様

**ファイルと読む順番**

| 順 | ファイル | 中身 | 付けるもの（`bootstrap/app.php`） |
|---|---|---|---|
| 1 | `routes/web.php` | 訪問者と個人会員 | `web`（Laravel が付ける） |
| 2 | `routes/company.php` | 企業会員 | `web`、`/company`、`company.` |
| 3 | `routes/admin.php` | 管理画面 | `web`・`admin.ip`・`NoIndexHeader`、設定の `neobit.route.admin_path`、`admin.` |
| 4 | `routes/pages.php` | 固定ページ | `web` |

2〜4は `withRouting()` の `then:` で読む。`then:` は `web:` の後に動く。

**FrameworkRoutes**

- メソッド：`codes`・`ipBlocks`・`accessRestriction`・`basicAuth`・`fileWatch`・`seoSettings`・`redirects`・`operationLogs`。どれも URL を1つ受け取る
- 名前の頭は、渡した URL の前後の `/` を外し、残りの `/` を `.` にしたもの（`/redirects` は `redirects.`、`/tools/moved` は `tools.moved.`）
- パッケージの側では読み込まない。案件が、管理者だけのグループの中で、使う画面の分だけ呼ぶ
- コントローラーが、呼ばれたルートの名前から組の名前を作る決まりは変えていない

## C. 前提・依存

- Laravel の `withRouting()` の `then:` が、`web:` のルートの後に動くこと
- `admin.ip`・`acl.manager` などの短い名前は、`withMiddleware()` の `alias()` で登録したまま。ルートが動くときに解決する
- パッケージの追加・更新は無い。`composer.json` も変えていない

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 固定ページのルート | `routes/pages.php` に分けて最後に読む | `routes/web.php` に残す：`/{slug}` は `/` を含む URL も受けるので、後から読む `/company/login` などを先に受けてしまう |
| 頭の URL・名前・ミドルウェア | `bootstrap/app.php` でファイルごとに1回だけ | 各ファイルの中のグループ：何を付けているかが読み込みの所で見えない。`web: [...]` の配列でも読めるが、頭を付けられない |
| 1行で書く形 | 普通のクラスの静的メソッド | ルートのマクロ（`Route::neobitRedirects()`）：エディタで説明の表示と定義へのジャンプができない |
| 名前の頭 | 渡した URL から作る | 名前も引数で渡す：見本の8画面は URL と同じ名前で、引数が増えるだけ。違う名前にしたい案件は、グループの `name()` で頭を足せる |
| コーナーごとのファイル | 作らない | `routes/admin/news.php` など：今の規模では、探す手間のほうが増える |

## E. 課題・懸念点

- `routes/` と `bootstrap/app.php` は、同時にサーバーへ送る必要がある。片方だけだと、ルートが欠けるか二重になる
- 見本のサイトの操作ログの4件は、登録の順番が管理者だけのグループの中で前へ動いた。同じ URL に当たるルートが無いので、当たり方は変わらない
- `FrameworkRoutes` の名前は、段階4-5 の総点検で確かめ、このままとした

## F. セキュリティ・権限・データ整合性の注意

- `FrameworkRoutes` は権限のミドルウェアを付けない。必ず管理者だけのグループの中で呼ぶ。グループの外で呼ぶと、管理者でないスタッフや、ログインしていない人にも開く
- 管理画面の `admin.ip` は、`bootstrap/app.php` で `routes/admin.php` 全体に掛かる。管理画面のルートを別のファイルに足すときは、同じものを付ける

## G. テスト状況

- 分ける前後で、全部のルートの名前・URL・メソッド・アクション・ミドルウェア（順番も）・除外したミドルウェア・`where`・`scopeBindings`・`withTrashed` を比べ、296件すべて同じことを確かめた。登録の順番は、企業会員とそのほかは同じ、管理画面は操作ログの4件が動いただけ
- `tests/Feature/RouteFilesTest.php`：ほかの画面が固定ページより先に当たること、どの画面にも当たらない URL が固定ページに当たること、ファイルごとの頭とミドルウェア、別の URL を渡したときの名前と URL、見本の8画面が管理者だけのグループにあること
- テスト全体は530件のうち529件が成功し、1件がスキップ（`FileWatchTest` の、root では確かめられない権限の確かめ）。Pint も通った
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

