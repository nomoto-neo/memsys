# 仕様メモ：マイグレーションを作る形だけにまとめる（migration-squash）

- 実装プロンプト：`docs/prompt/migration-squash.md`
- 実装完了日：2026-11-07
- 計画書の段階5

## A. 概要

**目的・解決する課題**

案件の DB は、流したマイグレーションのファイル名を `migrations` テーブルに記録する。配った後にファイルをまとめると、案件の DB と食い違う。最初の版（`v1.0.0`）を出す前に、列の追加や名前の変更を重ねてきたマイグレーションを、作る形だけにまとめた。まだリリースした案件は無いので、パッケージにする前の形からの移し替えも消した。

**変えたこと**

- パッケージ：8本を、`2026_10_13_000000_create_neobit_core_tables.php` の1本にまとめた
- 見本のサイト：テーブルごとに、作るマイグレーション1本にまとめた。列の追加・名前の変更・型の変更・ファイルの移し替えの16本を消した
- できるテーブルの形は変えていない

**変更したファイル**

| 場所 | ファイル |
|---|---|
| フレームワーク | `database/migrations/2026_10_13_000000_create_neobit_core_tables.php`（書き直し）。7本を削除 |
| 見本のサイト | `database/migrations/` の、`t_members`・`t_categories`・`t_news`・`t_news_attachments`・`t_bulk_mails`・`t_companies`・`t_company_users` を作るマイグレーション（書き直し）。16本を削除 |
| 道具と文書 | `check_latest.sh`・計画書 |

## B. 実装仕様

**パッケージ**：`code_tables`・`operation_logs`・`csv_download_logs`・`csv_import_logs`・`trusted_devices`・`two_factor_backup_codes`・`two_factor_secrets`・`passkeys`・`team_invitations`・`ip_blocks`・`settings`・`seo_metas`・`redirects`・`basic_auth_users` を作る。`t_codes` からの移し替えと、`hasTable()` で作るのを飛ばす確かめは消した。

**見本のサイト**：残したファイルと、まとめ込んだもの。

| 残したファイル | まとめ込んだもの |
|---|---|
| `…190005_create_t_members_table` | `withdrawn_at` を消したこと、`photo`・`staff_memo`・`legacy_password`・`notice_mail`、`birthday` |
| `…190008_create_t_categories_table` | `display_order` を `seq` にしたこと |
| `…190009_create_t_news_table` | `members_only`・掲載期間 |
| `…190011_create_t_news_attachments_table` | `file`・`file_origin`（NULL を許す）、`seq` を持たないこと |
| `2026_10_04_102658_create_t_bulk_mails_table` | `status` を数値に |
| `2026_10_05_145301_create_t_companies_table` | `status` を数値に、`ceo_name` |
| `2026_10_05_145303_create_t_company_users_table` | メールアドレスの一意の索引 |

ファイルを非公開の場所へ移したマイグレーション（2本）は、まっさらな DB では移すものが無いので消した。

**消したファイルの記録を消す SQL**（すでに流した DB で、人が行う）

```sql
DELETE FROM migrations WHERE migration IN (
  '2026_10_15_000000_create_ip_blocks_table',
  '2026_10_16_000000_create_settings_table',
  '2026_10_21_000000_rename_sort_order_to_seq_on_code_tables_table',
  '2026_10_25_000000_create_seo_metas_table',
  '2026_10_27_000000_create_redirects_table',
  '2026_10_28_000000_create_basic_auth_users_table',
  '2026_11_05_000000_rename_columns_on_neobit_core_tables',
  '2026_09_27_100001_drop_withdrawn_at_from_t_members_table',
  '2026_09_30_100001_change_original_name_nullable_on_t_news_attachments_table',
  '2026_10_03_163426_add_photo_to_t_members_table',
  '2026_10_03_165725_move_inquiry_attach_files_to_private_disk',
  '2026_10_03_174749_add_members_only_to_t_news_table',
  '2026_10_03_174750_move_news_files_to_private_disk',
  '2026_10_03_193327_add_publish_period_to_t_news_table',
  '2026_10_04_181114_add_staff_memo_to_t_members_table',
  '2026_10_05_131909_add_legacy_password_to_t_members_table',
  '2026_10_07_023841_add_notice_mail_to_t_members_table',
  '2026_10_12_130000_add_unique_email_to_t_company_users_table',
  '2026_10_21_000000_rename_columns_on_t_news_attachments_table',
  '2026_10_21_100000_rename_display_order_to_seq_on_t_categories_table',
  '2026_10_21_200000_rename_display_order_to_seq_on_news_child_tables',
  '2026_10_21_300000_drop_seq_on_t_news_attachments_table',
  '2026_11_05_000000_rename_columns_on_member_tables'
);
```

## C. 前提・依存

- パッケージの追加・更新は無い
- まだリリースした案件が無いこと。パッケージにする前の形（タグ `v5`）から作った DB は、もう移し替えられない

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| まとめた後のファイルの名前 | 作る側のファイルの名前のまま | 新しい名前：すでに流した DB で、同じテーブルをもう一度作ろうとして止まる |
| 前の形からの移し替え | 消す | 残す：リリースした案件が無く、まっさらな DB に作るだけなら読みにくくなるだけ |
| 見本のサイト | テーブルごとに1本 | 1本にまとめる：テーブルを足し引きするとき、案件が写して直しにくい |
| 消したファイルの記録 | 人が SQL で消す | 残す：害は無いが、DB の記録とファイルが食い違ったままになる |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | 消したファイルの記録は、`migrate:status` には出ない（ファイルの一覧だけを出すため）。残っていても動きは変わらない |
| 低 | 仕様メモのうち、消したマイグレーションの名前を書いているもの（`docs/column-names-spec.md` など）は、その時の記録として残している |

## F. セキュリティ・権限・データ整合性の注意

- テーブルの形を変えていないので、すでにある DB のデータには触れない
- 確かめのために作り直したのは、テスト用の DB（`memsys_testing`）だけ

## G. テスト状況

- テスト用の DB で、まとめる前と後に `migrate:fresh` で作った36テーブルの形（`SHOW CREATE TABLE`）を比べ、同じだった
- 手元の `memsys_local` で `php artisan migrate --pretend` を流し、流すものが無かった
- 全部のテスト：552件のうち551件が通り、1件はスキップ（`FileWatchTest` の root でしか確かめられないもの）
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

- 最初の版を出した後は、マイグレーションをまとめない。変えるときは、足すマイグレーションを書く
