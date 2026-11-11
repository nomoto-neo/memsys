# 仕様メモ：テーブルからコーナーの下書きを作る（table2draft）

- 実装プロンプト：`docs/prompt/table2draft.md`
- 実装完了日：2026-11-12

## A. 概要

**目的・解決する課題**

開発の道具 `tools/table2rules.php` を `tools/table2draft.php` に改め、テーブルの定義から推測できるコーナーの下書きを、まとめて書き出せるようにした。今までは `rules()`・`formValues()`・`saveValues()` とモデルの `$fillable`・`$casts` だけで、アップロード・子テーブル・多対多・一覧・入力欄の画面は、人が一から書いていた。

書き出すのは下書きで、プロジェクトのファイルは書き換えない、という考え方は変えていない。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| 開発の道具 | `tools/table2rules.php` を `tools/table2draft.php` に名前を変え、書き出すものを広げた |
| 利用ガイド | `tools.md` の節を書き直した。`guide.md`・`form.md`・`new-corner.md` のツールの名前 |
| 外からの確認の手順 | `docs/security-external-check.md` の「5. 公開されてはいけないファイルが見えないか」の手順のパス。過去の記録のコマンドは変えていない |
| フレームワーク・見本のサイト | 変わらない |

## B. 実装仕様

**使い方**

```
php tools/table2draft.php t_members              tools/tmp/ に3つのファイルを書き出す
php tools/table2draft.php t_members t_news       いくつでも並べられる
php tools/table2draft.php --stdout t_members     ファイルに書かず、画面に出す（ファイルの名前を見出しにして続けて出す）
```

**書き出すファイル**（`tools/tmp/`。git の外）

| ファイル | 中身 |
|---|---|
| `テーブル名.controller.php` | クラスの形。冒頭のコメントに参考の CREATE 文。トレイトの `use`、一覧の定数（`INDEX_ROUTE`・`FREE_WORD_COLUMNS`・`PER_PAGE`・`ORDER_OPTIONS`）、`UPLOAD_FILES`、`CHILD_TABLES`、`rules()`・`formValues()`・`defaultValues()`・`searchRules()`・`applyCustomSearch()`・`csvColumns()`・`saveValues()`・`afterSave()`・`beforeDelete()` |
| `テーブル名.model.php` | クラスの形。`SoftDeletes`、画像の横幅の定数、`$table`・`$fillable`・`$casts`、`belongsTo`・`hasMany`・`belongsToMany` |
| `テーブル名.fields.blade.php` | 入力欄の画面。ニュースの `_fields.blade.php` と同じ書き方 |

- テーブル名の後ろは「.」で区切る。「_」だと、テーブル名の切れ目が分からないため
- コントローラーとモデルは、そのまま `php -l` が通る形にした。入口（`index`・`create` など）は書き出さない

**推測の決まり**

| もの | 決まり |
|---|---|
| アップロードの種類 | `photo`・`image`・`picture` を含む名前は画像、`file`・`filename`・`attach`・`attachment` を含む名前は添付ファイル。どちらでもなければ画像として TODO |
| 画像の横幅 | モデルに「列_WIDTH」（子テーブルは「組の名前_列_WIDTH」）の定数で 1000。TODO |
| 必須のアップロード | NULL を許さない列は `'required' => true` |
| 子テーブル | `t_` で始まるほかのテーブルのうち、「対象の単数形_id」の列を持つもの（中間テーブルを除く） |
| 中間テーブル | `id`・`created_at`・`updated_at` を除いた列が、`_id` で終わる2つだけのテーブル |
| 組の名前 | 子テーブルの名前から `t_` と親の名前を外したもの（`t_news_photos` は `photos`）。リレーションの名前は、そのキャメルケース |
| 組の書き方 | 並び順の列（`seq` など）があれば `'order'`、無ければ `'delete_insert' => true`。上限は `'max' => 10` で TODO |
| 組の項目 | 子テーブルの列から、`id`・親を指す列・並び順の列・`created_at`・`updated_at`・`列_origin`・入力させない列を除いたもの |
| 多対多 | `相手の単数形_ids` の配列を受け、`Rule::exists('相手のテーブル', 'id')`。`formValues()` は `pluck()`、`defaultValues()` は `[]`、`afterSave()` で `sync()`、`beforeDelete()` で `detach()` |
| `belongsTo` | `xxx_id` の数の列で、`t_xxxs` のテーブルがあるもの。`staff` のように複数形が同じ形の語のため、`s` を付けた名前も探す |
| 論理削除 | `deleted_at` の列があれば `SoftDeletes` |
| `defaultValues()` | DB の既定値がある入力の列。`CURRENT_TIMESTAMP` のような式は除く |
| フリーワード | `name`・`title`・`kana`・`subject` で終わる文字の列 |
| 一覧の検索 | 区分表のある列は配列で `IN()`、真偽の列は完全一致 |
| 並び順 | 更新日の新しい順・古い順（更新日が無ければ登録の順）。日付の列があれば、最初の1つの新しい順・古い順 |
| `csvColumns()` | 列に COMMENT が1つでもあるときだけ。見出しは COMMENT。区分表は名前に、真偽は「はい・いいえ」に、日付は書式付き。アップロードは表示名の列も並べる。最後に更新日時 |
| 項目の名前 | 列の COMMENT。無ければ列の名前で TODO |
| 入力欄 | 文字は1行の入力欄（メール・電話・URL・パスワードは名前から type を決める）、長い文は複数行、日付・日時・時刻・数は型の type、真偽は「1・0」のラジオボタン、区分表は `code_options()` の選択肢、アップロードは `_ajax_upload_block`、多対多はチェックボックスの並び、子テーブルは `_repeatable_group` |

