# 仕様メモ：添付ファイルを繰り返しの組に移す（repeatable-group-attach）

- 実装プロンプト：`docs/prompt/repeatable-group-attach.md`
- 実装完了日：2026-10-21

## A. 概要

**目的・解決する課題**

ニュースの添付ファイル（`'attach.*'`）を、繰り返しの組（`RepeatableGroup`。`docs/repeatable-group-spec.md`）の、ファイルだけの組に移した。あわせて、今の複数の欄だけが持っていた「ファイルをまとめて選ぶと、行を足しながら順に入れる」動きを組に移し、複数の欄の仕組みをフレームワークから外した。繰り返しの仕組みを1つにまとめ、配布の前に覚えることを減らすため。

`t_news_attachments` の列（`filename`・`original_name`）は、アップロードの欄の決まり「名前」「名前_origin」から外れていたので、組の決まり（`file`・`file_origin`）に揃えた。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | 複数の欄（`_ajax_upload_group`・`MaxUploadCountRule`・作り直しの保存・`'名前.*'` の書き方）を外した。まとめて選ぶ動きを、ファイルの項目が1つだけの組に移した |
| 見本のサイト | 添付ファイルの子テーブルの列の名前を変え、並び順の列を足した。管理画面の添付ファイルの欄を組にした |
| CSV | ニュースの添付ファイルの列は、書き出しだけになった（取り込みは組の取り込みを足すまで止まる） |

## B. 実装仕様

**定義**（`Admin\NewsController`）

```php
private const UPLOAD_FILES = [
    'list_image' => ['type' => UploadType::Image, 'width' => News::LIST_IMAGE_WIDTH],
    'attach.*.file' => ['type' => UploadType::File],
    'photos.*.image' => ['type' => UploadType::Image, 'width' => News::PHOTO_WIDTH],
];

private const REPEATABLE_GROUPS = [
    'attach' => ['relation' => 'attach', 'fields' => ['file'], 'order' => 'seq', 'max' => 5],
    'photos' => [...],
];
```

- 画面から送る名前は `attach_group`・`attach_id[]`・`attach_file[]`・`attach_file_tmp[]`・`attach_file_origin[]`・`attach_file_del[]`。
- 行の部分ビュー `admin/news/_attach_row.blade.php` は、`_ajax_upload_block`（`'field' => 'attach_file'`）を1つ置くだけ。
- 非公開のファイルの欄の名前は `attach_file`（`News::PRIVATE_FILE_FIELDS`）。表示の URL は `NewsAttachment::url`。

**`UPLOAD_FILES` の書き方**（`UploadFieldDefinitions`）

| 書き方 | 扱い |
|---|---|
| `'名前'` | 1つだけの欄（`kind` は `single`） |
| `'組.*.列'` | 組の中の欄（`kind` は `group`）。列が組の `fields` に無ければ `LogicException` |
| `'名前.*'` | `LogicException`。「REPEATABLE_GROUPSに組を書き、UPLOAD_FILESに「組の名前.*.列」で書いてください」と案内する |
| キー | `type`・`width` だけ。`max` は使えないキーとして `LogicException`（行の数の上限は組の `'max'`） |

- 欄の定義に `multiple` を持たせた。組の中の欄で、その組のファイルの欄が `UPLOAD_FILES` に1つだけのとき true。`upload_field()` の項目は `type`・`width`・`multiple`。
- `_ajax_upload_block` は、`multiple` の欄のファイルの input に `multiple` を付ける。枠の×は外した（行の×は組の部品が出す）。

**まとめて選ぶ**（`ajax_upload.js`）

