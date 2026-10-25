# Laravel の機能とパッケージへの依存

このプロジェクトとネオビットフレームワークが、Laravel のどの機能と、どのパッケージに頼っているかの一覧。

## 目的

Laravel やパッケージに脆弱性が出たときに、影響があるかを早く判断するため。使っている機能が多いほど、当たる脆弱性も増える。何を使っているかを把握し、使う機能を必要なものに絞る。

依存を減らすために、認証・暗号化・CSRF・パスワードのハッシュのような部分を自分で書き直すことはしない。多くの人が見て直している Laravel の側を使い、脆弱性が出たらすぐに版を上げる。Laravel に直接頼る部分は、なるべくフレームワーク（`packages/neobit/core/`）の中の少ない場所にまとめる。

## 使っている Laravel の機能

「主な場所」の `core/` は `packages/neobit/core/src/` を指す。

| 分野 | 機能 | 使い方 | 主な場所 |
|---|---|---|---|
| 認証 | セッションのガード（SessionGuard） | 管理画面（`admin`）・個人会員（`web`）・企業会員（`company`）の3つのガード。どれも `driver` は `session` | `config/auth.php`・`core/Auth/LoginSession.php` |
| 認証 | ユーザーの探し方（EloquentUserProvider） | 既存のシステムから移した会員の古い方式のパスワードも読む、`eloquent-legacy` の元 | `core/Auth/LegacyPasswordUserProvider.php` |
| 認証 | パスワードのハッシュ（Hash） | ログイン・パスワードの変更と再設定・バックアップコード | 認証まわりのコントローラー・`core/Auth/BackupCodeGenerator.php` |
| 認証 | 認可（Gate・Policy） | 非公開のファイルを見てよいか（`viewFiles`）、スタッフの権限 | `app/Policies/`・`core/Http/Controllers/UploadedFileController.php` |
| 認証 | 認証のイベント | ログインの記録 | `app/Providers/AppServiceProvider.php` |
| DB | Eloquent・クエリビルダー・トランザクション | ほぼ全部の画面。保存はトランザクションの中で行い、ファイルは確定の後に消す（`DB::afterCommit()`） | 全体 |
| DB | マイグレーション・スキーマ・シーダー | テーブルの作成と初期データ | `database/`・`core/../database/migrations/` |
| DB | 暗号化して保存する列（`encrypted` のキャスト） | 2段階認証の秘密鍵 | `core/Models/TwoFactorSecret.php` |
| HTTP | ルーティング・ミドルウェア・リクエスト・レスポンス | 全部の画面 | `routes/`・`bootstrap/app.php`・`core/Http/Middleware/` |
| HTTP | セッション（DB に保存）・CSRF の保護 | 全部のフォーム。ログインの途中の状態（確認コード・登録の途中など） | `config/session.php`・各コントローラー |
| HTTP | Cookie | ログインID の記憶・信頼済み端末・パスキーの案内 | `core/Auth/LoginIdMemory.php`・`TrustedDeviceManager.php`・`PasskeySuggestion.php` |
| HTTP | 署名付き URL | メルマガの配信停止の URL | `app/Services/MailUnsubscribe.php` |
| HTTP | HTTP クライアント | スパムの判定（Cloudflare Turnstile）への問い合わせ | `core/Security/SpamGuard.php` |
| 入力 | バリデーション | 全部のフォーム。ログインの ID とパスワードは `string` で、配列や数値を断る | 各コントローラー・`core/Crud/`・`core/Csv/` |
| 画面 | Blade・エラーの表示 | 全部の画面とメールのテンプレート | `resources/views/`・`core/../resources/views/` |
| 暗号 | 暗号化（Crypt） | スパムの判定のフォームの時刻 | `core/Security/SpamGuard.php` |
| 制限 | 回数の制限（RateLimiter） | ログインの失敗・登録や申し込みの回数・管理画面の回数の制限・IP の自動ブロック | `core/Auth/LoginThrottle.php`・`core/Security/IpBlocker.php`・`app/Providers/AppServiceProvider.php` |
| キャッシュ | キャッシュ（DB に保存） | IP ブロックの一覧・アクセス制限・SEO 設定・リダイレクトの一覧・エラーの通知の間引き・定期実行の記録 | `core/Security/`・`core/Seo/`・`core/Operations/` |
| キュー | キュー（DB に保存）・ジョブのバッチ | 一斉メールの送信 | `app/Jobs/SendBulkMail.php`・`app/Http/Controllers/Admin/BulkMailController.php` |
| メール | Mail・Mailable | お知らせ・確認コード・一斉メール。テンプレートは `resources/mail-templates/` | `core/Mail/`・`app/Mail/` |
| ファイル | ストレージ（`local` のディスク） | アップロードしたファイルと一時ファイル | `core/Upload/`・`core/Crud/ChildTables.php` |
| 定期実行 | スケジューラー・Artisan のコマンド | 後片付け・操作ログの報告。cron が無いサーバーでは、アクセスを契機に動かす | `routes/console.php`・`core/Operations/RequestScheduler.php`・`core/Console/` |
| 記録 | ログ | エラーの通知・警告 | 全体 |
| 通知 | Notifiable | 会員のモデルに付いている。通知の送信には使っていない | `app/Models/Member.php` |
| 土台 | サービスプロバイダー・設定・Facade・Collection・Str・Arr・Carbon | 全体 | 全体 |

