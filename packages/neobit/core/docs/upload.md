# ファイルアップロード・WYSIWYG（AjaxFileUpload）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Upload/AjaxFileUpload.php`・`UploadFilePath.php`　**実例**：`Admin\NewsController`（一覧用画像・添付ファイル・本文）、`ContactController`（添付ファイル1つ）

ファイルは選んだ時点で Ajax で一時ディレクトリ（`upload_tmp/`）に送り、確認画面を経て保存するときに正式な保存先へ移します。コントローラー・画面の書き方は、アップロードの有無で変わらないようにしてあります。

## コントローラーに書くもの

```php
use AjaxFileUpload;

/**
 * フィールド名 => ['type' => 種類, 'width' => 縮小する横幅]。
 * 「組の名前.*.列」は、子テーブル（form.md）の中の欄
 */
private const UPLOAD_FILES = [
    'list_image' => ['type' => UploadType::Image, 'width' => News::LIST_IMAGE_WIDTH],
    'attach.*.file' => ['type' => UploadType::File],
];
/** 画像を埋め込める WYSIWYG の欄と、入れた画像を縮小する横幅（無ければ書かない） */
private const WYSIWYG_IMAGE_FIELDS = [
    'body' => News::BODY_IMAGE_WIDTH,
];

private function rules(): array
{
    return [
        'title' => [...],
        'body' => ['nullable', 'string'],
    ] + $this->ajaxUploadRules();   // アップロード項目の hidden のルールを足す
}

private function prepareInput(array $validated): array
{
    $validated['body'] = safe_html($validated['body'] ?? null);   // WYSIWYG の欄は必ず無害化

    return $validated;
}
```

### UPLOAD_FILES の書き方

決まりは `UploadFieldDefinitions` にあり、誤りは最初に画面を開いたときに例外になります。

| キー | 書き方 |
|---|---|
| `type` | 必ず書く。`UploadType::Image`（画像）か `UploadType::File`（添付ファイル） |
| `width` | 画像なら必ず書く。この横幅を超えたら縮小する。添付ファイルには書かない |
| `required` | 省略できる。`true` なら必須（下の「必須の欄」） |

受け付ける拡張子は、種類ごとに `config/neobit.php` の `upload` の節の `image_types`・`attach_types` を使います。

- **必須の欄**：`'required' => true` の欄は、新しいファイル（一時ファイルが今ある）か、消していない今のファイルが無いと、確認画面へ進めず、保存もできません。今のファイルは、hidden の値をそのまま信じず、編集しているレコードの今の値と照らします。組の中の欄では、空でない行のどれにもファイルを求めます（今のファイルは、その行の今の値と照らします）。「少なくとも1つ」は、組の `'min' => 1` で決めます。
    - エラーの文は、通常の項目と同じ `validation.required` です。エラーは欄の名前（組の中の欄は `photos_image.0` のように行ごと）に付き、`_ajax_upload_block` が自分で `invalid-feedback` を出します。
    - 必須マークは `requiredFields()` が付けます。画面では `{!! $required['list_image'] ?? '' !!}` と書きます。
    - ルールは FormFlow の検証（`validatedInput()`）が足すので、コントローラーの `rules()` には書きません。CSV の取り込みでも、必須の欄は確かめます（[csv-import.md](csv-import.md)）。
    - 見本は、ニュースの一覧用画像です。
- **いくつでも足せるファイル**：子テーブル（[form.md](form.md)）に、ファイルの項目だけの組を書き、`UPLOAD_FILES` に「組の名前.*.列」で書きます（例：`'attach.*.file'`。画面から送る名前は `attach_file`）。行の数の上限は組の `'max'` です。末尾が「.*」だけの書き方（`'attach.*'`）は、例外にして組の書き方を案内します。
- `$input` の組み立て（`inputPageValues()`・`confirmPageValues()`）と保存（`saveData()`）は FormFlow が自動でアップロードの処理を呼ぶので、入口の書き方は変わりません。
- ルートにアップロード先を1本足します。訪問者向けの画面では `throttle` を付けます。

    ```php
    // POST：Ajax で送られたファイルを一時ディレクトリに受け取り、結果を JSON で返す
    Route::post('/news/ajax-upload', [AdminNewsController::class, 'uploadAjaxFile'])->name('news.ajaxUpload');
    ```

