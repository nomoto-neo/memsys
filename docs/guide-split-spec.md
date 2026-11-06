# 仕様メモ：利用ガイドを機能ごとのファイルに分ける（guide-split）

- 実装プロンプト：`docs/prompt/guide-split.md`
- 実装完了日：2026-11-07
- 計画書の段階6
- 作業の前の状態：タグ `before-guide-split`

## A. 概要

**目的・解決する課題**

利用ガイド（`docs/neobit-framework-guide.md`）が3500行になり、全体を見渡しにくくなっていた。ほかの文書やコードのコメントが「利用ガイドの19章」のように章の番号で指していて、章を足すたびに指し先がずれるおそれもあった。入口と機能ごとのファイルに分け、番号ではなくファイルの名前で指す形にした。

ガイドはフレームワークの使い方なので、パッケージの中へ移し、パッケージと一緒に配る。案件からは `vendor/neobit/core/docs/` で読める。

**変えたこと**

- 入口 `packages/neobit/core/docs/guide.md`：はじめに、機能ごとの概要とリンク（8つのまとまり）、見本のサイトにある機能、まだ無い機能、このガイドの直し方
- 機能ごとのファイル `packages/neobit/core/docs/guide/*.md`（33本）：本文は書き直さずに移した
- 版の履歴（第1版〜第6版）はやめ、`packages/neobit/core/CHANGELOG.md` を指す
- ほかの文書とコードのコメントの、章の番号での参照を、ファイルの名前にした

**変更したファイル**

| 場所 | ファイル |
|---|---|
| フレームワーク | `docs/guide.md`・`docs/guide/*.md`・`CHANGELOG.md`（新規）、`config/neobit/route.php`（コメント） |
| 見本のサイト | `app/Models/Member.php`・`config/view.php`・`routes/console.php`（コメント） |
| 文書 | `docs/neobit-framework-guide.md`（削除）、`docs/project-overview.md`・`neobit-framework-rules.md`・`new-project-files.md`・`packaging-plan.md`・`security-external-check.md`・`security-issues.md`・`laravel-dependency.md`、仕様メモ12本 |

## B. 実装仕様

**章とファイル**

| 今の章 | ファイル | 今の章 | ファイル |
|---|---|---|---|
| 0（設置と設定の節） | `setup.md` | 16 | `table-model.md` |
| 1 | `overview.md` | 17 | `blade.md` |
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
| 15 | `transaction.md` | 31 | `wordpress.md` |
| | | 付録 | `security.md` |

0章の冒頭（このガイドについて、見本のサイトの機能の表）と「まだ無い機能」は入口へ移した。「改版について」は、入口の「このガイドの直し方」に書き換えた。

**機能ごとのファイルの形**

- 1行目は `# 見出し`（章の番号を外した文言）。その下に右寄せの「利用ガイドへ戻る」（`<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>`）
- 見出しは1段ずつ上げた（`###` → `##`）。節の番号（`1-1.` など）は外した

**参照の書き方**

| 場所 | 書き方 |
|---|---|
| ガイドの中 | `[ip-block.md](ip-block.md)` のように、ファイルの名前でリンクする。コードの塊の中は、リンクにせず `login.md` と書く |
| 全体像の部品の一覧 | 表をやめ、部品の名前の下に「場所」「概要」「詳細」の3行を並べるリストにした。詳細は、ファイルの名前のリンク |
| memsys の文書・コードのコメント | `利用ガイドの scheduler.md`。節を指すときは `利用ガイドの setup.md の「サイトの名前」` |

## C. 前提・依存

- パッケージの追加・更新は無い
- 見本のサイトは、パッケージを `path` で入れているので、`vendor/neobit/core/docs/` からも同じファイルが見える

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 置き場所 | パッケージの中（`packages/neobit/core/docs/`） | memsys の `docs/guide/`：案件に配られず、案件ごとに写すことになる |
| ファイル名 | 機能の名前だけ（`seo.md`） | 番号付き（`27-seo.md`）：章を足したり並べ替えたりすると、名前と参照が変わる |
| 版の履歴 | `CHANGELOG.md` を指す。第1版〜第6版の表は消す | 履歴のファイルに分ける：パッケージの版と別の番号が並び、どちらが正しいか分からなくなる。消した表は、受け取った案件が無いパッケージにする前の記録で、git に残っている |
| リンクの文言 | ファイルの名前 | 章の見出し：「操作ログ（[操作ログ](…)）」のように、前の語と重なるところが多い。memsys の文書とコードのコメントの書き方ともそろう |
| memsys の文書からの指し方 | 名前だけでリンクにしない | リンク：`docs/` の外を指すので、Obsidian で `docs/` だけを開いていると飛べない |
| 仕様メモ | memsys に残し、ガイドからはリンクしない | パッケージへ移す：なぜそう作ったかの記録で、案件には要らない |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | ガイドの本文には、`docs/csv-import-spec.md` などの memsys の文書の名前が残っている。案件には無いファイルなので、案件からは読めない |
| 低 | 入口の「見本のサイトにある機能」は memsys のコントローラーの名前で書いている。案件では、memsys を見に行く前提 |
| 低 | `check_latest.sh` には足していない。サーバーの動きに関わるファイルだけを確かめるもので、文書はもともと見ていないため |

## F. セキュリティ・権限・データ整合性の注意

- 文書とコメントだけの変更で、動きは変わらない

## G. テスト状況

- 元のガイドの本文の行と、分けた後の全部のファイルの行を突き合わせ、見出しの段・戻るリンク・参照の書き換え・入口で足した文のほかに違いが無いことを確かめた
- 入口と機能ごとのファイルのリンクが、全部あるファイルを指していることを確かめた
- `git grep` で、ガイドの章の番号での参照と、古いファイル名が残っていないことを確かめた（仕様メモ自身の章を指す「4章」などは別のもの）
- コメントを直した PHP は、`pint --test` と `php -l` を通した

## H. 今後の改善提案・TODO

- 計画書の段階6の残り：`docs/new-project-files.md` の調べ直し、上書きの方法と `vendor:publish` のタグをガイドにまとめること、`docs/project-overview.md` のディレクトリの表
- 版を出す手順（`tools/release-core.sh`）を、ガイドの `setup.md` か入口に案内する