## 使っていない主な機能

脆弱性が出たときに、まず「当たらない」と判断できる機能。使い始めるときは、上の表に移す。

| 機能 | 代わりにしていること |
|---|---|
| API の認証（TokenGuard・Sanctum・Passport） | API を持たない。ログインはセッションだけ |
| 認証の雛形（Fortify・Breeze・Jetstream） | ログイン・2段階認証・パスキー・パスワードの再設定は、フレームワークとコントローラーで書いている |
| 標準のパスワードの再設定（`Password::`） | 確認コードのメールで再設定する（`PasswordResetController`） |
| 通知の送信（Notification）・ブロードキャスト（WebSocket） | メールは Mail で送る |
| Redis・Memcached | キャッシュ・キュー・セッションは、どれも DB に保存する |

## Laravel 以外のパッケージ

### composer（PHP）

本番に入るもの（`composer install --no-dev`）。依存の依存まで含めると、全部で105個になる（`composer.lock`）。

| パッケージ | 何に使うか | 入れている所 |
|---|---|---|
| `laravel/framework` | Laravel 本体 | アプリ・フレームワーク |
| `laravel/tinker` | サーバーでの調べもの（`php artisan tinker`） | アプリ |
| `laravel/passkeys`（`web-auth/webauthn-lib` を使う） | パスキーの登録とログイン | フレームワーク |
| `pragmarx/google2fa` | 認証アプリ（TOTP）の2段階認証 | フレームワーク |
| `bacon/bacon-qr-code` | 認証アプリに読ませる QR コード | フレームワーク |
| `ezyang/htmlpurifier` | WYSIWYG の本文の無害化（`HtmlSanitizer`） | フレームワーク |
| `mpdf/mpdf` | PDF の出力（`PdfDownload`） | フレームワーク |

開発の手元だけで使うもの（サーバーには入れない）：`laravel/boost`・`laravel/pail`・`laravel/pao`・`laravel/pint`・`laravel/sail`・`phpunit/phpunit`・`mockery/mockery`・`fakerphp/faker`・`nunomaduro/collision`。

### npm と CDN（ブラウザー）

| 部品 | 何に使うか | 読み込み方 |
|---|---|---|
| `@laravel/passkeys` | パスキーの画面の処理 | npm（Vite でまとめる） |
| `suneditor` | WYSIWYG エディタ（固定ページ） | npm |
| `sortablejs` | ドラッグでの並べ替え（カテゴリー・コード表の編集） | npm |
| `vite`・`laravel-vite-plugin` | JavaScript と CSS のまとめ（ビルドのときだけ） | npm（開発用） |
| Bootstrap 5.3.3 | 画面の見た目 | CDN |
| jQuery 3.7.1 | summernote が使う | CDN |
| summernote 0.9.0 | WYSIWYG エディタ（ニュース） | CDN |

## 脆弱性が出たときの見方

1. **対象の機能とパッケージを見る**：お知らせに書かれた機能（例：TokenGuard）やパッケージが、上の表のどちらにあるかを見る。「使っていない主な機能」にあれば、当たらない。
2. **版を見る**：直った版より新しいかを見る。
   - 手元：`composer show laravel/framework`、または `composer.lock` の版
   - サーバー：`php artisan --version`、またはエラーの画面の版
   - まとめて調べるとき：`composer audit`・`npm audit`
3. **当たるときは版を上げる**：手元で `composer update`（または `npm update`）をして、テストと画面で確かめてから、`composer.json`・`composer.lock` をサーバーへ送る。サーバーでは `composer install --no-dev --optimize-autoloader` の後に、必ず `php artisan config:cache` をやり直す（利用ガイドの0章）。
4. **直った版がまだ無いとき**：その機能を使っている場所（上の表の「主な場所」）を見て、入力の確かめを足す・ルートを止めるなどの回避ができるかを考える。

## 依存を増やすときの決まり

- Laravel の新しい機能を使い始めるときは、上の表に足す。使うのをやめたら「使っていない主な機能」に移すか、消す。
- composer・npm のパッケージを足すときは、先に利用者に確認を取る（`CLAUDE.md` の決まり）。足したら、上の表に足す。
- 認証・暗号化・CSRF・セッションのような安全に関わる仕組みは、自分で書かずに Laravel のものを使う。

## 見直した記録

| 日付 | Laravel の版 | PHP の版（手元） | 内容 |
|---|---|---|---|
| 2026-10-22 | 13.31.0 | 8.3.35 | 最初の一覧。TokenGuard の脆弱性（GHSA-f9x3-7vq9-cph6、13.29.0 で修正）は、版が新しく、TokenGuard も使っていないので当たらないことを確かめた |