- **単数のフィールド**（`list_image`）：テーブルに `list_image`（保存ファイル名）と `list_image_origin`（元のファイル名）のカラムを作り、モデルの `$fillable` に入れます。
- **組の中のフィールド**（`attach.*.file`）：子テーブルに、列（`file`）と、列の名前に `_origin` を付けた表示名の列（`file_origin`。NULL可）を作り、組の名前と同じ HasMany リレーション（`News::attach()`）を用意します。
- 許可する拡張子・大きさの上限は、サイト全体の方針として `config/neobit.php` の `upload` の節で決めます。コーナーごとには変えません。画像の種類を変えたら、エディタでファイルを選ぶときの絞り込み（`wysiwyg_suneditor.js`・`wysiwyg_summernote.js` の `ACCEPT_IMAGE_TYPES`）も合わせます。CSV の取り込みの大きさの上限（`csv_max_kb`）も、ここにあります。
- **保存先**：モデルのクラス名と id から、保存先の規則が決めます（既定の例：`news/000/000012/xxxx.jpg`。規則の差し替えは下の「保存先の規則を変えるとき」）。1件分のファイルはフィールドに関係なく1つのディレクトリに入ります。表示の URL は `UploadFilePath::url(クラス, id, フィールド, ファイル名)` で作り、モデルのアクセサにしておきます（例：`News::list_image_url`、`NewsAttachment::url`）。

## 画面

```blade
{{-- 1つだけの欄 --}}
@include('neobit::_ajax_upload_block', [
    'model' => $model, 'input' => $input, 'field' => 'list_image',
    'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
])
{{-- 組の中の欄は、組の行の部分ビューに、何行目か（$idx）を付けて置く（form.md） --}}
@include('neobit::_ajax_upload_block', [
    'model' => $model, 'input' => $input, 'field' => 'attach_file', 'idx' => $idx,
    'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
])
```

- 欄の種類・縮小する横幅・まとめて選べるかは、画面には書きません。部品が、ヘルパーの `upload_field(欄の名前, 項目)` で、今の画面のコントローラーの `UPLOAD_FILES` から読みます。そのため、部品はその欄を定義したコントローラーの画面で使います。案内の文などで使いたいときは、画面からも呼べます（`横幅{{ upload_field('list_image', 'width') }}pxに縮小します`）。項目は `'type'`・`'width'`・`'required'`・`'multiple'` です。

- `$readonly` を渡すだけで、入力画面ではアップロードの UI、確認・詳細画面では表示だけに切り替わります。
- **横に並べる**：組に `'inline' => true` を渡すと行を横に並べ、入りきらなければ折り返します（見本はニュースの添付ファイルと写真）。行の幅は 14rem で、`global.css` の `.repeatable_inline` にあります。案件の CSS で行に幅を当てれば変えられます（`.repeatable_group[data-group="attach"] .repeatable_row { width: 10rem; }`）。

  固定の幅（rem・px）を当てると、1行に並ぶ数は入れ物の幅で変わります。画面の幅にかかわらず1行に並ぶ数を決めたいときは、行の間（0.5rem）を差し引いた割合で幅を当てます。1行に n 個なら `calc((100% - 0.5rem × (n − 1)) / n)` です。割合だとスマホでも同じ数で並んで行が細くなるので、狭い画面は `@media` で数を減らします。

  ```css
  /* 広い画面は1行に3つ、狭い画面は1つ */
  .repeatable_group[data-group="attach"] .repeatable_row {
      width: calc((100% - 1rem) / 3);
  }
  @media (max-width: 576px) {
      .repeatable_group[data-group="attach"] .repeatable_row {
          width: 100%;
      }
  }
  ```
- アップロード欄のある画面（新規登録・編集）は、`@push('head-extra')` で CSRF の `<meta>` と `vendor/neobit/core/resources/js/ajax_upload.js` を読み込みます。
- **WYSIWYG**：`<textarea class="wysiwyg" data-upload-url="...">` を置き、`wysiwyg_suneditor.js` か `wysiwyg_summernote.js` を読み込みます（どちらでもサーバー側は同じ）。確認・詳細画面では `{!! safe_html($input['body']) !!}` で表示します。
- **エディタは、1つの画面に1種類だけ読み込みます**。どちらのスクリプトも `textarea.wysiwyg` を探して置き換えるので、両方を読み込むとぶつかります。見本のサイトでは、ニュースの本文を summernote、固定ページの本文を SunEditor にして、両方の動作の見本にしています。

