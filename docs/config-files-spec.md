# 仕様メモ：設定ファイルの置き場所を、読むもので分ける（config-files）

- 実装プロンプト：`docs/prompt/config-files.md`
- 実装完了日：2026-11-06

## A. 概要

**目的・解決する課題**

`config/` に、Laravel の雛形のファイル・フレームワークの設定・案件の設定が並び、見分けが付かなかった。Laravel のファイルにも、このプロジェクトの項目が足してあった。読むものによって置く場所を決め、案件の設定ファイルは名前で分かるようにした。動きと `.env` の名前は変えていない。

**決まり**（`docs/neobit-framework-rules.md`）

- フレームワークの機能の設定は `neobit.php`、案件の機能の設定は `site_` で始まるファイルに置く。それ以外は Laravel のファイル
- フレームワークは `site_` のファイルを読まない。フレームワークが読む値は、パッケージの `config/neobit/` に既定の値を持ち、案件は `neobit.php` で上書きする

**移したもの**

| 今 | 前 |
|---|---|
| `config/site_contact.php`（`site_contact.staff_email`） | `config/contact.php` |
| `config/site_login.php`（`site_login.company_user.registration_staff_email`・`identity_check_column`） | `config/login.php` |
| `neobit.auth.legacy_passwords.種類`（既定は空） | `login.種類.legacy_passwords` |
| `config/site_bulk_mail.php`（`per_minute`・`check_domain`） | `mail.bulk_per_minute`・`mail.bulk_check_domain` |
| `neobit.site.name`（既定の値が `.env` の `SITE_NAME` を読む） | `app.site_name` |
| 消した | `filesystems.permissions`（どこからも読まれていなかった） |

**変更したファイル**

| 場所 | ファイル |
|---|---|
| 設定 | `config/app.php`・`mail.php`・`filesystems.php`、`site_contact.php`・`site_login.php`・`site_bulk_mail.php` |
| フレームワーク | `config/neobit/site.php`・`auth.php`（新規）、`NeobitServiceProvider`・`stubs/config/neobit.php`・`LegacyPasswordUserProvider`、サイトの名前を読むクラスと画面 |
| 見本のサイト | `AppServiceProvider`・一斉メール・お問い合わせ・企業会員のコントローラー、レイアウトと画面 |
| テスト・文書 | `tests/Feature/`、利用ガイド・名前の一覧・`docs/new-project-files.md`・仕様メモ2つ・`check_latest.sh` |

## B. 実装仕様

- `NeobitServiceProvider` の節に `site`・`auth` を足した。案件の `config/neobit.php` には書かず、既定の値のまま使う
- `neobit.auth.legacy_passwords` は、種類の名前 => クラスの配列。書いていない種類は、古い方式の照合をしない
- `site_login.php` には、企業の担当者の2つだけが残った。キーは今までどおり種類の名前

## C. 前提・依存

- パッケージの追加・更新は無い
- `.env` の名前（`SITE_NAME`・`MAIL_BULK_PER_MINUTE`・`MAIL_BULK_CHECK_DOMAIN`・`CONTACT_STAFF_EMAIL`・`COMPANY_REGISTRATION_STAFF_EMAIL`）は変えていない

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 案件の設定ファイルの見分け方 | 名前の頭に `site_` | `config/site/` のディレクトリ：数が少ないので、かえって探す手間になる。Laravel のファイルに注意書きを足す：数が多く、雛形との見比べもしにくくなる |
| 頭の言葉 | `site_` | `app_`：Laravel の `app.php` と紛れる |
| `legacy_passwords` | フレームワークの設定 `neobit.auth` | `site_login.php` に残す：フレームワークが案件のファイルを名指しで読むことになる |
| サイトの名前 | `neobit.site.name` | `app.php` に残す：Laravel のファイルに足した項目のままになる |
| `mail.return_path` | `mail.php` に残す | 移す：Laravel 本体が読む項目なので、置き場所が決まっている |
| ガード・ログの出し先・`services.turnstile` | Laravel のファイルに残す | 移す：Laravel が用意した一覧に足すもので、ほかに置き場所が無い |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | サーバーに `config/login.php`・`contact.php` が残ると、古い設定のまま読まれ続ける（今は読む所が無いので害は無いが、紛らわしい）。消してから `config:cache` を流す |
| 低 | 配った後の案件が `app.site_name` や `login.〜` を読んでいたら、直す必要がある。今は memsys だけ |

## F. セキュリティ・権限・データ整合性の注意

- 値の出どころ（`.env`）は変えていないので、送った後に `config:cache` を流せば、同じ値で動く
- 古い方式のパスワードの照合は、memsys では今も空で、働かない

## G. テスト状況

- 全部のテスト：545件のうち544件が通り、1件はスキップ（`FileWatchTest` の root でしか確かめられないもの）
- `SiteNameTest` は、`SITE_NAME` をパッケージの既定の値のファイルから読むことを確かめる形に直した
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

- 案件の機能の設定を足すときは、`site_` で始まるファイルにする
