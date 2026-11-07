# 区分表・選択肢

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Code/CodeTable.php`・`packages/neobit/core/src/helpers.php`・`app/Enums/`・`code/*.csv`・`config/neobit.php` の `code_table.db`・`code_table.method`・`packages/neobit/core/src/Http/Controllers/CodeTableController.php`（項目見出し一覧）

## ヘルパー

区分表（値と名称の組）は、出どころに関係なく次のヘルパーで使います。

| ヘルパー | 使いどころ |
|---|---|
| `code_table('prefectures')` | 選択肢の一覧（値 => 名称）。画面のセレクト、CSV の一覧 |
| `code_keys('prefectures')` | 値の一覧。検証の `Rule::in(code_keys(...))` |
| `code_label('prefectures', $value, '（未設定）')` | 名称。画面の表示、メール。複数選ばれた値を配列で渡すと、名称を「、」でつなげる。区切りは4番目の引数に書く |
| `code_options('prefectures', $selected)` | プルダウンの `<option>` を並べた HTML。選ばれているものに `selected` が付く。2階層の配列を渡すと `<optgroup>` にまとめる |
| `is_match($value, $key)` | 入力値が、選択肢の値に当たっているか。`@checked()`・`@selected()` に渡す |

## 出どころ

出どころは4種類で、`CodeTable` がコード名から自動で探します（2つ以上にあればエラー）。

- **列挙型**（`App\Enums\<コード名の StudlyCase>`、`CodeTableEnum` を実装し `label()` を持つ）：プログラムが値によって動きを変えるもの（スタッフの権限 `StaffAcl` など）。モデルの `$casts` や判定では列挙型を直接使います。列挙型を探す名前空間は、`config/neobit.php` の `code_table.enum_namespaces` に書きます。既定は `App\Enums\` で、書いた順に探します。フレームワークの列挙型（`Neobit\Core\Enums\`）は、書かなくても最後に探します。
- **CSV**（`code/<コード名>.csv`、「値,名称」を1行1件、`#` はコメント）：選択肢として並べるだけのもの（都道府県など）。
- **DB**（`code_tables` テーブル。コード名を `config/neobit.php` の `code_table.db` に載せる）：CSVと同じ扱いのもの（性別・連絡方法など）を、管理画面の「項目見出し一覧」から書き換えたいとき。値・名称の扱い（数字だけの値は int、名称の `\n` は改行）も CSV と同じです。
- **メソッド**（コード名を `config/neobit.php` の `code_table.method` に、`[クラス, 静的メソッドの名前]` で載せる）：スタッフの一覧のように、ほかのテーブルから作るもの。メソッドが返した「値 => 名称」の配列を、そのまま使います。

DBのコード表を増やすときは、`config/neobit.php` の `code_table.db` に1行足し、必要なら `CodeSeeder` に初期データを書きます。

```php
// config/neobit.php の code_table の節
'db' => [
    'gender' => ['label' => '性別', 'fixed' => true],          // label：項目見出し一覧に出す名前
    'contact' => ['label' => '連絡方法', 'fixed' => false],
],
```

プログラムが特定のコード値を前提に処理するコード表は、`fixed` を true にします。そのコード表は、画面で並び替えと表示名の変更だけができ、コード値の変更・追加・削除はできません（サーバー側でも断ります）。

項目見出し一覧の画面は、フレームワークにあります（`CodeTableController`・`neobit::admin.code_tables.index`・`code_editor.js`）。ルートはアプリが書きます。管理者だけのグループの中に、`Neobit\Core\Routing\FrameworkRoutes` の1行を書きます。名前の頭は、渡した URL から作ります（`/codes` なら `codes.`）。

```php
// routes/admin.php（管理者だけのグループの中）
FrameworkRoutes::codes('/codes');
```

## ほかのテーブルから作る（メソッド）

ほかのテーブルから作るコード表は、モデルに静的なメソッドを書き、`code_table.method` に載せます。画面やコントローラーで一覧を組み立てなくても、ほかのコード表と同じヘルパーで使えます。

```php
// config/neobit.php の code_table の節
'method' => [
    // 過去のデータの名前を出す用。削除したスタッフも含む
    'staff' => [Staff::class, 'nameOptions'],
    // 担当者を選ぶプルダウンの用。今いるスタッフだけ
    'active_staff' => [Staff::class, 'activeNameOptions'],
],

// app/Models/Staff.php
public static function nameOptions(): array
{
    return static::withTrashed()->orderBy('id')->pluck('name', 'id')->all();
}
```

- 設定には関数ではなく、クラスとメソッドの名前を書きます。`config:cache` で固められるようにするためです。
- メソッドは public で static にします。無いときや呼べないときは、最初に使った画面で `CodeTableException` になります。
- 論理削除した行を、名前には出し、選択肢には出さないときは、上の例のようにメソッドを分けて、コード表を2つにします。
- 1回作った一覧は、同じリクエストの中で使い回します。1つの画面で何度 `code_label()` を呼んでも、問い合わせは1回です。
- 操作ログの操作のコード表（`operation_log_action`）は、フレームワークが自分で持つメソッドのコード表です。`config/neobit.php` の `operation_log.actions` の列挙型を、書いた順に合わせて作ります。設定の `code_table.method` に書く必要はありません。

## 列挙型の名前の書き方

列挙型の値ごとの名前や設定は、`match` でメソッドの中に書かず、冒頭の定数に1行ずつまとめ、メソッドはそれを引くだけにします。キーは `case` の値を参照します（PHP 8.2から使える書き方）。

```php
// app/Enums/StaffAcl.php
case Staff = 0;
case Manager = 1;

private const LABELS = [
    self::Staff->value => 'スタッフ',
    self::Manager->value => '管理者',
];

public function label(): string
{
    return self::LABELS[$this->value];
}
```

## 選択肢を画面に書く

```blade
{{-- プルダウン。先頭の「選択してください」は画面に書く --}}
<select id="prefecture" name="prefecture" class="form-select"{{ $disabled }}>
    <option value="">選択してください</option>
    {{ code_options('prefectures', $input['prefecture']) }}
</select>

{{-- チェックボックス・ラジオ。囲むタグとクラスはデザインで変わるので、@foreachで書く --}}
@foreach (code_table('hobby') as $k => $name)
    <div class="form-check form-check-inline">
        <input id="hobby_{{ $k }}" type="checkbox" name="hobby[]" value="{{ $k }}"
               class="form-check-input" @checked(is_match($input['hobby'], $k)){{ $disabled }}>
        <label for="hobby_{{ $k }}" class="form-check-label">{{ $name }}</label>
    </div>
@endforeach

{{-- 一覧や詳細に、選ばれたものの名前を並べる --}}
{{ code_label('hobby', $row->hobby) }}
```

- **`is_match($value, $key)`** は、入力値が1つなら同じ値か、配列ならその中にあるかを返します。フォームから届いた文字の `'1'` と、コード表の数値の `1` は、同じ値として扱います。入力値が空なら、当たりません。型をそろえる `(int)` や `in_array()` を、画面に書かなくて済みます。
- **`code_options()`** は、`HtmlString`（そのまま出してよい印の付いた HTML）を返します。`{{ }}` はこの印の付いた値をエスケープしないので、`{{ }}` で出します。`{!! !!}` でも同じ結果になりますが、`{!! !!}` は中身を確かめずに出す書き方なので、画面には増やしません。複数選べるプルダウンは、選ばれている値を配列で渡します。
- **2階層の選択肢は、`code/` の CSV に見出しの行を書きます**。`[東北]` のように `[ ]` で囲んだ行が見出しで、その後ろの行は、次の見出しまでその中に入ります。`code_options('pref_area', $input['pref'])` と名前を渡すだけで、見出しごとの `<optgroup>` にまとまります。地域ごとに分けた都道府県のような選択肢に使います。

  ```
  # code/pref_area.csv
  [東北]
  2,青森県
  3,岩手県
  [関東]
  13,東京都
  ```

- **2階層のまま使えるのは `code_options()` だけです**。`code_table('pref_area')` は `['東北' => [2 => '青森県', 3 => '岩手県'], …]` を返します。`code_keys()`・`code_label()` や、検証の `Rule::in()`、CSV の項目の定義は、1階層のコード表のためのものです。2階層のコード表に使うときは、使う側で形を合わせます。列挙型と DB のコード表は、1階層だけです。
- **`code_options()` には、コード表の名前の代わりに配列も渡せます**。区分表に無い選択肢や、コントローラーで組み立てた2階層の配列を、そのまま渡せます。
- **ラジオとチェックボックスを並べる部品は、作りません**。1個ずつを囲むタグとクラスが、デザインごとに違うためです。
- 選択肢ごとに `data-` 属性を付けるなど、`<option>` に手を入れたいときは、`@foreach` と `@selected(is_match(...))` で書きます。

Blade には `\App\Enums\...` を書かず、`code_table()` 系で書きます。画面の説明文のような文面は、列挙型に持たせず Blade に書きます。