## SunEditor

**ファイル**：`packages/neobit/core/resources/js/wysiwyg_suneditor.js`・`packages/neobit/core/resources/css/wysiwyg_suneditor_content.css`　**実例**：固定ページ（`Admin\PageController`・`PageController`、画面は `admin/pages/`・`pages/show.blade.php`）

```blade
{{-- 入力の画面。エディタの見た目のCSSは、JSが一緒に読み込む --}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['vendor/neobit/core/resources/js/wysiwyg_suneditor.js'])
@endpush

{{-- 表示の画面（確認・詳細・訪問者の側）。本文を class="sun-editor-editable" で囲む --}}
@push('head-extra')
    @vite(['vendor/neobit/core/resources/css/wysiwyg_suneditor_content.css'])
@endpush

<div class="wysiwyg-content sun-editor-editable">{!! safe_html($page->body) !!}</div>
```

- **パッケージは `suneditor`（3版）** です。MIT ライセンスで、ほかのパッケージにも jQuery にも頼りません。案件ごとにライセンスを確かめたり、キーを入れたりする必要はありません。
- **使う機能は、自分で選んで組みます**。入れる機能は `wysiwyg_suneditor.js` の冒頭の `PLUGINS`、ツールバーの並びは `TOOLBAR` です。変えるときは、スクリプトをアプリへコピーして直します（下の「エディタのスクリプトを変えるとき・選ぶとき」）。太字や元に戻すのような基本のボタンは本体に入っているので、`PLUGINS` に書くのは、見出し・表・画像のような追加の機能だけです。
- **機能を足したら、`HtmlSanitizer` の許可も合わせます**。本文に新しいタグや属性が出るようになるためです。動画の埋め込み（`video`）は、配置に使う `style` の `position` を `HtmlSanitizer` が許可していないので、入れていません。
- **画像は、アップロードするか、URL を指定して入れます**。ファイルを選んだとき（ボタン・ドラッグ＆ドロップ・貼り付け）は、`onImageUploadBefore` がほかのエディタと同じ送り先へ送ります。URL を指定したときは何も送らず、その URL のまま本文に入ります。URL で入れた画像は、保存先へ移しも消しもしません（`AjaxFileUpload` が扱うのは、`src` が一時ファイルかそのレコードの保存先の画像だけです）。大きさ・配置・キャプション・代替テキストは、画像の画面で決められます。
- **表示は日本語です**。同梱の翻訳（`suneditor/langs/ja`）を読み込んでいます。
- **表示の画面には、表示用の CSS が要ります**。SunEditor は、画像の回り込みや表の罫線を、クラス（`se-component`・`__se__float-left` など）で保存します。`class="sun-editor-editable"` で囲んで `wysiwyg_suneditor_content.css` を読み込むと、エディタの中と同じ見た目になります。書体・文字の大きさ・色は、表示する画面のものに合わせてあります。summernote は見た目を `style` 属性で保存するので、この CSS は要りません。
- **保存のときに、`data-se-` で始まる属性は消えます**。SunEditor が画像に付ける管理用の属性で、`HtmlSanitizer` が取り除きます。表示にも、編集し直すときにも影響しません。

## エディタのスクリプトを変えるとき・選ぶとき

エディタのスクリプト（`wysiwyg_suneditor.js`・`wysiwyg_summernote.js`）は、フレームワークの `packages/neobit/core/resources/js/` にあります。そのまま使える既定の形です。

**ツールバーや使う機能を変えるとき**は、アプリへコピーして直します。

1. `vendor/neobit/core/resources/js/wysiwyg_suneditor.js` を、アプリの `resources/js/` へコピーして直す
2. `vite.config.js` の `input` と、画面の `@vite()` を、コピーのほうへ書き換える

アップロードの送信は `@neobit/upload_request.js` から読み込んでいます。`@neobit` は `vite.config.js` で決めたフレームワークのスクリプトの場所なので、コピーしてもそのまま動きます。コピーした後は、フレームワークを更新しても、そのスクリプトには直しが届きません。

**使わないエディタは、外せます**。どちらも使わないサイトは、両方を外します。

| 使わないエディタ | 外すもの |
|---|---|
| SunEditor | `package.json` の `suneditor`、`vite.config.js` の `input` の `wysiwyg_suneditor.js`・`wysiwyg_suneditor_content.css` |
| summernote | `vite.config.js` の `input` の `wysiwyg_summernote.js`、画面で CDN から読み込んでいる summernote の行。jQuery を使う画面がほかに無ければ、レイアウトの jQuery も |

