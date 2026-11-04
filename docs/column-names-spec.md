# 仕様メモ：テーブルの列の名前の見直し（column-names）

- 実装プロンプト：`docs/prompt/column-names.md`
- 実装完了日：2026-11-05
- 計画書の段階4-5-2（名前の総点検の続き）のうち、テーブルの列の分

## A. 概要

**目的・解決する課題**

配った後に列の名前を変えると、全案件でマイグレーションと画面の直しが要る。版を出す前に、中身が読み取りにくい列と、持ち方がそろっていない列を直す。見本のサイトのテーブルも、案件が写す元になるので同じく直した。動きは変えていない。

**変えた列**

| 今の名前 | 前の名前 | 理由 |
|---|---|---|
| `code_tables.label` | `name` | 画面に出す文字で、列挙型の `label()`・ヘルパーの `code_label()` とそろう。`name` は人の名前と紛れる |
| `ip_blocks.expires_at` | `blocked_until` | 期限が来た行は消すので、記録そのものの期限。`trusted_devices`・`team_invitations` とそろう |
| `seo_metas.image_url` | `image` | 入るのは画像の URL。画面の見出し「シェアの画像のURL」と `seoImageUrl()` とそろう |
| `t_bulk_mails.status` | 文字列（`sending`・`finished`） | 数値（送信中1・完了2）。既存のプロジェクトの形にそろえる |
| `t_companies.status` | 文字列（`pending`・`approved`・`suspended`） | 数値（申請中1・承認済み2・停止9）。同じ |
| `t_companies.ceo_name` | `representative` | 単語の馴染みが薄い。株式会社に限らず、団体の代表者も入れる |
| `t_members.birthday` | `birthdate` | 日本の業務システムで馴染みのある名前 |

**一緒に変えた名前**

| 今の名前 | 前の名前 |
|---|---|
| 項目見出し一覧のフォームの欄 `codes[][label]` | `codes[][name]` |
| `IpBlocker::changeExpiry()`、引数 `$expiresAt` | `changeUntil()`、`$until` |
| 操作ログの詳細のキー `expires_at`（IPブロック） | `until` |
| 記事の SEO の欄 `seo_image_url` | `seo_image` |
| SEO設定の欄と `settings` のキー `seo.image_url`・`seo.organization_logo_url`、`seo.pages` の中の `image_url` | `seo.image`・`seo.organization_logo`、`image` |
| 設定 `neobit.seo.image_url`・`neobit.seo.organization_logo_url` | `image`・`organization_logo` |
| `SeoSettings::imageUrl()`・`organizationLogoUrl()` | `image()`・`organizationLogo()` |
| 列挙型 `BulkMailStatus`・`CompanyStatus` | `string` の列挙型から `int` の列挙型に |

**変更したファイル**

| 場所 | ファイル |
|---|---|
| フレームワーク | `packages/neobit/core/`（マイグレーション、`Code`・`IpBlock`・`SeoMeta`、`CodeTable`・`IpBlocker`・`Seo`・`SeoFields`・`SeoSettings`、コントローラー3つ、画面、`code_editor.js`、設定） |
| 見本のサイト | `app/`（列挙型・モデル・会員と企業のコントローラー）、`resources/views/`、`database/`（マイグレーション・シーダー・ファクトリー） |
| テスト | `tests/Feature/` |
| 文書 | 名前の一覧・利用ガイド・SEO の仕様メモ・計画書・`check_latest.sh` |

## B. 実装仕様

**マイグレーション**

- フレームワーク：作るマイグレーションを新しい名前にしたうえで、`2026_11_05_000000_rename_columns_on_neobit_core_tables.php` が、古い名前の列があるときだけ変える。`ip_blocks` の索引の名前も `ip_blocks_expires_at_index` にそろえる。`settings` の SEO のキーと `seo.pages` の中のキーを書き換え、SEO設定のキャッシュ（`neobit-seo-settings`）を消す
- 見本のサイト：`2026_11_05_000000_rename_columns_on_member_tables.php` が列の名前を変え、状態の文字列を番号に書き換えてから、型を `tinyint` に変える
- どちらも戻せる（`down()`）

**画面**

- 企業の状態のバッジの色は、`CompanyStatus::badgeClass()` が返す。Blade に列挙型を書かない決まりに合わせた
- 画面の見出し（「代表者名」「生年月日」「シェアの画像のURL」など）は変えていない

**変えなかったもの**

- `seo('image')` のキー。`<head>` に出す値を `og:image` などに合わせて並べたもの
- `csv_download_logs.name`・`csv_import_logs.name`。案件が付ける CSV の題名で、ファイル名にもなる
- `t_newsletter_subscribers.source`・`operation_logs.action`。種類が増える・案件が足すものなので文字列のまま
- `PasskeyManagement` のセッションの `until`。IPブロックとは別のもの

## C. 前提・依存

- パッケージの追加・更新は無い
- `seo_metas` の画像の URL を変えたことで、案件の `config/neobit.php` に `seo.image`・`seo.organization_logo` を書いていれば、新しい名前に直す必要がある

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 状態の列の持ち方 | `tinyint` の数値と列挙型 | 文字列：大きくなり、書き間違いに気付きにくい。`ENUM` 型：既存のプロジェクトの形と違い、候補を足すたびに `ALTER TABLE` が要る |
| 状態の番号 | 進む順に1から、止める状態は9 | 0から：既存のプロジェクトの形（1:仮登録・2:本登録・9:退会）と違う |
| 代表者の列 | `ceo_name` | `president_name`：社長の意味が強い。`owner_name`：株主と読まれる。`daihyo_name`：ローマ字が混ざる |
| パッケージのマイグレーション | 名前を変えるマイグレーションを足す | 1本にまとめる：すでに流した DB の扱いを決めてから。最初の版の前に決める |
| 操作ログの詳細のキー | `expires_at` に変える | 残す：配る前なので、書いた記録の表示よりそろえることを選んだ |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | サーバーで `php artisan migrate` を流すまでの間、列の名前が合わずに画面がエラーになる。送った直後に流す |
| 低 | 開発サーバーの操作ログの、前に書いた IPブロックの記録は、日報で期限が空になる。配る前なので気にしない |
| 低 | パッケージのマイグレーションを1本にまとめるかは、まだ決めていない（計画書の段階4-5-2） |

## F. セキュリティ・権限・データ整合性の注意

- 状態の列は、マイグレーションの中で今の値を番号に置き換えてから型を変えるので、今の企業の承認の状態は保たれる
- SEO設定のキャッシュを消すので、新しいキーで読み直される

## G. テスト状況

- 全部のテスト：545件のうち544件が通り、1件はスキップ（`FileWatchTest` の root でしか確かめられないもの）
- 手元の DB で、マイグレーションを流して列と状態の番号が変わること、戻して元に戻ることを確かめた
- 古い名前が、別物と前のマイグレーションのほかに残っていないことを、検索で確かめた
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

- 段階4-5-2 の残り（`.env` の名前・`settings` のキー・保存先と定数）を見る
- 名前の一覧の13章のテーブルに、`seo_metas`・`redirects`・`basic_auth_users` を足す