- 推測の名前は、ツールの冒頭の定数（`IMAGE_NAMES`・`FILE_NAMES`・`ORDER_NAMES`・`FREE_WORD_NAMES`・`NAME_RULES` など）にまとめた
- 子テーブルの列も同じ `TableDraft` で読む。そのときは、その先のつながりは探さない

## C. 前提・依存

- Laravel を起動して、`.env` の DB を読む。`Schema::getTables()`・`getColumns()`・`getIndexes()` を使う
- CREATE 文は MySQL・MariaDB の `SHOW CREATE TABLE` で取る。ほかの DB では、取り出せない旨を書く
- パッケージの追加はしていない

## D. 設計判断とトレードオフ

- **クラスの形で書き出す**：断片のテキストだと、エディタの色分けが効かず、貼るときにどこからどこまでかが分かりにくい。`php -l` が通る形にして、メソッドを丸ごと選んで貼れるようにした
- **組の名前は親の名前を外す**：`t_` だけを外すと、`$news->news_photos` のように親の名前が重なり、実際のテーブルの名前と結び付いているように見えるため。見本のサイトの `photos`・`careers` とも合う
- **子テーブルは一律に書き出す**：`t_company_users` のように、別のコーナーで更新するテーブルも子テーブルとして見つかる。見分ける手がかりがテーブルの定義に無いので、`CHILD_TABLES` の説明に「ほかのコーナーで更新するテーブルには使わない」と書くことにした
- **`RAW_INPUT_FIELDS` は書き出さない**：入力をそろえない項目は、名前からの推測が外れやすいため
- **一覧の画面は書き出さない**：検索フォームと表は、コーナーごとの違いが大きいため。コントローラーの定数と `searchRules()` までにした
- **`csvColumns()` は COMMENT があるときだけ**：見出しが無いと、ほとんどが TODO になるため

## E. 課題・懸念点

- 見本のサイトのテーブルには COMMENT が無い。COMMENT の付いた列の書き出しは、テスト用の DB に試しのテーブルを作って確かめた
- 子テーブルの見つけ方は名前の決まりに頼る。「対象の単数形_id」でない名前で親を指すテーブルは見つからない
- 多対多の相手のテーブルの名前は、`_id` の前の部分の複数形から作る。決まりから外れた名前のテーブルは、`Rule::exists()` の名前を直す必要がある

## F. セキュリティ・権限・データ整合性の注意

- 下書きを書き出すだけで、DB とプロジェクトのファイルは書き換えない
- 書き出し先の `tools/tmp/` は `.gitignore` に入っている。改ざん監視は `tools/` を見ていない
- `tools/table2draft.php` はサーバーの画面からは呼ばれない。外からの確認の手順で、公開されていないことを確かめる対象のパスを新しい名前に直した

## G. テスト状況

- 自動テストは無い（開発の道具のため）。次を手で確かめた
- 見本のサイトの `t_news`・`t_members`・`t_companies`・`t_staffs`・`t_categories`・`t_inquiries` で書き出し、書き出したコントローラーとモデルが `php -l` を通ること、入力欄の画面を Blade でコンパイルした結果が `php -l` を通ること
- `t_news`：子テーブル `attachments`・`photos` と、多対多 `categories` が出る。`t_news_category` は子テーブルに入らない。`photos` は `'order' => 'seq'`、`attachments` は `'delete_insert' => true`
- `t_members`：子テーブル `careers` と、`staff_id` の `belongsTo` が出る
- `t_companies`：子テーブル `users` が出る
- `t_staffs`：`SoftDeletes` が付く
- テスト用の DB に COMMENT の付いた試しのテーブル（子テーブル・id の無い中間テーブル付き）を作り、`csvColumns()`・並び順の名前・`defaultValues()`・区分表の検索・ファイルの子テーブルが出ることを確かめた
- Pint を通した

## H. 今後の改善提案・TODO

- 子テーブルの行の部分ビュー（`_photos_row` など）も書き出せる。今は名前を TODO にしている
- `xxx_id` の列で相手のテーブルがあるときは、`Rule::exists()` を足せる。今は「行があるかを確かめる」のコメントだけ
