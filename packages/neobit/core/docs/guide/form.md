# 登録・更新（FormFlow）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Crud/FormFlow.php`　**実例**：`Admin\NewsController`（確認画面あり）・`Admin\CategoryController`（確認画面なし）

## コントローラーに書くメソッド

| メソッド | 必須 | 中身 |
|---|---|---|
| `rules($record)` | ○ | 検証のルール。`$record` は新規なら null。使わなければ引数なしの `rules()` でよい |
| `saveValues($validated, $record)` | ○ | 保存する値（`列 => 値`）。ここで返した列だけを保存する。外した列は更新でもDBの値が残る。書き方は下の「保存する値」 |
| `formValues($record)` | 詳細・編集があれば○ | モデルの今の値から `$input` を作る（日付は `Y-m-d`、フラグは `'1'`/`'0'`、多対多は id の配列など、フォームの値の形に） |
| `defaultValues()` | | 新規登録の初期値 |
| `prepareInput($validated)` | | 検証の後、確認画面と保存の前の整形（例：`safe_html()`、郵便番号の整形） |
| `afterSave($record, $validated, $changedFields)` | | 保存の直後（例：多対多の `sync()`）。`$changedFields` は更新で値が変わった列で、`列の名前 => 変わる前の値`。使わなければ引数に書かなくてよい。新規登録と CSV 取り込みでは空 |
| `operationLogActionType($created)` | | 操作ログ（[operation-log.md](operation-log.md)）に残す操作の種類。登録・更新とは別の名前で残したいコーナーで書く（例：一斉メールの送信） |
| `beforeDelete($record)` | | 削除の直前（[delete.md](delete.md)） |

アップロード項目は `saveValues()` に入れません（`commitUploads()` が保存します）。多対多は `afterSave()` で保存します。

## 保存する値

`saveValues()` は、どのコーナーも「入力値をそのまま保存する列の一覧 → `pickValues()` で取り出す → 加工して保存する列を足す」の順に書きます。`pickValues()` は `FormFlow` にあり、送られてこなかった列は null にします（チェックの無いチェックボックスのように、送られてこない項目も DB の値を空にできるようにするため）。

```php
/** 保存する値（t_staffsの列 => 値）。ここで返した列だけを保存する。 */
private function saveValues(array $validated, Staff $staff): array
{
    // 入力値をそのまま保存する列。送られてこなかった列は null になる
    $saveFieldNames = ['name', 'login_id', 'email'];

    $values = $this->pickValues($validated, $saveFieldNames);

    // パスワードは入力があったときだけハッシュ変換して保存する。空欄なら今のまま変えない
    if (! empty($validated['password'])) {
        $values['password'] = Hash::make($validated['password']);
    }

    return $values;
}
```

- 加工して保存する列が無いコーナーは、`return $this->pickValues($validated, $saveFieldNames);` で終えます。
- 入力値がそのまま入る列は、`$saveFieldNames` の一覧で絞られます。`$validated` を丸ごと返さないでください。
- 加工して保存する列の例は、ハッシュ値にしたパスワード、操作したスタッフの id、新規登録のときだけ決める表示順や状態です。

## 入口（確認画面あり）

```php
/** 新規登録フォーム */
public function create(): View
{
    return view('admin.news.create', [
        'input' => $this->inputPageValues(null, old()),   // 初期値は defaultValues()、old() が優先
        'categories' => $this->allCategories(),
        'required' => $this->requiredFields(),
    ]);
}

/** 確認画面 */
public function confirmForStore(Request $request): View
{
    return view('admin.news.confirm', [
        'isCreate' => true, 'news' => null,
        'input' => $this->confirmPageValues($request),   // 検証（失敗なら入力画面へ戻る）＋整形
        'categories' => $this->allCategories(),
    ]);
}

/** 確認画面の「戻る」 */
public function backToCreate(Request $request): RedirectResponse
{
    return redirect()->route('admin.news.create')->withInput($request->except('_token'));
}

/** 登録の実行 */
public function store(Request $request): RedirectResponse
{
    $this->saveData(new News(), $request);   // 検証し直し → トランザクションで保存

    return redirect()->route(self::INDEX_ROUTE, ['back'])->with('status', 'ニュース記事を登録しました。');
}
```

