# プロジェクトの概要（memsys）

このプロジェクトが何で、どんな構成かをまとめたもの。作業の決まりは `CLAUDE.md`、フレームワークごとの決まりは下の「使っているフレームワーク」が案内するファイルにある。

## 何のプロジェクトか

memsys は、ネオビットフレームワークの見本のサイト。ネオビットフレームワークは、Laravel の上に「コーナー（一覧・詳細・登録・編集・削除をひとまとめにした管理単位）を同じ型で作るための共通部品」を載せたもの。

見本として、会員制のサイトに要る機能をひととおり持っている。新しい案件は、このリポジトリを元にして作る。

## 使っているフレームワーク

| 使っているもの | 版 | 決まりを書いたファイル |
|---|---|---|
| Laravel（PHP） | Laravel 13 以上・PHP 8.3 以上 | `docs/laravel-rules.md` |
| ネオビットフレームワーク | このリポジトリが本体 | `docs/neobit-framework-rules.md` |

- Laravel や PHP のコードを読み書きする前に、`docs/laravel-rules.md` を読む。Artisan・テスト・整形（Pint）・Laravel Boost の道具の使い方もここにある
- コントローラー・モデル・マイグレーション・Blade・共通部品を読み書きする前に、`docs/neobit-framework-rules.md` を読む。このプロジェクトのコードは、ほとんどがこれに当たる
- 2つが食い違うときは、`docs/neobit-framework-rules.md` を優先する
- 動作条件より古い版への配慮は要らない

そのほかに使っているもの。

- DB は MariaDB。画面の CSS は Bootstrap で、レイアウトで CDN から読み込む
- JavaScript と CSS のまとめは Vite。WYSIWYG エディタは SunEditor（固定ページ）と summernote（ニュース）
- テストは PHPUnit、整形は Laravel Pint
- PDF は mPDF、2段階認証は google2fa、パスキーは laravel/passkeys

## 画面と機能

ログインする人は3種類で、それぞれガードが分かれている。

| 人 | ガード | 画面 |
|---|---|---|
| スタッフ | `admin` | 管理画面（`/admin`） |
| 個人会員 | `web` | 会員登録・ログイン・マイページ |
| 企業会員（企業の担当者） | `company` | 企業会員の画面（`/company`） |

- **訪問者の側**：トップ、ニュース、固定ページ（`/aboutus` のように URL の名前がそのままパスになる）、お問い合わせ、メールの配信停止
- **管理画面**：ニュース、カテゴリー、固定ページ、個人会員、企業と企業の担当者、スタッフ、コード表、一斉メール、操作ログ
- **認証まわり**：パスワードの再設定、2段階認証、パスキー、信頼する端末、ログインの試行制限

新しいコーナーを作るときに写す元は、ニュース・会員・スタッフ・カテゴリー。

## ディレクトリの構成

Laravel の標準の構成に、次のものを足している。

| 場所 | 中身 |
|---|---|
| `app/Support/`・`app/helpers.php` | ネオビットフレームワークの共通部品。どのプロジェクトでもそのまま使う前提 |
| `app/Http/Controllers/Admin/`・`Company/` | 管理画面と企業会員の画面のコントローラー。直下は訪問者と個人会員の側 |
| `app/Enums/`・`code/` | 選択肢の定義。列挙型と、CSV で持つコード表 |
| `resources/views/` 直下の `_` で始まるビュー | どのコーナーからも使う部分ビュー |
| `resources/js/` | 画面ごとの JavaScript。足したら `vite.config.js` の一覧にも足す |
| `resources/mail-templates/` | メールのテンプレート |
| `docs/` | このプロジェクトの文書（下の「文書」） |
| `tools/` | 開発の道具。`table2rules.php` は、テーブルの定義からコントローラーとモデルの下書きを書き出す |
| `check_latest.sh` | サーバーへ送ったファイルが最新かを確かめるスクリプト |

## 文書

| ファイル | 中身 |
|---|---|
| `docs/project-overview.md` | このファイル |
| `docs/laravel-rules.md` | Laravel・PHP の決まり |
| `docs/neobit-framework-rules.md` | ネオビットフレームワークの決まり |
| `docs/neobit-framework-guide.md` | ネオビットフレームワークの利用ガイド。部品の使い方の全体 |
| `docs/new-project-files.md` | 新しいプロジェクトに展開するときに書き換えるファイル |
| `docs/csv-import-spec.md` | CSV 取り込みの設計 |
| `docs/passkeys-spec.md` | パスキーの設計 |
| `docs/member-types-spec.md` | 個人会員と企業会員を共存させる設計 |
| `docs/security-code-check.md` | コードのセキュリティを確かめる観点と、確かめた記録 |
| `docs/security-external-check.md` | 設置したサイトを外から確かめる手順と、その記録 |
| `docs/security-issues.md` | まだ対応していないセキュリティ面の課題 |

## 開発と公開の環境

- 手元の開発環境は Windows 11（PowerShell）、PHP 8.3、MariaDB 10.11（`memsys_local`）。メールはレンタルサーバーの SMTP で実際に送る
- 本番・開発サーバーは自前の VPS で、AlmaLinux 10＋Apache＋PHP-FPM＋MariaDB。Laravel Cloud は使わない
- 手元の DB は `php artisan migrate:fresh --seed` で作り直せる前提。サーバーのデータは持ってこない（`APP_KEY` が違うので、暗号化した列を復号できない）
- サーバーへ送らないものと、送った後に実行するコマンドは、`docs/new-project-files.md` の末尾と利用ガイドの0章にある
