# 新しいプロジェクトで書き換えるファイル

ネオビットフレームワークを新しいプロジェクトに展開するときに、書き換えることになるファイルをまとめたものです。2026-10-05 の時点の、見本のサイト（memsys）のコードを調べた結果です。

コントローラー・モデル・ルート（`routes/web.php`）・`.env`・`resources/`・`code/` は、プロジェクトごとに書くのが前提なので、ここには載せていません。載せているのは、それ以外で触ることになるファイルです。

## 1. コーナーやテーブルを足すたびに触るもの

| ファイル | 触る内容 |
|---|---|
| `database/migrations/`・`seeders/`・`factories/` | テーブル、初期データ |
| `app/Enums/` | そのサイトの選択肢（権限、状態など）。`OperationLogSubject` と `CodeType` は、モデルやコード表を足すたびに1行足す |
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
| `config/login.php` | 既存のシステムから会員を移すサイトでは、古い方式のパスワードの種類（`legacy_passwords`）と、初回のログインで照合する列（`identity_check_column`）。新しく始めるサイトは、そのまま。企業会員のあるサイトでは、申請の通知の宛先（中身は `.env`） |
| `config/contact.php`・`config/services.php` | お問い合わせの宛先、Turnstile の鍵（中身は `.env`） |
| `bootstrap/app.php` | ログインしていないときの移動先のルート名、ミドルウェアの短い名前 |
| `composer.json`・`package.json` | プロジェクトの名前 |
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
| `OperationLogSubject` | 操作ログに残すモデルの種類と、操作した人になれる種類 |
| `OperationLog::subjectNames()` | 画面に出す名前の読み方（スタッフ・個人会員・企業・企業の担当者） |
| `config/cleanup.php`（`tasks`） | プロジェクトの片付け（お問い合わせ） |

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