SunEditor は npm のパッケージなので、`package.json` から外したのに `vite.config.js` に `wysiwyg_suneditor.js` が残っていると、`npm run build` が失敗します。両方を合わせて外します。summernote は CDN から読み込むので、`package.json` には関係しません。

## 固定ページの URL

固定ページは、URL の名前（`t_pages.slug`）がそのままパスになります。`aboutus` なら `/aboutus` です。

- **ルートは `routes/pages.php` に置き、`bootstrap/app.php` がいちばん最後に読みます**。ほかの全部の画面を先に当てて、どれにも当たらなかった URL だけを受けるためです。画面を足すときは、ほかのルートのファイルに書きます。
- **ほかの画面と同じ名前のページは作れません**。`login`・`news`・`admin` のような、ほかの画面の URL の最初の区切りと同じ名前は、検証で断ります（`NotReservedPathRule`）。ルートの一覧から調べるので、画面を足しても、直す所はありません。
- **名前の形**は、検証もルートも `SlugRule` のパターン（`SlugRule::NESTED_PATTERN`）を使います。食い違うと、登録できるのに開けないページができるためです。
- 後から足した画面の URL が、すでにあるページの名前と重なったときは、画面のほうが先に当たり、ページは開けなくなります。ページの名前を変えます。
- 表示名（元のファイル名）が空のときは、リンクの文字を「添付ファイル1」のようにします（`_ajax_upload_block` と訪問者向けのニュース詳細で実装済み）。

## ログインした人だけが見られるファイル（非公開）

**実例**：会員の顔写真（`Member::PRIVATE_FILE_FIELDS`、`Admin\MemberController`・`MypageController`）、お問い合わせの添付ファイル（`Inquiry::PRIVATE_FILE_FIELDS`、`ContactController`）、ニュースのファイル（`News::PRIVATE_FILE_FIELDS`、`NewsPolicy`）

個人情報のように、URL を知っているだけで誰でも見られては困るファイルは、持ち主のモデルに、非公開にするフィールドの名前を並べます。公開か非公開かはデータ項目の性質なので、コントローラーではなくモデルが決めます。同じモデルに公開と非公開のフィールドがあってもかまいません。コントローラー・画面の書き方は、公開のファイルと同じです。

```php
/** モデル。組の中のフィールド（attach.*.file）は画面から送る名前（attach_file）、WYSIWYG欄は欄の名前 */
public const PRIVATE_FILE_FIELDS = ['photo'];
```

```php
/** そのモデルの Policy（例：app/Policies/MemberPolicy.php）。$user はログイン中の会員かスタッフ */
public function viewFiles(Member|Staff $user, Member $member, string $field): bool
{
    return $user instanceof Staff || $user->id === $member->id;
}
```

```php
/** ログインしていない人にも見せることがあるなら、$user を null 可にする（例：app/Policies/NewsPolicy.php） */
public function viewFiles(Member|Staff|null $user, News $news, string $field): bool
{
    return $user instanceof Staff || $news->isVisibleTo($user);   // 一般公開なら誰でも、会員限定なら会員だけ
}
```

