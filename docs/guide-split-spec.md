# 仕様メモ：利用ガイドを機能ごとのファイルに分ける（guide-split）

- 実装プロンプト：`docs/prompt/guide-split.md`
- 実装完了日：2026-11-07
- 計画書の段階6
- 作業の前の状態：タグ `before-guide-split`

## A. 概要

**目的・解決する課題**

利用ガイド（`docs/neobit-framework-guide.md`）が3500行になり、全体を見渡しにくくなっていた。ほかの文書やコードのコメントが「利用ガイドの19章」のように章の番号で指していて、章を足すたびに指し先がずれるおそれもあった。入口と機能ごとのファイルに分け、番号ではなくファイルの名前で指す形にした。

ガイドはフレームワークの使い方なので、パッケージの中へ移し、パッケージと一緒に配る。案件からは `vendor/neobit/core/guide.md` と `vendor/neobit/core/docs/` で読める。

**変えたこと**

- 入口 `packages/neobit/core/guide.md`：はじめに、機能ごとの概要とリンク（8つのまとまり）、このガイドの直し方
- 機能ごとのファイル `packages/neobit/core/docs/*.md`（決まりと名前の一覧を含めて37本）：本文は書き直さずに移した。全体像の部品の一覧は、長いので `parts.md` に分けた
- 決まりは `rules.md` だけに置いた。全体像の「全体に共通する約束」「コメントの書き方」、トランザクション、テーブル・モデルの決まりは、`rules.md` と重なっていたので `rules.md` へまとめ、足りない分を足した
- 0章の設置と設定の節は、サーバーで行う作業（`deploy.md`）と、サイトの設定（`settings.md`）に分けた
- 見本のサイトの実例の表と自動テストは、memsys だけの話なので、入口から `examples.md` へ移した
- 版の履歴（第1版〜第6版）はやめ、`packages/neobit/core/CHANGELOG.md` を指す
- ほかの文書とコードのコメントの、章の番号での参照を、ファイルの名前にした
- フレームワークの決まり（`docs/neobit-framework-rules.md`）と名前の一覧（`docs/neobit-framework-names.md`）も、パッケージの `docs/rules.md`・`names.md` へ移した。案件へ写さず、利用ガイドと同じ版で届くようにするため

**変更したファイル**

| 場所 | ファイル |
|---|---|
| フレームワーク | `guide.md`・`docs/*.md`・`CHANGELOG.md`（新規）、`config/neobit/route.php`（コメント） |
| フレームワーク（決まりと名前の一覧） | `docs/rules.md`・`docs/names.md`（移した）、`config/neobit/file_watch.php`・`rebuild.php`、`src/Security/BasicAuth.php`、`stubs/config/neobit.php`（コメント） |
| 見本のサイト | `app/Models/Member.php`・`config/view.php`・`routes/console.php`（コメント） |
| 文書 | `docs/neobit-framework-guide.md`（削除）、`docs/project-overview.md`・`neobit-framework-rules.md`・`new-project-files.md`・`packaging-plan.md`・`security-external-check.md`・`security-issues.md`・`laravel-dependency.md`、仕様メモ12本 |

## B. 実装仕様

**章とファイル**

| 今の章 | ファイル | 今の章 | ファイル |
|---|---|---|---|
| 0（設置と設定の節） | `deploy.md`・`settings.md` | 16 | 決まりは `rules.md`、マイグレーションと id の始まりは `table.md` |
| 1 | `overview.md`・`parts.md`（部品の一覧） | 17 | `blade.md` |
| 2 | `new-corner.md` | 18 | `pdf.md` |
| 3 | `list.md` | 19 | `scheduler.md` |
| 4 | `detail.md` | 20 | `error-notify.md` |
| 5 | `form.md` | 21 | `queue.md` |
| 6 | `delete.md` | 22 | `operation-log.md` |
| 7 | `upload.md` | 23 | `member-types.md` |
| 8 | `code-table.md` | 24 | `access-restriction.md` |
| 9 | `csv-download.md` | 25 | `basic-auth.md` |
| 10 | `csv-import.md` | 26 | `ip-block.md` |
| 11 | `mail.md` | 27 | `seo.md` |
| 12 | `public-form.md` | 28 | `redirect.md` |
| 13 | `authorization.md` | 29 | `file-watch.md` |
| 14 | `login.md` | 30 | `public-files.md` |
| 15 | `rules.md` の「トランザクション」 | 31 | `wordpress.md` |
| | | 付録 | `security.md` |