編集は `edit(News $news)` で `inputPageValues($news, old())`・`requiredFields($news)`、`confirmForUpdate()` で `confirmPageValues($request, $news)`、`update()` で `saveData($news, $request)` です。

**確認画面なし**（項目が少なく、間違えてもすぐ直せるもの）は、`store()`・`update()` からそのまま `saveData()` を呼びます。検証に失敗すれば入力画面へ戻ります（実例：カテゴリー）。

## ルート

```php
Route::get('/news/create', ...'create')->name('news.create');
Route::post('/news/confirm', ...'confirmForStore')->name('news.confirm.store');
Route::post('/news/back', ...'backToCreate')->name('news.confirm.store.back');
Route::post('/news/store', ...'store')->name('news.store');
Route::get('/news/{news}/edit', ...'edit')->name('news.edit');
Route::patch('/news/{news}/confirm', ...'confirmForUpdate')->name('news.confirm.update');
Route::post('/news/{news}/back', ...'backToEdit')->name('news.confirm.update.back');
Route::patch('/news/{news}/update', ...'update')->name('news.update');
```

## 画面

- **`_fields.blade.php`**：入力欄一式。新規登録・編集・確認・詳細の4画面から同じものを呼びます。受け取る変数は `$input`・`$model`（新規は null）・`$readonly`（`' readonly'` か `''`）・`$disabled`（`' disabled'` か `''`）・`$required`（必須マークの配列。読み取り専用の画面では `[]`）と、選択肢など。
- **1項目の書き方**：

```blade
<div class="mb-3">
    <label for="title" class="form-label">タイトル {!! $required['title'] ?? '' !!}</label>
    <input id="title" type="text" name="title" class="form-control"
           value="{{ $input['title'] }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="title">{{ $errors->first('title') }}</div>
</div>
```

  エラー欄の `<div>` と `{{ }}` の間に改行や空白を入れないでください（中身が空のときだけ隠れる CSS のため）。ラジオボタン・チェックボックス・セレクトには `$disabled` を付けます。
- **`$input` には、`rules()` にある項目のキーが必ず入っています**。値の無い項目は null です。画面では `{{ $input['title'] }}` のように、`?? ''` を付けずに書けます。チェックの無いチェックボックスのように、送られてこない項目も同じです。一覧の検索条件の `$filters`（[list.md](list.md)）も、検索の項目のキーが必ず入っています。`rules()` に無い項目（`password_confirmation` など）と、`FormFlow` を通さずに組み立てた `$input`（会員登録の `old()` など）には、`??` が要ります。
- **確認画面**：`_fields` を読み取り専用で表示し、「戻る」「登録する」の2つのフォームに `@include('neobit::_confirm_hidden', ['input' => $input])` で hidden を入れます。項目が増えても確認画面は直さずに済みます。
- **パスワード**：確認画面の「戻る」側は `'exclude' => ['password', 'password_confirmation']` で hidden から外します。`rules()` に無い `password_confirmation` は、確認画面の `$input` に足して持ち回ります。入力画面に戻したときは、`Arr::except(old(), [...])` で再表示しません（実例：スタッフ）。
- **必須マーク**：`requiredFields($record, ['password_confirmation'])` の第2引数で、`rules()` に `required` が無いが必須にしたい項目を足せます（`accepted` の同意チェックなど）。マークの HTML は `config/neobit.php` の `form` の節の `required_mark`（管理画面と訪問者向けで別）にあります。
- 入力画面では、必須マークの付いた欄に JavaScript が `required` 属性を付け、ブラウザの入力チェックの結果も同じエラー欄に出します（[blade.md](blade.md)）。

## トランザクション

`saveData()` は、本体の保存・アップロードの確定・`afterSave()` の中の書き込みまでを1つのトランザクションで行います。`afterSave()` の中で自分でトランザクションを書く必要はありません。保存の処理だけを使いたいとき（CSV 取り込みなど）は、トランザクションの中で `saveValidated($record, $validated, $input)` を呼びます。

## 入力をそろえる（InputNormalizer）

**ファイル**：`packages/neobit/core/src/Crud/InputNormalizer.php`（冒頭のコメント）・`packages/neobit/core/src/Http/Middleware/NormalizeInput.php`、`packages/neobit/core/src/Rules/KatakanaRule.php`・`HiraganaRule.php`

