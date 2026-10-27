# 新しいプロジェクトで書き換えるファイル

ネオビットフレームワークを新しいプロジェクトに展開するときに、書き換えることになるファイルをまとめたものです。2026-10-05 の時点の、見本のサイト（memsys）のコードを調べた結果です。

コントローラー・モデル・ルート（`routes/web.php`）・`.env`・`resources/`・`code/` は、プロジェクトごとに書くのが前提なので、ここには載せていません。載せているのは、それ以外で触ることになるファイルです。

## 1. コーナーやテーブルを足すたびに触るもの

| ファイル | 触る内容 |
|---|---|
| `database/migrations/`・`seeders/`・`factories/` | テーブル、初期データ |
| `app/Enums/` | そのサイトの選択肢（権限、状態など）。`AppOperationLogModel` は、モデルを足すたびに1行足す |
| `config/neobit.php`（`code_table_db`・`code_table_method`） | DB で管理するコード表を足すたびに、`code_table_db` に1行足す。名前と、コード値を固定するか。ほかのテーブルから作るコード表は、`code_table_method` にクラスとメソッドの名前を足す |
| `app/Providers/AppServiceProvider.php` | モデルの短い名前の一覧（`enforceMorphMap()`）。載っていないモデルを使うと例外になる |
| `app/Policies/` | 1件ごとの権限、非公開のファイルを見てよいか |
| `app/Rules/` | そのサイトだけの検証（電話番号など） |
| `vite.config.js` | 画面ごとの JavaScript を足したら、読み込むファイルの一覧に足す |
| `lang/ja/validation.php` | ほかの項目と比べるルールを使ったときだけ、`custom` に文言を足す |
| `routes/console.php`・`app/Console/Commands/` | 定期的な処理を足すとき |

## 2. プロジェクトの最初に1回だけ触るもの

| ファイル | 触る内容 |
|---|---|
| `config/auth.php` | ログインできるモデル（今は `Member`・`CompanyUser`・`Staff`） |
| `config/mail.php` | 送信元の既定の値（`staff@example.com`）。実際の値は `.env` の `MAIL_FROM_ADDRESS` に書く |
| `phpunit.xml` | テスト用の DB の名前（`memsys_testing`） |
| `config/form.php` | 必須マークの HTML |
| `config/upload.php` | アップロードできるファイルの種類と、大きさの上限。CSV の取り込みの上限もここ |
| `resources/pdf-sheets/` | 定型紙の PDF。見本の `resume_a4.pdf`（厚生労働省の履歴書様式例）は動きを見せるためのものなので、使わないなら消し、使うならその案件の定型紙に差し替える |
| `config/login.php` | 既存のシステムから会員を移すサイトでは、古い方式のパスワードの種類（`legacy_passwords`）と、初回のログインで照合する列（`identity_check_column`）。新しく始めるサイトは、そのまま。企業会員のあるサイトでは、申請の通知の宛先（中身は `.env`） |
| `config/contact.php`・`config/services.php` | お問い合わせの宛先、Turnstile の鍵（中身は `.env`） |
| `bootstrap/app.php` | ログインしていないときの移動先のルート名、ミドルウェアの短い名前 |
| `composer.json`・`package.json` | プロジェクトの名前 |
| `config/neobit.php` の `seo` | サイトマップに載せるページとモデル、検索に出さないルート、robots.txt で巡回させない場所、管理画面の「SEO設定」に出すページ（`page_routes`）、リダイレクトを使うか（`redirects`）、既定の説明文と画像、構造化データの運営者の名前とロゴ |
| `docs/project-overview.md` | そのプロジェクトの概要（何のプロジェクトで、どんな構成か）。`CLAUDE.md` は、どのプロジェクトでも共通の決まりなので書き換えない |
| `docs/` のそのほかの文書 | そのプロジェクトの設計や記録。`docs/laravel-rules.md`・`docs/neobit-framework-rules.md` は、フレームワークの決まりなのでそのまま使う |

## 3. 共通部品なのに、このサイトの形が入っているもの

`packages/neobit/core/` は「どのプロジェクトでもそのまま使う」前提ですが、スタッフの構成や、ルートの名前を直接書いているものがあります。次のプロジェクトも「スタッフがいて、ルートの名前も同じ」なら、どれも直さずに使えます。スタッフのモデルの名前が違うサイト（`Admin` など）では、ここを直すことになります。

### ログインする人は、名指ししていない

ログインする人（`Member`・`CompanyUser`・`Staff`）は、共通の型 `LoginAccount` で受け取ります。ガード・ルート・メールのテンプレートの名前は、モデルの「種類の名前」から決まりで作るので、共通部品に会員のモデルやルートの名前は書いていません。会員のモデルを足したり外したりするときに触る所は、利用ガイドの23章にまとめてあります。

- 企業会員を使わないサイトで消すもの：23章の「企業会員を使わないサイト」
- 個人会員をログインID でログインさせるサイトで直すもの：23章の「個人会員をログインID でログインさせるサイト」
- 会員の種類をもう1つ足すときに触るもの：23章の「会員の種類をもう1つ足すとき」

### ログインする人の受け取り方

2段階認証・信頼済み端末・パスキー・パスワードを変えた後始末（`PasswordChange`）の部品は、スタッフを名指ししていません。相手を、ログインする人の共通の型（`LoginAccount`）で受け取ります。スタッフのモデルの名前が違うサイトでも、そのモデルが `LoginAccount` を実装していれば使えます。

### モデルの種類ごとの一覧を持っているもの

モデルを足したり外したりしたら、ここも合わせます。

| 部品 | 持っているもの |
|---|---|
| `AppServiceProvider`（`enforceMorphMap()`） | モデルの短い名前 |
| `AppOperationLogModel` | 操作ログに残すモデルの種類と、操作した人になれる種類 |
| `OperationLog::modelNames()` | 画面に出す名前の読み方（スタッフ・個人会員・企業・企業の担当者） |
| `config/cleanup.php`（`tasks`） | プロジェクトの片付け（お問い合わせ） |

管理画面の「ベーシック認証」を使うサーバーでは、VirtualHost に、訪問者画面と管理画面のそれぞれを目印のファイルで掛ける決まりを書きます（書き方は利用ガイドの25章「ベーシック認証」）。パスは、そのサーバーのプロジェクトの場所にします。

`public/.htaccess` は、Laravel の決まりの「末尾の / を外す」決まり（Redirect Trailing Slashes）を消してあります。その働きはリダイレクトのミドルウェア（`ApplyRedirects`）が受け持つので、Laravel の新しい版の `.htaccess` に差し替えるときも、この決まりは戻しません。

### ルートやビューの名前を直接書いているもの

どれも、部品と組で使う、フレームワーク自身のルートとビューです。業務の画面のルート名は、部品には書いていません。

| 部品 | 書いてある名前 |
|---|---|
| `AdminRequestLimit` | ビュー `neobit::admin.too_many_requests` |
| `CsvImport` | ビュー `neobit::admin.csv_import.form`・`confirm` |
| `UploadFilePath` | `uploads.show`・`uploads.tmp` |

## サーバーへ送らないもの

プロジェクトのファイルのうち、サーバーへ送ってはいけないものです。サーバーごとに作るためです。

- `public/build`（サーバーで `npm run build` して作る）
- `bootstrap/cache/*.php`（サーバーで作る。`bootstrap/app.php` などは送ってよい）
- `.env`（サーバーごとに別）

サーバーへ送った後に実行するコマンドは、利用ガイド（`neobit-framework-guide.md`）の0章にあります。