0章の冒頭（このガイドについて）は入口へ、見本のサイトの機能の表と「まだ無い機能」（自動テストの今の状況と動かし方）は `examples.md` へ移した。「改版について」は、入口の「このガイドの直し方」に書き換えた。1章の「全体に共通する約束」と「コメントの書き方」は `rules.md` へ移し、全体像には動作条件とコントローラーの並び順を残した。

**入口のまとまり**

| まとまり | ファイル |
|---|---|
| はじめに | overview・parts・new-corner・rules・names・examples |
| コーナーを作る | list・detail・form・delete・table・blade |
| ファイルと入出力 | upload・code-table・csv-download・csv-import・pdf |
| メールとフォーム | mail・public-form・queue |
| ログインと権限 | authorization・login・member-types |
| 設置と運用 | deploy・settings・scheduler・error-notify・operation-log |
| サイトの守り | access-restriction・basic-auth・ip-block・file-watch・public-files・wordpress・security |
| 見せ方 | seo・redirect |

コードを書く人が読む順（全体像 → 部品 → 作る手順 → 各機能）に並べ、サーバーに置くときだけ読むものは「設置と運用」にまとめた。

**機能ごとのファイルの形**

- 1行目は `# 見出し`（章の番号を外した文言）。その下に右寄せの「利用ガイドへ戻る」（`<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>`）
- 見出しは1段ずつ上げた（`###` → `##`）。節の番号（`1-1.` など）は外した

**参照の書き方**

| 場所 | 書き方 |
|---|---|
| ガイドの中 | `[ip-block.md](ip-block.md)` のように、ファイルの名前でリンクする。コードの塊の中は、リンクにせず `login.md` と書く |
| 部品の一覧（`parts.md`） | 表をやめ、部品の名前の下に「場所」「概要」「詳細」の3行を並べるリストにした。詳細は、ファイルの名前のリンク |
| memsys の文書 | `利用ガイドの scheduler.md`。節を指すときは `利用ガイドの settings.md の「サイトの名前」` |
| コードのコメント | `利用ガイドの vendor/neobit/core/docs/scheduler.md`。案件でも memsys でも、そのまま開けるパス |
| 案件へ写す文書（`docs/project-overview.md`・`laravel-rules.md`・`new-project-files.md`） | `vendor/neobit/core/docs/rules.md` のように、案件の側のパス |
| パッケージの文書の中のパス | memsys のまま（`packages/neobit/core/src/…`）。入口に「案件では `vendor/neobit/core/` にある」と書いた |

## C. 前提・依存