- 対象は、`multiple` の付いた欄で、`.repeatable_group` の中にあるもの。
- 1つ目は選んだ行に、2つ目から先は、ほかの行の同じ欄の空いているものに、画面の順で入れる。空いている欄が無ければ、送って受け付けられてから行を足して入れる。1つずつ順に送る。
- 行は `repeatable:add-row` のイベント（`detail.row` に足した行を入れて返す）で `repeatable_group.js` に足してもらう。上限で足せなければ、残りは送らない。
- 足せなかった数は、最後にまとめて知らせる：「ファイルは5件までです。2件は追加しませんでした。」
- 「ファイルを削除」は、ファイルだけの組（`_repeatable_group` が `data-files-only="1"` を付ける）で行が2つ以上あれば、行ごと消す（`repeatable:remove-row` のイベントで `repeatable_group.js` に頼む）。最後の1行と、ほかの項目がある組の行は、欄を空にするだけで行は残す。
- 行の × は、行が2つ以上あれば行ごと消す。最後の1行は消さずに、ひな型の空の行に置き換える（行の id・ファイルの欄の hidden・`type="text"`・`textarea` がまとめて空になる）。空の行は保存のときに除かれるので、今の行は削除になる。そのため、行が1つだけのときも × を出す。

**フレームワークから外したもの**

| 外したもの | 代わり |
|---|---|
| `resources/views/_ajax_upload_group.blade.php` | `_repeatable_group` と、行の部分ビューの `_ajax_upload_block` |
| `src/Rules/MaxUploadCountRule.php` | 組の `'max'`（`repeatableGroupRules()`） |
| `AjaxFileUpload` の複数の欄の検証・値の組み立て・作り直しの保存・削除・ファイル名の一覧 | 組の部品（`RepeatableGroup`） |
| `UploadedFileController` の `filename` の列の照合 | 組の欄の照合（欄の名前を「_」で区切ってリレーションと列を探す） |
| `global.css` の `.ajax_upload_inline` | `.repeatable_inline` |
| `ajax_upload.js` の枠の追加・枠の×・ひな型 | `repeatable_group.js` の行の追加・行の× |

**マイグレーション**（`2026_10_21_000000_rename_columns_on_t_news_attachments_table.php`）

- `filename` → `file`、`original_name` → `file_origin` に名前を変える。今の行とファイルはそのまま使う。
- `seq`（`unsignedInteger`、既定 0）を `news_id` の後ろに足し、今の行には記事ごとに id の順で 0, 1, 2… を入れる。
- `file` を NULL 可にする（組の中のファイルの列は、行に残る値として扱うため。見本の写真の `image` と揃えた）。
- `down()` は、NULL 可を戻し、並び順の列を消し、列の名前を戻す。

## C. 前提・依存

- 繰り返しの組（`RepeatableGroup`・`_repeatable_group`・`repeatable_group.js`）。`docs/repeatable-group-spec.md`
- AjaxFileUpload（組の中の欄の受け取り・一時ファイルの移し方）、UploadFieldDefinitions、UploadedFileController
- `NewsAttachment` の `$fillable` に `file`・`file_origin`・`seq`。`News::attach()` は `seq`、`id` の順
- 画面で `ajax_upload.js` と `repeatable_group.js` を読み込む（ニュースの入力画面は読み込み済み）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| 子テーブルの列 | マイグレーションで `file`・`file_origin` に名前を変える | 組に列の名前を読み替える設定を足す：例外の決まりが1つ増え、ほかの組と書き方が食い違う |
| 複数の欄の仕組み | フレームワークから外し、`'名前.*'` は例外で案内する | 残して2つの仕組みを並べる：配布の後に名前や動きを変えると壊れるので、配布の前に1つにした |
| まとめて選べる欄 | ファイルの項目が1つだけの組 | 2つ以上の組でも選べるようにする：どの欄に入れるかが決まらず、ほかの項目の入力と行の対応が分かりにくい |
| 行を足す仕組み | `ajax_upload.js` がイベントを出し、`repeatable_group.js` が行を足して返す | `ajax_upload.js` が行のひな型を直接複製する：行の数の上限やボタンの状態の扱いが2か所に分かれる |
| 行を足す時点 | 送って受け付けられてから | 先に足す：許可されない種類のファイルで、空の行が残る |
| 「ファイルを削除」 | ファイルだけの組で行が2つ以上なら行ごと消す。最後の1行と、ほかの項目がある組では欄を空にするだけ | どの組でも欄を空にするだけ：ファイルだけの組では、空の行が残って手間が増える。どの組でも行ごと消す：写真のような組で、同じ行のキャプションまで消える |
| 最後の1行の × | 行の id も含めて、ひな型の空の行に置き換える | 行の id を残して中身だけ空にする：空にした後に入れ直すと元の行の更新になり、× で消した行が残ったように見える。× は行を消す操作なので、入れ直したときは新しい行にした |
| `file` の NULL 可 | NULL 可にする | NOT NULL のまま：組の部品は、一時ファイルが片付けで消えていたときなど、ファイルを移せなかった行のファイルの列を NULL で書くので、保存が失敗しうる ※プロンプトに無かった点 |
| CSV の取り込み | 今回は作らない。取り込みのとき添付ファイルの列は無視する | 複数の欄の取り込みを組に合わせて直す：組の取り込みとして次のタスクでまとめて作るほうが、作り直しが無い |
| CSV の取り込みの複数の欄の処理（`CsvColumnSet::uploadTarget()`・`CsvReader` の複数の欄の今の値） | 残す。今は呼ばれない | プロンプトでは外す予定だった：次のタスクの組の取り込みで、形を変えて使うため ※プロンプトと違う点 |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | ニュースの CSV の添付ファイルの列は、取り込めない（無視される）。組の取り込みを足すまで、CSV で添付ファイルを直せない |
| 中 | `CsvColumnSet::uploadTarget()` と `CsvReader` の複数の欄の今の値の処理は、今は呼ばれない。次のタスクで組の取り込みに作り直すか、使わなければ消す |
| 低 | 組の子テーブルの変更は、操作ログの「変わった列」に入らない（組の部品の課題と同じ） |
| 低 | 行の並べ替えの操作は無い。添付ファイルの順を変えるには、行を消して足し直す |

