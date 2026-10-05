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
| `config/auth.php` | ログインできるモデル（今は `Member` と `Staff`） |
| `config/mail.php` | 送信元の既定の値（`staff@example.com`）。実際の値は `.env` の `MAIL_FROM_ADDRESS` に書く |
| `phpunit.xml` | テスト用の DB の名前（`memsys_testing`） |
| `config/form.php` | 必須マークの HTML |
| `config/members.php` | 既存のシステムから会員を移すサイトだけ。古い方式のパスワードの種類（`legacy_passwords`）。新しく始めるサイトは、空のまま |
| `config/contact.php`・`config/services.php` | お問い合わせの宛先、Turnstile の鍵（中身は `.env`） |
| `bootstrap/app.php` | ログインしていないときの移動先のルート名、ミドルウェアの短い名前 |
| `composer.json`・`package.json` | プロジェクトの名前 |
| `CLAUDE.md`・`.ai/guidelines/`・`docs/` | そのプロジェクトの決まりと文書 |

## 3. 共通部品なのに、このサイトの形が入っているもの

`app/Support/` は「どのプロジェクトでもそのまま使う」前提ですが、会員とスタッフの構成や、ルートの名前を直接書いているものがあります。次のプロジェクトも「会員とスタッフがいて、ルートの名前も同じ」なら、どれも直さずに使えます。会員がいないサイトや、モデルの名前が違うサイト（`User`・`Admin` など）では、ここを直すことになります。

### 会員（Member）とスタッフ（Staff）のモデルを名指ししているもの

- `PasswordChange`・`TrustedDeviceManager`・`PasskeyManagement`
- `MemberVerificationCode`・`MemberActivityLog`・`MemberProfileNotice`
- `BackupCodeGenerator`（スタッフ）
- `OperationLog`・`OperationLogReport`（スタッフと会員の氏名を引く所）

### ルートやビューの名前を直接書いているもの

| 部品 | 書いてある名前 |
|---|---|
| `LoginRedirect` | `admin.dashboard`・`mypage` |
| `PasswordChange` | `admin.login`・`password.forgot`・`contact.create` |
| `MemberProfileNotice` | `password.forgot`・`contact.create` |
| `AdminRequestLimit` | ビュー `admin.too_many_requests` |
| `CsvImport` | ビュー `admin.csv_import.form`・`confirm` |
| `UploadFilePath` | `uploads.show`・`uploads.tmp` |
| `ErrorNotifyHandler` | ガードの名前 `admin`・`web` |

### この先の予定

3 の「会員を名指ししている所」と「ルートの名前を直接書いている所」は、会員の種類を複数にする設計（`member-types-spec.md`）で直す予定です。個人会員と企業会員が共存するサイトに対応するためです。直した後は、この節の内容が変わるので、この文書も合わせて直します。

## サーバーへ送らないもの

プロジェクトのファイルのうち、サーバーへ送ってはいけないものです。サーバーごとに作るためです。

- `public/build`（サーバーで `npm run build` して作る）
- `bootstrap/cache/*.php`（サーバーで作る。`bootstrap/app.php` などは送ってよい）
- `.env`（サーバーごとに別）

サーバーへ送った後に実行するコマンドは、利用ガイド（`neobit-framework-guide.md`）の0章にあります。