入力された文字の、全角と半角の揺らぎをそろえます。住所や電話番号のような個人情報が、入力した人によって全角だったり半角だったりするのを防ぐためです。画面からの入力の全部と、CSV 取り込みの各セルに、検証の前に掛かります。全角の数字で入力された電話番号も、半角に直ってから検証されます。

| そろえる内容 | 例 |
|---|---|
| 全角の英字と数字を、半角にする | `ＡＢＣ１２３` → `ABC123` |
| 半角カナを、全角カナにする。濁点は前の文字とまとめる | `ｶﾞｲｼｬ` → `ガイシャ` |
| 記号の `＠ － ． ： ／` を、半角にする | `ｔａｒｏ＠ｅｘａｍｐｌｅ．ｃｏｍ` → `taro@example.com` |

コントローラーと画面に書くものは、ありません。そろえないのは、次の2つだけです。

- **パスワード**。入力されたままを照合します。
- **モデルで指定した項目**。本文のように、書いたとおりに残す文章です。

```php
/** モデル。データの仕様として持つ */
public const RAW_INPUT_FIELDS = ['body'];

/** コントローラー。モデルの指定を引く。CSV 取り込みにも効く */
private const RAW_INPUT_FIELDS = News::RAW_INPUT_FIELDS;
```

- 入力をそろえる時点では、どのモデルの入力かがまだ分からないので、そのリクエストを受け持つコントローラーの定数を見ます。定数を書いていないコントローラーでは、全部の項目をそろえます。ログインや検索の入力も、そろえます。
- 今、指定しているのは、ニュースの本文、一斉メールとその文面の件名・本文、お問い合わせの内容です。
- **ひらがなとカタカナは、変換しません**。フリガナの欄には `new KatakanaRule()` を付けて、ひらがなを入れたらエラーにします。通すのは、全角カタカナ・長音（`ー`）・中点（`・`）・全角と半角の空白です。英数字とハイフンは通しません。ひらがなの欄には `new HiraganaRule()` を使います。
- すでに保存されているデータは、変わりません。これから入力される分だけがそろいます。
- 探すときは、DB の照合順序（`utf8mb4_unicode_ci`）が、全角と半角・大文字と小文字・ひらがなとカタカナの違いを無視して比べます。そろえていない古いデータも、検索やログインでは見つかります。

## 子テーブル（ChildTables）

**ファイル**：`packages/neobit/core/src/Crud/ChildTables.php`・`ChildTableDefinitions.php`（書き方）・`packages/neobit/core/resources/views/_repeatable_group.blade.php`・`packages/neobit/core/resources/js/repeatable_group.js`　**実例**：ニュースの写真（`Admin\NewsController`、画面は `admin/news/_photo_row.blade.php`）・ニュースの添付ファイル（ファイルだけの組。画面は `admin/news/_attach_row.blade.php`）・会員の経歴（最初に10枠を並べる組。`Admin\MemberController`・`MypageController`、画面は `_member_career_row.blade.php`）　**設計**：`docs/repeatable-group-spec.md`

1:多の子テーブルを、親と同じ画面で入力して、親と一緒に保存します。子テーブルの1行を「いくつかの項目（ファイルを含んでよい）の組」として、画面で行を増やしたり消したりしながら入力します。例：ニュースの写真（画像とキャプション）。コントローラーの側の名前は `CHILD_TABLES`・`ChildTables`、画面の部品の名前は `_repeatable_group`・`repeatable_group.js`（行を繰り返す部品）です。いくつでも足せるファイルの欄も、ファイルの項目だけの組として書きます（例：ニュースの添付ファイル）。

```php
use FormFlow;
use AjaxFileUpload;      // 組にファイルの項目があるときは要る
use ChildTables;

private const CHILD_TABLES = [
    'photos' => [
        'relation' => 'photos',            // 子テーブルへのHasMany（News::photos()）
        'fields' => ['image', 'caption'],  // 組の項目。子テーブルの列の名前
        'order' => 'seq',                  // 並び順の列。無ければ書かない
        'max' => 10,                       // 行の数の上限。固定の枠なら代わりに 'fixed'。'min' も書ける
    ],
];

private const UPLOAD_FILES = [
    // 組の中のファイルの項目。子テーブルに image（保存したファイル名）と image_origin（表示名）の列を作る
    'photos.*.image' => ['type' => UploadType::Image, 'width' => News::PHOTO_WIDTH],
];

// rules()：組の項目の要素のルールを「組の名前_項目.*」に書き、childTableRules() を足す
'photos_caption.*' => ['nullable', 'string', 'max:200'],
] + $this->ajaxUploadRules() + $this->childTableRules();
```

