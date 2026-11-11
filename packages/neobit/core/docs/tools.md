# ツール

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

プロジェクトの直下の `tools/` にある、開発のときに手元で動かす道具です。どちらも下書きを書き出すだけで、今あるファイルは書き換えません。

## テーブルから入力の検証と保存の下書きを作る（table2rules.php）

マイグレーションを実行した後のテーブルを読んで、コーナーのコントローラーとモデルに貼る下書きを書き出します。列の型や桁数を、目で見て書き写す手間を減らすためのものです。

```
php tools/table2rules.php t_members              tools/tmp/t_members.rules.txt に書き出す
php tools/table2rules.php t_members t_news       いくつでも並べられる
php tools/table2rules.php --stdout t_members     ファイルに書かず、画面に出す
```

- プロジェクトの直下で動かします。Laravel を起動して、`.env` の DB を読みます。
- 書き出すものは、次のとおりです。コントローラーとモデルへ貼り付けて、`TODO` の付いた所を直します。
  - 参考のテーブルの CREATE 文。TODO を直すときに、列の定義を見比べるためのものです
  - コントローラーの冒頭の `use` 文
  - コントローラーの `rules()`・`formValues()`・`saveValues()`（[form.md](form.md)）
  - モデルの `$fillable`・`$casts`
- `TODO` は、列の名前から推測したルールと、人が決めることに付きます。

ルールの決まりは、次のとおりです。

- NULL を許さない列は `required`、許す列は `nullable`。型ごとのルールは、ツールの冒頭の `TYPE_RULES`
- text 型の列は、上限の文字数を決め打ちで書く。ふつうは2000、本文らしい名前の列は20000
- 一意の索引が1列に付いていれば、`Rule::unique()`
- 列の名前と同じ名前の区分表があれば、`Rule::in(code_keys())`（[code-table.md](code-table.md)）
- 「列」と「列_origin」の組と、`photo`・`image` のような名前の文字の列は、アップロードの欄として扱い、`rules()` には書かない（[upload.md](upload.md)）
- `id`・`created_at` のような入力しない列は書き出さない。`staff_id` は、`saveValues()` で入れる旨のコメントだけ書く

名前から推測するルールは、ツールの冒頭の定数に足していきます。

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