## F. セキュリティ・権限・データ整合性の注意

- 添付ファイルの保存・削除・照合は、組の部品の決まりに従う。今のファイルは「その行の今の値」と一致するときだけ残り、hidden を書き換えてほかの行のファイルを指させても残らない。
- 非公開の添付ファイルは、その行にあるファイル名のときだけ返す。見てよいかは今までどおり `NewsPolicy`。
- 行の数の上限（5件）は、画面だけでなく保存のときの検証（`repeatableGroupRules()`）でも守る。まとめて選んだときの上限は画面の都合で、検証の代わりではない。
- マイグレーションは列の名前を変えるだけで、ファイルは動かさない。保存先のディレクトリと非公開の扱いはそのまま。

## G. テスト状況

**実施済み**

- `tests/Feature/UploadFieldTest.php`（8件）
  - 定義の読み方：1つだけの欄は `multiple` が false、添付ファイルは組の欄で `multiple` が true
  - 書き方の誤り：`'名前.*'` は組の書き方を案内する例外、1つだけの欄の `max` は使えないキー
  - 新規登録の画面：添付ファイルの組に上限（`data-max="5"`）と横並びのクラスが付き、欄が `multiple`。一覧用画像は `multiple` が付かない
- `tests/Feature/RepeatableGroupTest.php`（添付ファイルの分を3件足して22件）
  - 添付ファイルが、空の行を除いて画面の順に、元のファイル名と並び順つきで保存される。非公開のファイルは、その行のファイルのときだけ返る。訪問者向けの詳細に元のファイル名で出る
  - 消した行のファイルが、保存の後に消える。残した行の id とファイルは変わらない
  - CSV の書き出しに添付ファイルの組の列が出る。取り込みでは添付ファイルの列が無視され、今の行は変わらない
- 手元の DB でマイグレーションを流し、今の添付ファイルの行が列の名前を変えて残り、並び順が記事ごとに id の順で入ることを確かめた。`migrate:rollback --step=1` で元の列に戻り、もう一度流せることも確かめた

全体で 390件が通る。

**未実施**

- ブラウザーでの画面の確認（まとめて選んだときの行の足し方と上限の知らせ、許可されない種類のファイルで行が増えないこと、「ファイルを削除」で行が消えること、最後の1行の × で行が空になること、横並び、確認画面との行き来）
- サーバーでのマイグレーション

## H. 今後の改善提案・TODO

- CSV の取り込みで、組の横展開の列から子テーブルを書き戻す（添付ファイルの取り込みを戻す）
- 行の並べ替えの操作（ドラッグ、上下のボタン）