- パッケージの追加・更新は無い
- 見本のサイトは、パッケージを `path` で入れているので、`vendor/neobit/core/docs/` からも同じファイルが見える

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 置き場所 | パッケージの中。入口はパッケージの直下（`CHANGELOG.md` と並ぶ `guide.md`）、ページは `docs/` | memsys の `docs/guide/`：案件に配られず、案件ごとに写すことになる。入口も `docs/` に置き、ページを `docs/guide/` に分ける：`docs/` の直下が入口だけになり、パスも1段長くなる |
| ファイル名 | 機能の名前だけ（`seo.md`） | 番号付き（`27-seo.md`）：章を足したり並べ替えたりすると、名前と参照が変わる |
| 版の履歴 | `CHANGELOG.md` を指す。第1版〜第6版の表は消す | 履歴のファイルに分ける：パッケージの版と別の番号が並び、どちらが正しいか分からなくなる。消した表は、受け取った案件が無いパッケージにする前の記録で、git に残っている |
| リンクの文言 | ファイルの名前 | 章の見出し：「操作ログ（[操作ログ](…)）」のように、前の語と重なるところが多い。memsys の文書とコードのコメントの書き方ともそろう |
| memsys の文書からの指し方 | 名前だけでリンクにしない | リンク：`docs/` の外を指すので、Obsidian で `docs/` だけを開いていると飛べない |
| 仕様メモ | memsys に残し、ガイドからはリンクしない | パッケージへ移す：なぜそう作ったかの記録で、案件には要らない |
| 決まりと名前の一覧 | パッケージへ移す | memsys の `docs/` に置いて案件へ写す：フレームワークの版が上がっても、案件の写しは古いまま残る |
| 決まりの置き場所 | `rules.md` だけ。ガイドのページからは指すだけにする | ガイドにも書く：同じことを2か所に書くと、片方だけ直して食い違う。読む側も、どちらを信じればよいか迷う |
| 設置と設定 | `deploy.md` と `settings.md` に分ける | 1つのファイル：いちばん引かれる「送った後のコマンド」と、サイトの名前や見た目の設定が混ざり、入口の1行で中身を言い表せない |
| 見本のサイトの表と自動テスト | `examples.md` に分ける | 入口に置く：memsys だけの話で、入口が地図として長くなる |
| コードのコメントのパス | `vendor/neobit/core/docs/…` | ファイルの名前だけ（`scheduler.md`）：`vendor/` は検索から外れることが多く、名前だけでは見つけにくい。memsys では `vendor/neobit/core` が `packages/neobit/core` を指すので、同じパスで開ける |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | ガイドの本文には、`docs/csv-import-spec.md` などの memsys の文書の名前が残っている。案件には無いファイルなので、案件からは読めない |
| 低 | `examples.md` は memsys のコントローラーの名前で書いている。案件では、memsys を見に行く前提 |
| 低 | 管理画面の「ベーシック認証」の注意書きと、改ざん監視の知らせのメールは、利用ガイドを名前（「改ざん監視」など）で指している。読む人向けの文なので、パスにしていない |
| 低 | `check_latest.sh` には足していない。サーバーの動きに関わるファイルだけを確かめるもので、文書はもともと見ていないため |

## F. セキュリティ・権限・データ整合性の注意

- 文書とコメントだけの変更で、動きは変わらない

## G. テスト状況

- 元のガイドの本文の行と、分けた後の全部のファイルの行を突き合わせ、見出しの段・戻るリンク・参照の書き換え・入口で足した文のほかに違いが無いことを確かめた
- 入口と機能ごとのファイルのリンクが、全部あるファイルを指していることを確かめた
- `git grep` で、ガイドの章の番号での参照と、古いファイル名が残っていないことを確かめた（仕様メモ自身の章を指す「4章」などは別のもの）
- コメントを直した PHP は、`pint --test` と `php -l` を通した
- 入口のまとまりに、`docs/` の全部のファイルが1回ずつ並んでいることを確かめた
- 分けた後に、ガイドの中身をコードと突き合わせる見直しを5回行い、無いファイル・メソッド・設定のキー、実際の動きと違う説明、名前の一覧の抜けを直した。最後の回で見つかったのは、画面の文言の空白と、操作ログの報告の説明の抜けだけだった

## H. 今後の改善提案・TODO

- 計画書の段階6の残り：`docs/new-project-files.md` の調べ直し、上書きの方法と `vendor:publish` のタグをガイドにまとめること
- 分けた後に、ディレクトリとルートの全体（`structure.md`）を「はじめに」へ足した
- 版を出す手順（`tools/release-core.sh`）を、ガイドの `deploy.md` か入口に案内する