```blade
{{-- _fields.blade.php。行の中身は、コーナーの部分ビュー（$model・$input・$idx・$readonly・$uploadUrl を受け取る）に書く --}}
@include('neobit::_repeatable_group', [
    'group' => 'photos', 'row' => 'admin.news._photo_row',
    'model' => $model, 'input' => $input, 'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
    'addLabel' => '写真を追加', 'inline' => true,
])

{{-- _photo_row.blade.php。name は「組の名前_項目[]」、値は $input['photos_caption'][$idx] --}}
@include('neobit::_ajax_upload_block', ['field' => 'photos_image', 'idx' => $idx, ...])
<input type="text" name="photos_caption[]" value="{{ $input['photos_caption'][$idx] ?? '' }}">
```

- **書けるのは1:多だけ**：`relation` には、親から子への HasMany のリレーションを書きます。多対多（BelongsToMany）などを書くと、確認画面へ進むときか保存のときに「…のCHILD_TABLESには1:多のテーブルしか定義できません。News::categories()はBelongsToManyです。」の例外になります。多対多（ニュースのカテゴリーなど）は、選んだ id の配列を受けて、`afterSave()` で `$news->categories()->sync($validated['category_ids'])` のように揃えます。
- **送る値**：行の並びを揃えた、列ごとの配列（`photos_id[]`・`photos_caption[]`・`photos_image[]`・`photos_image_tmp[]`…）です。確認画面の hidden（`_confirm_hidden`）が1段の配列しか持ち回れないためです。行の id と、組が送られたことを示す印（`photos_group`）は、部品が出します。印は、行を全部消したときに「組を空にする」と伝えるためのものです。
- **空の行**：全部の項目が空の行は、検証の前に除きます。必須の項目は、空でない行にだけ効きます。
- **保存**：子テーブルの行の id を保って揃えます。届いた id の行は更新、id の無い行は追加、届かなかった行は削除します。ほかの親の行の id や、2回目に出てきた同じ id は、新しい行として扱います。並び順の列があれば、画面の並びの番号を入れます。
- **作り直す保存**：組に `'delete_insert' => true` を書くと、保存のたびに子テーブルの行を全部消して、画面の順に入れ直します。行の id は保ちませんが、id の順が画面の順になるので、並び順の列（`'order'`）が要りません。ファイルの扱いは同じで、入れ直した行のファイルは消えません。次のときは使いません。
    - ほかのテーブルから、子の行を id で参照するとき（保存のたびに id が変わる）
    - 子テーブルを外部キーで参照しているテーブルがあるとき（行を消せないか、参照先も一緒に消える）

  `'order'` を書かずに id を保つ保存にすると、途中の空いた行に入れたファイルは新しい大きい id で足されるので、次に開いたときに末尾へ回ります。並び順の列を持たないなら、作り直す保存にします。
- **ファイル**：受け取りと一時ファイルの移し方はアップロードの部品（[upload.md](upload.md)）のものを使います。今のファイルは、ファイル名が「その行の今の値」と一致するときだけ残すので、hidden を書き換えてほかの欄のファイルを指させることはできません。使わなくなったファイルは、トランザクションが確定した後に消えます。
- **ファイルだけの組**：ニュースの添付ファイルは、次のように書きます。作り直す保存にして、子テーブルには `file`（保存したファイル名）・`file_origin`（表示名）だけを作ります。行の部分ビューは `_ajax_upload_block` を1つ置くだけです。

    ```php
    private const CHILD_TABLES = [
        'attach' => ['relation' => 'attach', 'fields' => ['file'], 'delete_insert' => true, 'fixed' => 4],
    ];
    private const UPLOAD_FILES = [
        'attach.*.file' => ['type' => UploadType::File],
    ];
    ```
