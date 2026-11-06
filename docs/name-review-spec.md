# 仕様メモ：案件が頼る名前の総点検（name-review）

- 実装プロンプト：`docs/prompt/name-review.md`
- 実装完了日：2026-11-02
- 計画書の段階4-5

## A. 概要

**目的・解決する課題**

配った後に名前を変えると、全案件のコードを直すことになる。版を出す前に、案件が頼る名前のうち、分かりにくいものと形がそろっていないものを直して固める。動きは変えていない。

**変えた名前**

| 今の名前 | 前の名前 | 理由 |
|---|---|---|
| `searchRules()` | `srchRules()` | ここだけ略していた |
| `operationLogActionType($created)` | `savedLogAction($created)` | 操作の種類を返すだけで、保存はしない。戻り値の型の名前とそろう |
| `is_match($value, $key)` | `hit($value, $key)` | 何に当たるのか読み取れなかった |
| `CsvRead` | `CsvReader` | `CsvDownload`・`CsvImport` とそろえた |
| `HasTeamInvitations` | `TeamInvitationRelation` | `HasPasskeys`・`HasSeoMeta` とそろえた |
| メールの変数 `$name` | `$member_name` | ほかのテンプレートとそろえた |
| メールの変数 `$to_mail`・`$site_name` | `$to`・`$app_name` | 同じ |
| `LoginVerificationCode` の `$accountClass` | `$memberClass` | 会員以外のモデルも渡す |
| コマンドの頭 `neobit:` | `app:` | 案件のコマンドと見分ける |
| `Gatekeeper` と、その関連の名前（下の表） | `Gate` | 名前が広すぎるうえ、Laravel の権限の部品 `Gate` と同じ名前で、`allows()` まで同じだった |

**変更したファイル**

| 場所 | ファイル |
|---|---|
| 見本のサイト | `app/`（コントローラー・`Company`）・`resources/views/admin/`（一覧の画面）・`resources/mail-templates/` |
| フレームワーク | `packages/neobit/core/`（`Crud`・`Csv`・`Team`・`Auth`・`Console`・`Security`・`Operations`・`Http`・`helpers.php`・設定・画面・メール） |
| テスト | `tests/Feature/` |
| 道具と文書 | `tools/tpl2blade.php`・`check_latest.sh`・利用ガイド・名前の一覧・計画書・名前を書いている仕様メモ |

## B. 実装仕様

**コマンド**

`neobit:access-denied-off`・`neobit:cleanup-temporary-data`・`neobit:file-watch`・`neobit:gatekeeper`・`neobit:report-operation-logs`・`neobit:reset-two-factor`・`neobit:set-next-id` の7つ。引数とオプションは変えていない。

**入口の見張り**

| 今の名前 | 前の名前 |
|---|---|
| `neobit:gatekeeper` | `neobit:gate` |
| `Gatekeeper`・`GatekeeperState`・`CheckGatekeeper` | `Gate`・`GateState`・`CheckGate` |
| `packages/neobit/core/gatekeeper.php` | `packages/neobit/core/gate.php` |
| `NEOBIT_GATEKEEPER_STATE`・`NEOBIT_GATEKEEPER_DONE` | `NEOBIT_GATE_STATE`・`NEOBIT_GATE_DONE` |
| `storage/app/gatekeeper/` | `storage/app/gate/` |
| 設定 `neobit.wordpress.gatekeeper`、`.env` の `WORDPRESS_GATEKEEPER` | `neobit.wordpress.gate`、`WORDPRESS_GATE` |

画面とコメントの言葉「入口の見張り」は変えていない。

**ヘルパー**

`is_match()` の動きと引数の順番は、前のままにした。入力値が1つなら同じ値か、配列ならその中にあるかを返し、入力値が空なら合わない。

**メールのテンプレート**

パッケージのテンプレートは、宛先を `$to_mail`、サイトの名前を `$site_name`、相手の名前を `$name` で受け取る。案件が同じ名前のテンプレートを `resources/mail-templates/` に置くときも、この変数を使う。