- 非公開のフィールドのファイルは `"public"` ディスクではなく `"local"` ディスク（`storage/app/private`、Web サーバーから直接は見えない）に、公開と同じ規則のディレクトリ（`member/000/000005/` のように id を上位と下位に分けた2階層）で保存されます。
- URL は `/uploads/{種類}/{id}/{フィールド}/{ファイル名}`（ルート `uploads.show`。フレームワークの `routes/web.php` が登録するので、アプリのルートには書きません）になります。`UploadedFileController` は、そのファイルが今そのフィールドに保存されているものかを DB で確かめ、Policy の `viewFiles()` で許されたときだけ返します。見てはいけない人には 404 を返します。`$field` で、フィールドごとに見てよい人を変えられます。
- Policy には、まずログインしていない人（`$user` が null）として聞きます。許されれば、誰にでも見せてよいファイルとして、ブラウザやプロキシに残してよい形（`Cache-Control: public, no-cache` と ETag。2回目からは変わっていなければ 304）で返します。毎回サーバーに問い合わせさせるので、記事を会員限定や非表示に変えれば、その時点から見られなくなります。
- 許されなければ、ログイン中のユーザー（会員・スタッフのどのガードでも）の誰かが許されたときだけ、ブラウザに残させない形（`Cache-Control: private, no-store`）で返します。
- 一般公開と会員限定を切り替えられるデータのように、見せてよい人が変わるものは、全部のフィールドを非公開の場所に置き、見せるかどうかを Policy でデータの今の状態から決めます。置き場所で分けると、切り替えのたびにファイルを移し、本文の画像の URL も書き換えることになるためです。
- 見せる人を絞る必要の無いフィールドは、`PRIVATE_FILE_FIELDS` に書きません（公開の `"public"` ディスクのまま）。PHP を通さずに Web サーバーが直接返すので軽く、手前に Nginx などを置けばそこで返せます。非公開は、制御が要るフィールドだけに使います。
- URL の「種類」は `AppServiceProvider` の `Relation::enforceMorphMap()` の名前です。非公開のフィールドを持つモデルは、必ずそこに載せます（載っていなければ URL を作るときに例外）。
- `UploadFilePath::url(クラス, id, フィールド, ファイル名)`・`upload_preview_url()`・モデルのアクセサは、そのまま非公開の URL を返します。PDF に埋め込む・メールに添付するときなど、サーバー上のパスが要るときは `UploadFilePath::path(クラス, id, フィールド, ファイル名)` を使います（例：`Member::photo_path`、`ContactController` の通知メール）。
- 退会などでレコードを消すときは、FormFlow の `deleteData()` か、`deleteAllUploads($record)` をトランザクションの中で呼びます（実例：`MypageController::destroy()`）。公開・非公開の両方のディレクトリが消えます。
- 運用を始めた後にフィールドを公開から非公開へ（または逆へ）変えるときは、すでにあるファイルをディスクの間で移すマイグレーションを書きます（実例：`move_inquiry_attach_files_to_private_disk`・`move_news_files_to_private_disk`。WYSIWYG 欄の本文の `<img>` の URL も書き換えます）。

## 保存先の規則を変えるとき

保存先のディレクトリは、`config/neobit.php` の `upload` の節の `directory_rule` に書いたクラスが決めます。既定は `DefaultUploadDirectory`（`news/000/000012`）です。プロジェクトで変えたいときは、`UploadDirectoryRule` を実装したクラスを書き、設定にその名前を書きます。メソッドの名前は `directory()` に決まっています。

```php
// app/Services/AppUploadDirectory.php：id をそのまま1階層にする例（news/12）
final class AppUploadDirectory implements UploadDirectoryRule
{
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        return Str::snake(class_basename($ownerClass)).'/'.(int) $ownerKey;
    }
}

// config/neobit.php の upload の節
'directory_rule' => App\Services\AppUploadDirectory::class,
```

- 受け取るのは、モデルのクラス名と id だけです。親の id だけが手元にある場面でも、レコードを読まずに使えるようにするためです。そのため、登録した日のようなレコードの中身は使えません。
- 1件ごとに専用のディレクトリにします。レコードを消すとき、フレームワークはディレクトリごと消すので、ほかの行のファイルが同じ所や下にあると、一緒に消えてしまいます。
- モデルごとに分けます。会員とニュースで同じ id があっても、ぶつからないようにするためです。
- `..` や先頭・末尾の `/` を含む値を返すと、`UploadFilePath` が例外にします。
- 運用を始めてから変えると、すでにあるファイルを読み出せなくなるので、変えません。どうしても変えるときは、ファイルを移すマイグレーションが要ります。

## 一時ファイル（tmp）

アップロードした直後のファイルは、公開・非公開に関係なく `"local"` ディスクの `upload_tmp/`（`storage/app/private/upload_tmp/`）に置き、URL は `/uploads/tmp/{ファイル名}`（ルート `uploads.tmp`）です。アップロードしたときにセッションへファイル名を覚えておき、同じセッション（アップロードしたブラウザ）にだけ返します。保存するときに、持ち主のモデルのディスク（公開なら `"public"`）へ移します。保存されずに残った一時ファイルは、24時間を過ぎるとスケジューラーが消します（[scheduler.md](scheduler.md)）。

## 消えるファイル

古いファイルは、保存の前後で「そのレコードがどこからも参照しなくなったファイル」だけを、トランザクションの確定後に消します。WYSIWYG の本文から外した画像も同じです（[rules.md](rules.md)の「トランザクション」）。
