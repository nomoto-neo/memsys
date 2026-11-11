# ツール

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

プロジェクトの直下の `tools/` にある、開発のときに手元で動かす道具です。どちらも下書きを書き出すだけで、今あるファイルは書き換えません。

## テーブルからコーナーの下書きを作る（table2draft.php）

マイグレーションを実行した後のテーブルを読んで、コーナーのコントローラー・モデル・入力欄の画面の下書きを書き出します。列の型や桁数、子テーブルや多対多のつながりを、目で見て書き写す手間を減らすためのものです。

```
php tools/table2draft.php t_members              tools/tmp/ に3つのファイルを書き出す
php tools/table2draft.php t_members t_news       いくつでも並べられる
php tools/table2draft.php --stdout t_members     ファイルに書かず、画面に出す
```

- プロジェクトの直下で動かします。Laravel を起動して、`.env` の DB を読みます。
- 書き出すファイルは、次の3つです。コントローラーとモデルはクラスの形で書き出すので、エディタで色分けが効き、メソッドを丸ごと選んで貼れます。
  - `t_members.controller.php`：トレイトの `use`、一覧の定数、`UPLOAD_FILES`・`CHILD_TABLES`、`rules()`・`formValues()`・`defaultValues()`・`searchRules()`・`csvColumns()`・`saveValues()`・`afterSave()`。冒頭のコメントに、参考のテーブルの CREATE 文
  - `t_members.model.php`：画像の横幅の定数、`$fillable`・`$casts`、論理削除、リレーション
  - `t_members.fields.blade.php`：入力欄の画面（`_fields.blade.php`）
- 貼り付けた後、`TODO` の付いた所を直します。列の名前から推測したものと、人が決めることに付いています。入口（`index`・`create` など）は書き出さないので、実例を写して書きます。
- 項目の名前は、列の COMMENT から取ります。COMMENT の無い列は、列の名前のままにして `TODO` を付けます。

ルールの決まりは、次のとおりです。

- NULL を許さない列は `required`、許す列は `nullable`。型ごとのルールは、ツールの冒頭の `TYPE_RULES`
- text 型の列は、上限の文字数を決め打ちで書く。ふつうは2000、本文らしい名前の列は20000
- 一意の索引が1列に付いていれば、`Rule::unique()`
- 列の名前と同じ名前の区分表があれば、`Rule::in(code_keys())`（[code-table.md](code-table.md)）。入力欄は選択肢（`code_options()`）
- 真偽の列は、どちらかを必ず選ぶラジオボタン
- DB の既定値がある列は、`defaultValues()` に書く
- `id`・`created_at` のような入力しない列は書き出さない。`staff_id` は、`saveValues()` で入れる旨のコメントだけ書く

アップロードの決まりは、次のとおりです（[upload.md](upload.md)）。

- 「列」と「列_origin」の組と、`photo`・`image` のような名前の文字の列は、アップロードの欄として、`rules()` ではなく `UPLOAD_FILES` に書く
- `photo`・`image`・`picture` を含む名前は画像、`file`・`attach` を含む名前は添付ファイル。どちらでもなければ、画像として書いて `TODO` を付ける
- 画像の横幅は、モデルに「列_WIDTH」の定数として仮の値（1000px）で書き、`UPLOAD_FILES` から参照する
- NULL を許さない列は `'required' => true`

ほかのテーブルとのつながりの決まりは、次のとおりです。

- **子テーブル**：`t_` で始まるほかのテーブルのうち、「対象の単数形_id」の列（`t_news` なら `news_id`）を持つものを、子テーブルとして `CHILD_TABLES` に書きます（[form.md](form.md)）。
  - 組の名前とリレーションの名前は、子テーブルの名前から `t_` と親の名前を外したものです（`t_news_photos` は `photos`）。
  - 並び順の列（`seq` など）があれば `'order'`、無ければ `'delete_insert' => true` にします。行の数の上限は、仮に10です。
  - 組の項目のルールは「組の名前_列.*」に、組の中のファイルは `UPLOAD_FILES` に「組の名前.*.列」で書きます。親のモデルには `hasMany` を書きます。
  - ほかのコーナーで更新するテーブルも見つかるので、そのときは使いません。
- **中間テーブル**：`id`・`created_at`・`updated_at` を除いた列が、`_id` で終わる2つだけのテーブルは、多対多として書きます。選んだ id の配列のルール、`formValues()`・`defaultValues()` の値、`afterSave()` の `sync()`・`beforeDelete()` の `detach()`、モデルの `belongsToMany`、入力欄のチェックボックスです。
- **belongsTo**：「xxx_id」の列で、`t_xxxs` のテーブルがあれば、モデルに `belongsTo` を書きます。
- **論理削除**：`deleted_at` の列があれば、モデルに `SoftDeletes` を付けます。

一覧の決まりは、次のとおりです（[list.md](list.md)）。

- `name`・`title`・`kana`・`subject` で終わる文字の列は、フリーワード検索の対象（`FREE_WORD_COLUMNS`）
- 区分表のある列は、配列で受けて `IN()`。真偽の列は完全一致（`searchRules()`）
- 並び順は、更新日の新しい順・古い順。日付の列があれば、最初の1つの新しい順・古い順も足す（`ORDER_OPTIONS`）
- 列に COMMENT が1つでもあれば、CSV ダウンロードの項目（`csvColumns()`）も書く。見出しは COMMENT です（[csv-download.md](csv-download.md)）

名前から推測する決まりは、ツールの冒頭の定数に足していきます。

## Smarty のテンプレートを Blade に書き換える（tpl2blade.php）

旧フレームワークのサイトを移すときに使います。Smarty のテンプレート（`.tpl`）を Blade（`.blade.php`）に書き換えます。Laravel にも Composer にも依存せず、このファイルだけで動きます。

```
php tools/tpl2blade.php AAA.tpl            AAA.tpl と同じ場所に AAA.blade.php を書き出す
php tools/tpl2blade.php dir/               ディレクトリの中の .tpl を全部（下の階層も）
php tools/tpl2blade.php --force AAA.tpl    AAA.blade.php がもうあっても上書きする
php tools/tpl2blade.php --stdout AAA.tpl   ファイルに書かず、画面に出す
```

- 機械的に決まる所は全部書き換え、決まらない所には `{{-- TODO(tpl2blade): … --}}` の印を付けて、元の書き方を印の中に残します。書き換えの後に、印を付けた行の一覧を出します。
- 主な書き換えは、次のとおりです。
  - 一番上の変数 `$name` は `$input['name']`、`foreach` の行の `$d.id` は `$d->id`
  - 名前が `_ary` で終わる変数は区分表。`foreach` なら `code_table('名前')`、`$xxx_ary[$v]` なら `code_label('名前', $v)`（[code-table.md](code-table.md)）
  - `|checked:$k`・`|selected:$k` は `@checked(is_match(…))`・`@selected(is_match(…))`
  - `html_options` は `code_options()`、`html_values` は `code_label()`
  - `<!--{$err.xxx}-->` は、エラー欄の `<div class="invalid-feedback" …>`
  - HTML のコメントは Blade のコメント、`method="post"` の `<form>` には `@csrf` を足す
- サイトごとに変わる読み替えは、ツールの冒頭の「設定」の定数を書き換えます。区分表の名前の読み替え、アップロードの欄の変数、レイアウトに当たるテンプレートの名前などです。