## C. 前提・依存

- パッケージの追加・更新は無い
- 名前を探すときに、別物と紛れないようにした。`LoginThrottle::hit()`・`RedirectResolver::hit()`・`RateLimiter::hit()`、`FileWatcher` の記録のキー `to`（期間の終わり）、`LoginVerificationCode` の状態のキー `member_type`・`member_id` は変えていない

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| `hit()` の扱い | 名前だけ変える | `@selected`・`@checked` を2つの引数で動くように置き換える：Laravel の標準の命令が違う動きになる。`@selectedValue` などの専用の命令：値を返さないのに `Value` は不自然で、使う場所も少ない |
| `hit()` の新しい名前 | `is_match` | `is_selected`：`@selected(is_selected(…))` と重なり、チェックボックスでは言葉が食い違う。`is_hit`：何に当たるのかが分かりにくいまま |
| `uploadAjaxFile()` | 変えない | `ajaxUploadFile()`：`downloadCsv()`・`commitUploads()` などの「動詞＋対象」の形から外れる。`ajaxUpload〜` は欄のルールと値の名詞で、役割が違う |
| `savedLogAction()` の新しい名前 | `operationLogActionType` | `logActionOnSave`：「Save」が入ると、処理を行うように読める |
| 一覧の定数（`INDEX_ROUTE`・`PER_PAGE`・`ORDER_OPTIONS`・`FREE_WORD_COLUMNS` など） | 変えない | 機能の名前を頭に付ける：意味は分かる名前で、長くなるだけ |
| `required-mark`、`code_tables` と `code_table()` | 変えない | CSS のクラスとテーブルは使う場所が重ならず、設定のキーは `code_table.db` に変わって紛れなくなった |
| `FrameworkRoutes` とそのメソッド | 変えない | 見直しの候補に挙げていない。何をするクラスかが名前で分かる |
| 入口の見張りの名前 | `Gatekeeper` | `EntryGate`：判断する役より場所の名前に読める。`EntryGuard`：Laravel のガードと紛れる。`EntryFilter`：`filter` は「外す」と逆に読まれやすい |
| 入口の見張りの範囲 | クラス・ファイル・定数・設定まで全部 | コマンドだけ：中身の `Gate` の紛らわしさが残る |
| `IpBlocker` | 変えない | 404 の連発を数えて止める一覧に足す部品で、名前のとおりの働き。見張りは止める一覧を見るだけ |
| コマンドの頭 | 今変える | 変えない：配った後は全部のサーバーの cron を直すことになる |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | サーバーの cron の `app:` を `neobit:` に書き換える必要がある。送るのと同時に行わないと、コマンドが無いというエラーが MAILTO へ届く |
| 中 | 送った後、`neobit:gatekeeper --export` を流すまでは、新しい場所に状態のファイルが無く、入口の見張りが全部を通す。送った直後に流す |
| 低 | サーバーに `CsvReader.php`・`TeamInvitationRelation.php`・`gate.php`・`Gate.php`・`GateState.php`・`CheckGate.php`・`storage/app/gate/` が残る。読み込まれないが、消しておく |

## F. セキュリティ・権限・データ整合性の注意

- 改ざん監視の cron も名前が変わる。書き換えるまでの間は監視が動かないので、送るのと同時に書き換える
- `check_latest.sh` は新しいファイルの名前で確かめる

## G. テスト状況

- 全部のテスト：542件のうち541件が通り、1件はスキップ（`FileWatchTest` の root でしか確かめられないもの）
- `php artisan list` で、`neobit:` のコマンドが7つあり、`app:` のコマンドが無いことを確かめた
- 古い名前が、別物のほかにコードと文書（`docs/prompt/` を除く）に残っていないことを、検索で確かめた
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

- 新しくコマンドや案件が頼る名前を足すときは、`packages/neobit/core/docs/names.md` に足す
