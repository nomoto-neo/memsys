# 仕様メモ：フレームワークの設定を機能ごとに分け、案件を1ファイルにする（config-split）

- 実装プロンプト：`docs/prompt/config-split.md`
- 実装完了日：2026-11-02
- 計画書の段階4-7

## A. 概要

**目的・解決する課題**

- `neobit.php` に機能が混ざり、アップロードと片付けだけ別のファイルで、分け方の決まりが無かった
- `form.php` は、フレームワークが読むのにパッケージに既定の値が無く、案件に無いと必須マークが黙って空になった
- 許す IP と信頼するプロキシが Laravel の `app.php` にあった
- 案件が既定の値を写していると、変えた値と写しただけの値が見分けられず、パッケージの更新で変わった既定の値も効かない

**変えたこと**

- 既定の値を、パッケージの `config/neobit/` に機能ごとのファイルで持つようにした
- 案件は `config/neobit.php` の1ファイルに、変える値だけを機能ごとの節で書くようにした
- `config/upload.php`・`cleanup.php`・`form.php` を無くし、`app.php` から許す IP と信頼するプロキシを移した

動きと `.env` の書き方は変えていない。

**変更したファイル**

| 場所 | ファイル |
|---|---|
| 見本のサイト | `config/neobit.php`・`config/app.php`。`config/upload.php`・`cleanup.php`・`form.php` を削除。キーが変わるものの呼び出し |
| フレームワーク | `config/neobit/*.php`（新規）・`stubs/config/neobit.php`・`NeobitServiceProvider.php`。`config/neobit.php`・`upload.php`・`cleanup.php` と、`stubs` の `upload.php`・`cleanup.php` を削除。キーが変わるものの呼び出し |
| テスト | `tests/Feature/ConfigSectionsTest.php`（新規）・`ConfigStubTest.php`。キーが変わるもの |
| 道具と文書 | `check_latest.sh`・利用ガイド・`docs/neobit-framework-names.md`・`docs/new-project-files.md`・`docs/project-overview.md`・計画書・設定のキーやパスを書いている仕様メモ |

## B. 実装仕様

**読み方**：`NeobitServiceProvider::register()` が、節ごとに `mergeConfigFrom(ファイル, "neobit.節")` を呼ぶ。案件の `config/neobit.php` の節に書いたキーが、既定に勝つ。混ぜるのは節の中の1段目のキーだけで、その中の配列は丸ごと置き換わる。

**キー**

| パッケージのファイル | キー |
|---|---|
| `route.php` | `neobit.route.admin_path` |
| `layouts.php` | `neobit.layouts.admin`・`app` |
| `form.php` | `neobit.form.required_mark.admin`・`public` |
| `code_table.php` | `neobit.code_table.db`・`method`・`enum_namespaces` |
| `operation_log.php` | `neobit.operation_log.actions`・`models`・`route`・`report_to`・`report_thresholds` |
| `security.php` | `neobit.security.admin_requests_per_minute`・`admin_requests_exceeded_message`・`ip_block_auto`・`ip_block_watch_minutes`・`ip_block_watch_count`・`ip_block_hours`・`trusted_proxies`・`site_allowed_ips`・`admin_allowed_ips` |
| `file_watch.php` | `neobit.file_watch.release_file`・`to`・`public_upload_extra` |
| `seo.php` | `neobit.seo.*` |
| `schedule.php` | `neobit.schedule.cron_enabled` |
| `upload.php` | `neobit.upload.*` |
| `cleanup.php` | `neobit.cleanup.*` |

**WordPress**：既定の値を持たない。案件の `config/neobit.php` に `wordpress` の節があることが「WordPress を置く」の印で、無ければ `config('neobit.wordpress')` は null になる。

## C. 前提・依存

- Laravel の `mergeConfigFrom()` が、ドットを含むキーでも動くこと。設定のキャッシュがあるときは混ぜず、`config:cache` が混ぜた後の値を保存する
- パッケージの追加・更新は無い

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 案件のファイル | `config/neobit.php` の1つ | `config/neobit/機能.php`：案件のファイルが増える。`neobit_upload.php` のように頭を付けて並べる：同じく増え、`config/` が散らかる |
| 既定の値 | パッケージの中で機能ごとに分ける | 1ファイルのまま：機能が混ざり探しにくい |
| 案件のファイルの名前 | `neobit.php` | `packages.php`：案件のフレームワークは `vendor/neobit/core/` にあり、`packages/` は開発用の memsys にしかない |
| 許す IP・信頼するプロキシ | `neobit.security` | `app.php` のまま：フレームワークの設定が Laravel のファイルに散る |
| `login.php`・`contact.php` | 案件のファイルのまま | フレームワークへ移す：案件ごとの機能やデータの移行の設定で、フレームワークの既定が無い |
| 既定の見本 | 既定の値の説明はパッケージのファイルに書き、`stubs` は節の並びと WordPress の節だけ | 全部の説明を3か所に写す：直し漏れる |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | サーバーと案件の `config/neobit.php` は、節の形へ書き直しが要る。古いキーのまま残すと、黙って既定の値になる |
| 低 | 案件が節の中の配列を書くと、そのキーについてはパッケージの更新が効かない。版ごとの変更の記録で案内する（段階5） |

## F. セキュリティ・権限・データ整合性の注意

- 許す IP と信頼するプロキシは、キーの場所が変わっただけで、値は `.env` から読む。送った後に `config:cache` と `app:gate --export` を流さないと、入口の見張りの写しが古いままになる
- サーバーの `config/neobit.php` を送るときは、独自の値を消さないよう、先に控える

## G. テスト状況

- `ConfigSectionsTest`：節に書いたキーが勝ち、書いていないキーと書いていない節は既定の値になること。`wordpress` は節が無ければ null。古いキーが無いこと
- `ConfigStubTest`：写したままの見本は値を持たず、全部の節を見せていること
- 手元で `config:cache` を流し、節に書いたキーと既定の値がキャッシュに入ること、`wordpress` のキーが無いことを確かめた
- 開発サーバーで、管理画面が今までの URL で開くこと、`app:gate` の許す IP の件数が変わらないこと、必須マーク、アップロード、改ざん監視の画面を確かめた

## H. 今後の改善提案・TODO

- 配布の形（段階5）で、版ごとの変更の記録に、案件の節へ書いた配列を見比べる案内を書く