- **まとめて選ぶ**：ファイルの項目が1つだけの組では、ファイルをまとめて選べます（ドロップでも同じ）。1つ目は選んだ行に、2つ目から先は空いている行に入れ、1つずつ順に送ります。空いている行が無ければ、受け付けられてから行を足すので、許可されない種類のファイルで空の行は増えません。上限を超える分は足さずに知らせます。ファイルの項目が2つ以上ある組では、1つずつ選びます。
- **ファイルを削除する**：ファイルだけの組（例：添付ファイル）では、行が2つ以上あれば「ファイルを削除」で行ごと消します。最後の1行と、ほかの項目がある組の行は、ファイルを空にするだけで、行は残します（同じ行のほかの項目の入力を残すため）。
- **行の ×**：行が2つ以上あれば行ごと消します。最後の1行は消さずに、空の行に置き換えます（行の id・ファイルの欄・文字の欄が、まとめて空になります）。空の行は保存のときに除かれるので、今の行は削除になります。
- **FormFlow が呼ぶ**：値の組み立て（`inputPageValues()`・`confirmPageValues()`）、空の行の除き方、保存、削除は FormFlow が呼ぶので、入口の書き方は変わりません。
- **最初に並べる枠の数**：`'initial' => 10` を書くと、入力の画面に最初から10枠を並べます。行がそれより少なければ、空の枠で埋めます。「行を足す」と × は普通に出るので、`'max'` まで足せます（見本は会員の経歴。`'initial' => 10, 'max' => 20`）。
    - 省略すると1です。`'max'` までの数で書き、`'fixed'` とは同時に書けません。
    - 空の枠は保存されないので、10枠のうち3つだけ入れれば3行が保存され、次に開くと3行と空の7枠に戻ります。画面の上で × を押して10枠より減らしても、開き直すと10枠に戻ります。
    - `'min'` は、入っている行の下限（検証）で、最初に並べる枠の数とは別です。
- **固定の枠**：`'max'` の代わりに `'fixed' => 4` を書くと、入力の画面に最初から4枠を並べ、行を増やしたり消したりしません。経歴の年月と内容を10枠並べて、入れた分だけ保存する、のような画面に使います（見本はニュースの添付ファイル）。
    - 上限も `'fixed'` の数になります。`'fixed'` と `'max'` は同時に書けません（例外になります）。`'min'` は一緒に書けます。
    - 「行を足す」と行の × は出ません。ファイルだけの組でも、「ファイルを削除」は欄を空にするだけです。ファイルをまとめて選んだときは、空いている枠に順に入れ、入りきらない分は知らせます。
    - 保存してある行が枠の数より多いときは、その行を全部出します。そのまま保存すると、上限で断ります。
    - 空の枠は保存されず、間を空けて入れても詰めて保存されます。確認・詳細の画面には、入っている行だけが並びます。
- **画面**：「行を足す」・行の ×・行の数の上限・横並び（`'inline' => true`。行の幅は `global.css` の `.repeatable_inline`）は部品が持ちます。画面で `vendor/neobit/core/resources/js/repeatable_group.js` を読み込みます。
- **CSV**：`csvColumns()` に組の横展開（[csv-download.md](csv-download.md)。`'添付:*' => ['attach', 'group' => ['添付ファイル' => 'file_origin', '保存ファイル名' => 'file']]`）を書くと、書き出しにも取り込みにも使えます。書かなければ、CSV は親の項目だけで、子テーブルには触りません。
    - 取り込みは、CSV に組の列がある行だけ、子テーブルを CSV の内容で入れ直します。CSV には子テーブルの行の id が無いので、id を保つ組でも、取り込むと行の id が変わります。組の列が無い CSV では、子テーブルには触りません。
    - 組の項目の列が全部そろっているときだけ取り込み、足りなければ「取り込めない列なので、無視しました」の警告にします。ファイルの表示名の列だけは無くてよく、無ければ今のファイルの表示名を使います。
    - 「添付ファイル:2」「保存ファイル名:2」のような番号の組が全部空欄なら詰めます。行の数の上限と、組の項目の検証ルールは、画面と同じに効きます。
    - ファイル名は、1つだけの欄と同じく、今のファイルか、その記事の保存先にあるものだけ使えます。追加の行には書けません。
    - 確認画面の変更の内容には、組を1つの項目として「a.pdf（案内.pdf）、b.pdf」のように出します。ファイルの無い組は、項目の値を「／」でつなぎます。
- 組の子テーブルの変更は、操作ログの「変わった列」には入りません。
