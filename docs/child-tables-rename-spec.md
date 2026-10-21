# 仕様メモ：繰り返しの組を子テーブル（CHILD_TABLES）に名前を変え、1:多だけを許す（child-tables-rename）

- 実装プロンプト：`docs/prompt/child-tables-rename.md`
- 実装完了日：2026-10-21

## A. 概要

**目的・解決する課題**

繰り返しの組（`REPEATABLE_GROUPS`・`RepeatableGroup`）は、画面で行を増減するときだけでなく、1:多の子テーブルを親と一緒に入力して保存する仕組み全般に使える。PHP の側の名前を、画面の見え方ではなくデータの側（1:多の子テーブル）を表す名前に変えた。配布の後に名前を変えると壊れるので、配布の前に変えた。

あわせて、1:多（HasMany）ではないリレーションを書いたときに、分かりやすい例外にした。今までは確かめておらず、多対多（BelongsToMany）を書くと、行を足す・消す処理が相手のテーブル（カテゴリーなど）の行そのものを作ったり消したりしていた。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | トレイト・定義を読むクラス・定数・メソッドの名前。子テーブルのリレーションの確かめ |
| 見本のサイト | ニュースのコントローラーの `use`・定数・`rules()` の呼び出し。コメントの言葉 |
| 画面・動き | 変わらない |

## B. 実装仕様

**名前**（案A：定数と PHP の側だけ）

| 今まで | 変えた後 |
|---|---|
| 定数 `REPEATABLE_GROUPS` | `CHILD_TABLES` |
| トレイト `Neobit\Core\Crud\RepeatableGroup` | `Neobit\Core\Crud\ChildTables` |
| `RepeatableGroupDefinitions` | `ChildTableDefinitions` |
| `repeatableGroupRules()`（コントローラーが呼ぶ） | `childTableRules()` |
| FormFlow が呼ぶメソッド | `dropEmptyChildRows()`・`childTableRequiredRules()`・`childTableValues()`・`saveChildTables()`・`deleteChildTables()` |
| トレイトの中のほかのメソッド | `childTable〜`（`childTableRows()`・`childTableFileValue()` など） |
| テスト `RepeatableGroupTest` | `ChildTablesTest` |
| 文書の言葉「繰り返しの組」 | 「子テーブル」 |

- 変えないもの：画面の部品（`_repeatable_group`・`_repeatable_group_row`・`repeatable_group.js`・`repeatable_*` のクラス名・`{組}_group` の印・`repeatable:add-row`・`repeatable:remove-row` のイベント）。組の名前とその中のキー（`relation`・`fields`・`order`・`delete_insert`・`max`・`min`）。`UPLOAD_FILES` の `'組.*.列'` と、`kind` の `group`。
- 古い名前の定数（`REPEATABLE_GROUPS`）を書いたときの案内は作らない（配布の前で、使っている案件が無いため）。

**1:多だけを許す**

- 子テーブルのリレーションを取り出すところを `ChildTables::childTableRelation()` にまとめ、`HasMany` でなければ `LogicException` にする。
- 例外の文：「App\Http\Controllers\Admin\NewsControllerのCHILD_TABLESには1:多のテーブルしか定義できません。News::categories()はBelongsToManyです。」
- 確かめるのは、リレーションを取り出すとき（画面の値の組み立て・必須の確かめ・保存・削除）。リレーションの種類は親のモデルのメソッドを呼ばないと分からないため。

## C. 前提・依存

- FormFlow の保存の流れ（子テーブルのメソッドを `method_exists()` で探して呼ぶ）
- アップロードの部品（組の中の欄の受け取り・必須・非公開の表示）
- 名前を変える前の作業：`docs/repeatable-group-spec.md`・`docs/repeatable-group-attach-spec.md`・`docs/repeatable-group-delete-insert-spec.md`（冒頭に名前の変更の追記を入れた）

## D. 設計判断とトレードオフ

| 判断 | 採用 | 不採用とその理由 |
|---|---|---|
| 定数の名前 | `CHILD_TABLES` | `HAS_MANY_ROWS`：Laravel を知らない人に伝わりにくい。`CHILD_ROWS`：テーブルの定義というよりデータに見える。`REPEATABLE_GROUPS` のまま：1:多の子テーブルの定義だと読みにくい |
| 変える範囲 | PHP の側だけ（案A） | 画面の部品まで変える：画面の部品は「行を繰り返す」部品の名前として合っており、直すところが広がる |
| 例外の文 | 決まりと、実際のリレーションの種類の2文 | 組の名前も添える：リレーションのメソッド名で分かるので、同じことを繰り返す |
| 確かめる時点 | リレーションを取り出すたび | 定義を読んだとき：コントローラーの定数からは、リレーションの種類が分からない |
| 済んだ仕様メモ | ファイル名は変えず、冒頭に追記 | 中の名前を書き換える：その時点の作業の記録としての読み方が崩れる |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 低 | 新規登録の画面を開いただけでは、1:多かを確かめられない（親のレコードが無いため）。確認画面へ進むか保存するときに例外になる |
| 低 | PHP の側（子テーブル）と画面の部品（`_repeatable_group`）で名前が違う。利用ガイドで、両方の名前を並べて案内している |

## F. セキュリティ・権限・データ整合性の注意

- 多対多などを書いたときは、保存の処理に入る前の、リレーションを取り出した時点で例外になる。相手のテーブルの行は作られも消されもしない（テストで確かめた）。
- 名前を変えただけで、検証・保存・ファイルの扱いは変わらない。

## G. テスト状況

**実施済み**

- 名前を変えた後も、今までのテストが全部通る（`ChildTablesTest`・`UploadFieldTest`・`UploadRequiredTest` ほか）
- 多対多のリレーションを `CHILD_TABLES` に書いたテスト用のコントローラーで保存すると、例外の文のとおりの `LogicException` になり、カテゴリーは作られも消されも書き換えられもせず、記事とカテゴリーの繋がりも変わらない

全体で 402件が通る。

**未実施**

- ブラウザーでの画面の確認（画面と動きは変わらない）

## H. 今後の改善提案・TODO

- 固定の枠（`'fixed' => 10`）を画面の部品に足し、会員の経歴（マイページ・管理画面・履歴書の PDF）を見本にする（次の作業）
