# 全体像

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

## 動作条件

| | 最低バージョン | 根拠の代表例 |
|---|---|---|
| PHP | 8.3 | Laravel 13 本体が PHP 8.3 以上を要求する（`laravel/framework` の composer.json が `"php": "^8.3"`）。フレームワーク自身のコードで使っている一番新しい書き方は、列挙型の定数のキーに case の値を使う `self::Gender->value => [...]`（PHP 8.2 から。`StaffAcl::LABELS`） |
| Laravel | 13 | 見本のサイト（memsys）の composer.json が `"laravel/framework": "^13.17"` で、この版で開発・動作確認している。フレームワークのコードで使っている機能の一番新しいものは、モデルのスコープを属性で書く `#[Scope]`（Laravel 12 から。`News::visible()`） |

- PHP の最低バージョンは、フレームワーク自身の書き方ではなく、Laravel 13 の要求で決まっています。
- Laravel 12 でも動く可能性はありますが、動作を確かめていないので対象外とします。新しい案件は、その時点の最新の Laravel で始める前提です。
- このほか、パスキー（[login.md](login.md)）を使う場合は、ブラウザで開くアドレスが HTTPS であること（開発時の `localhost` は例外）が必要です。
- 新しい機能を足して、ここに書いたより新しい PHP・Laravel の機能を使ったときは、この表を直します。

## 部品の一覧

- **`SearchableList`**
  - 場所：`packages/neobit/core/src/Crud/`
  - 概要：一覧・検索（検索条件の検証と保存、完全一致・部分一致の自動判定、並び順、ページと検索条件の復元）
  - 詳細：[list.md](list.md)
- **`FormFlow`**
  - 場所：`packages/neobit/core/src/Crud/`
  - 概要：入力 → 確認 → 保存、削除（トランザクション込み）、必須マーク
  - 詳細：[form.md](form.md)・[delete.md](delete.md)
- **`InputNormalizer`・`NormalizeInput`**
  - 場所：`packages/neobit/core/src/Crud/`・`packages/neobit/core/src/Http/Middleware/`
  - 概要：入力の全角と半角の揺らぎをそろえる（画面の入力と CSV 取り込み。検証の前）
  - 詳細：[form.md](form.md)
- **`SecurityHeaders`**
  - 場所：`packages/neobit/core/src/Http/Middleware/`
  - 概要：ブラウザに守り方を伝えるヘッダーを、全部の応答に付ける
  - 詳細：[setup.md](setup.md)
- **`AccessRestriction`・`RestrictSiteAccess`・`RestrictAdminAccess`・`AccessRestrictionController`**
  - 場所：`packages/neobit/core/src/Security/`・`src/Http/Middleware/`・`src/Http/Controllers/`
  - 概要：アクセス制限（サイトはメンテナンス中の画面、管理画面は 404。それぞれ別に掛ける）。管理画面の「アクセス制限」で設定する
  - 詳細：[setup.md](setup.md)
- **`AjaxFileUpload`**
  - 場所：`packages/neobit/core/src/Upload/`
  - 概要：画像・添付ファイルの Ajax アップロード、WYSIWYG の画像
  - 詳細：[upload.md](upload.md)
- **`UploadFilePath`**
  - 場所：`packages/neobit/core/src/Upload/`
  - 概要：アップロードしたファイルの保存先と URL の規則（公開・非公開・一時ファイル）
  - 詳細：[upload.md](upload.md)
- **`UploadedFileController`**
  - 場所：`packages/neobit/core/src/Http/Controllers/`
  - 概要：ログインした人だけが見られるファイルと、一時ファイルを返す
  - 詳細：[upload.md](upload.md)
- **`CodeTableController`**
  - 場所：`packages/neobit/core/src/Http/Controllers/`
  - 概要：管理画面の項目見出し一覧。DB で管理するコード表をまとめて書き換える
  - 詳細：[code-table.md](code-table.md)
- **`PdfDownload`**
  - 場所：`packages/neobit/core/src/Pdf/`
  - 概要：Blade のテンプレートから PDF を作って返す（mPDF、同梱の IPAex フォント）
  - 詳細：[pdf.md](pdf.md)
- **`HtmlSanitizer`（`safe_html()`）**
  - 場所：`packages/neobit/core/src/Upload/`
  - 概要：WYSIWYG の HTML の無害化
  - 詳細：[upload.md](upload.md)
- **`CodeTable`（`code_table()` など）**
  - 場所：`packages/neobit/core/src/Code/`・`packages/neobit/core/src/helpers.php`
  - 概要：区分表（列挙型・CSV・DB）
  - 詳細：[code-table.md](code-table.md)
- **`CsvDownload`・`CsvColumnSet`・`CsvWriter`**
  - 場所：`packages/neobit/core/src/Csv/`
  - 概要：CSV ダウンロードと、CSV の項目の定義、CSV の共通の書き出し方
  - 詳細：[csv-download.md](csv-download.md)
- **`CsvImport` ほか**
  - 場所：`packages/neobit/core/src/Csv/`
  - 概要：CSV 取り込み（取り込み画面と保存の流れ）
  - 詳細：[csv-import.md](csv-import.md)
- **`CsvRead`**
  - 場所：`packages/neobit/core/src/Csv/`
  - 概要：CSV を読んで確かめる部分。自分の画面を持つ機能が、CSV の読み込みと検証だけを借りるときに使う
  - 詳細：[csv-import.md](csv-import.md)
- **`MailTemplate`・`TemplatedMail`**
  - 場所：`packages/neobit/core/src/Mail/`・`packages/neobit/core/src/Mail/`
  - 概要：テンプレートファイルによるメール送信
  - 詳細：[mail.md](mail.md)
- **`LoginThrottle`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：認証の失敗回数による試行制限
  - 詳細：[login.md](login.md)
- **`LoginAccount`・`IsLoginAccount`・`HasTwoFactor`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：ログインするモデル（個人会員・企業の担当者・スタッフ）の共通の型と、名前の決まり、2段階認証にかかわるモデルの中身
  - 詳細：[login.md](login.md)・[member-types.md](member-types.md)
- **`LoginIdType`**
  - 場所：`packages/neobit/core/src/Enums/`
  - 概要：ログインに使う値の種類（メールアドレス・ログインID・どちらでも）。モデルの定数で選ぶ
  - 詳細：[member-types.md](member-types.md)
- **`TwoFactorLogin`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：ログインの、パスワードが合った後の流れ（2段階目・信頼済みの端末・ログインの確定）と、ログアウト
  - 詳細：[login.md](login.md)
- **`TwoFactorChallenge`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：その人の2段階目の方式で、本人かどうかを確かめる
  - 詳細：[login.md](login.md)
- **`TwoFactorManagement`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：ログイン中の本人による、バックアップコードの発行と、認証アプリの登録の解除
  - 詳細：[login.md](login.md)
- **`PasskeySuggestion`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：パスワードでログインした人に、パスキーの登録を1回だけ勧める
  - 詳細：[login.md](login.md)
- **`LegacyPasswordUserProvider`・`LegacyPassword`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：既存のシステムから移した会員の、古い方式のパスワードの照合と、今の方式への置き換え
  - 詳細：[login.md](login.md)
- **`LoginIdMemory`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：ログイン画面の入力（企業ID と担当者ID）を、ブラウザに覚えさせる
  - 詳細：[login.md](login.md)
- **`TeamInvitationManager`・`TeamInvitationOwner`・`HasTeamInvitations`**
  - 場所：`packages/neobit/core/src/Team/`
  - 概要：集団への招待（リンクの発行・送り直し・取り消し・照合）。企業会員の担当者の招待に使う。集団のモデルに、インターフェースとトレイトを付ける
  - 詳細：[member-types.md](member-types.md)
- **`LoginSession`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：そのガードだけのログアウト（同じブラウザのほかのログインは残す）
  - 詳細：[login.md](login.md)
- **`LoginRedirect`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：ログインの後の移動先（開こうとしていた画面へ戻す。戻り先は、ログインの種類ごとに覚える）
  - 詳細：[login.md](login.md)
- **`LoginVerificationCode`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：メールで送る確認コード
  - 詳細：[login.md](login.md)
- **`EmailChange`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：本人がメールアドレスを変えるときに、新しいアドレスへの確認コードの入力を挟む
  - 詳細：[login.md](login.md)
- **`TrustedDeviceManager`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：2段階目を省略できる信頼済み端末
  - 詳細：[login.md](login.md)
- **`TwoFactorAuthenticator`・`BackupCodeGenerator`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：認証アプリ（TOTP）のコードと、バックアップコード
  - 詳細：[login.md](login.md)
- **`PasskeyLogin`・`PasskeyManagement`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：パスキーでのログインと、本人によるパスキーの登録・削除
  - 詳細：[login.md](login.md)
- **`PasswordChange`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：パスワードを変えたときの後始末（信頼済み端末・パスキーの無効化、お知らせのメール）
  - 詳細：[login.md](login.md)
- **`_confirm_hidden`**
  - 場所：`packages/neobit/core/resources/views/`
  - 概要：確認画面の hidden を `$input` から組み立てる
  - 詳細：[form.md](form.md)
- **`_ajax_upload_block`**
  - 場所：`packages/neobit/core/resources/views/`
  - 概要：アップロード欄（1つだけの欄と、子テーブルの中の欄）
  - 詳細：[upload.md](upload.md)
- **`ChildTables`・`_repeatable_group`**
  - 場所：`packages/neobit/core/src/Crud/`・`packages/neobit/core/resources/views/`
  - 概要：1:多の子テーブルを、親と同じ画面で入力して保存する（画面の部品は `_repeatable_group`）
  - 詳細：[form.md](form.md)
- **`admin/csv_import/`**
  - 場所：`packages/neobit/core/resources/views/`
  - 概要：CSV 取り込みの画面（全コーナー共通）
  - 詳細：[csv-import.md](csv-import.md)
- **`_passkeys`**
  - 場所：`packages/neobit/core/resources/views/`
  - 概要：パスキーの一覧・本人確認・登録の画面の中身
  - 詳細：[login.md](login.md)
- **`TemporaryDataCleaner`**
  - 場所：`packages/neobit/core/src/Operations/`
  - 概要：一時データの後片付け（一時ファイル・期限の切れたキャッシュと信頼済み端末・古い操作ログ）。プロジェクトの片付けは `config/neobit.php` の `cleanup` の節に足す
  - 詳細：[scheduler.md](scheduler.md)
- **`neobit:cleanup-temporary-data`**
  - 場所：`packages/neobit/core/src/Console/`
  - 概要：一時データの後片付けのコマンド（スケジューラーから1時間ごと）
  - 詳細：[scheduler.md](scheduler.md)
- **`neobit:reset-two-factor`**
  - 場所：`packages/neobit/core/src/Console/`
  - 概要：2段階認証の登録を解除するコマンド（画面から解除できる人がいないときに使う）
  - 詳細：[login.md](login.md)
- **`neobit:set-next-id`**
  - 場所：`packages/neobit/core/src/Console/`
  - 概要：新しく登録する行の id の始まりを決めるコマンド（サイトを開ける前に使う）
  - 詳細：[table-model.md](table-model.md)
- **`ErrorNotifyHandler`**
  - 場所：`packages/neobit/core/src/Operations/`
  - 概要：ログに書いたエラーを開発者にメールで知らせる（レベル・宛先は .env、間隔は `config/logging.php`）
  - 詳細：[error-notify.md](error-notify.md)
- **`OperationRecorder`**
  - 場所：`packages/neobit/core/src/Operations/`
  - 概要：操作ログ（誰が・いつ・どこから・何に・何をしたか）を書く
  - 詳細：[operation-log.md](operation-log.md)
- **`OperationLogStats`・`OperationLogReport`**
  - 場所：`packages/neobit/core/src/Operations/`
  - 概要：操作ログの件数の集計（一覧の上のグラフと表）と、1日ぶんの報告
  - 詳細：[operation-log.md](operation-log.md)
- **`neobit:report-operation-logs`**
  - 場所：`packages/neobit/core/src/Console/`
  - 概要：操作ログの報告のコマンド（スケジューラーから毎朝。気になる点があった日だけメールを送る）
  - 詳細：[operation-log.md](operation-log.md)
- **`AdminRequestLimit`**
  - 場所：`packages/neobit/core/src/Security/`
  - 概要：ログイン後の管理画面の全体に掛ける、スタッフごとの回数の制限
  - 詳細：[operation-log.md](operation-log.md)
- **`LoginProfileNotice`**
  - 場所：`packages/neobit/core/src/Auth/`
  - 概要：会員情報が変わったことを、本人へメールで知らせる
  - 詳細：[operation-log.md](operation-log.md)
- **`SpamGuard`・`_spam_guard`**
  - 場所：`packages/neobit/core/src/Security/`・`packages/neobit/core/resources/views/`
  - 概要：訪問者向けフォームのスパム対策（ハニーポット・送信までの時間・Cloudflare Turnstile）
  - 詳細：[public-form.md](public-form.md)
- **`global.js`・`global.css`**
  - 場所：`packages/neobit/core/resources/js/`・`css/`
  - 概要：全画面で使うスクリプトと CSS。フォームの補助（必須マークから required 属性、エラー表示）など
  - 詳細：[blade.md](blade.md)

トレイトは、コントローラーが `use` するだけで働きます。コントローラーに書くのは「このコーナーの項目の定義」と「ルートから呼ばれる入口」だけ、というのが全体の考え方です。

## コントローラーの並び順

どのコントローラーも、次の順にコメントのブロックで区切って書きます。

```php
class NewsController extends Controller
{
    // ---- 共通処理（トレイト） ----
    use SearchableList;
    use FormFlow;
    use AjaxFileUpload;
    use CsvDownload;
    use CsvImport;

    // ---- 一覧・検索（SearchableList）の設定 ----
    private const INDEX_ROUTE = 'admin.news.index';
    // ...

    // ---- アップロード（AjaxFileUpload）の設定 ----
    private const UPLOAD_FILES = [...];

    // ---- このコーナーの項目の定義 ----
    private function rules(): array { ... }
    private function formValues(...): array { ... }
    // defaultValues()・prepareInput() は必要なときだけ

    // ---- 一覧・検索 ----
    public function index(...) { ... }
    private function searchRules(): array { ... }
    private function applyCustomSearch(...): bool { ... }

    // ---- CSVダウンロード ----  ---- CSV取り込み ----
    // ---- 登録 ----
    // ---- 詳細・編集・削除 ----

    // ---- 保存・削除処理の補助（FormFlow） ----
    private function saveValues(...): array { ... }
    private function afterSave(...): void { ... }
    private function beforeDelete(...): void { ... }
    // afterSave()・beforeDelete() は必要なときだけ
}
```

- 「このコーナーの項目の定義」には、検証のルールや入力欄の値のような、項目の定義を返すメソッドを置きます。
- 保存する値を作る `saveValues()` と、保存の直後・削除の直前に呼ばれる `afterSave()`・`beforeDelete()` は、ルートから呼ばれる入口のブロックの後ろに「保存・削除処理の補助」のブロックを作り、呼ばれる順に置きます。実際に保存・削除するのは入口の `store()`・`update()`・`destroy()` で、このブロックには、そこから呼ばれる部品を置きます。入口の後ろに、トレイトから呼ばれるメソッドのブロック（2段階認証の管理など）や、コントローラーの中で使い回す処理のブロックがあるときは、それらより前に置きます。

- 定数はクラスの冒頭にまとめ、何で使うものか（一覧・検索の設定、アップロードの設定など）でブロックに区切ります。
- 1か所からしか呼ばれない数行の処理は、細かいメソッドに分けず、コメントを1行入れて呼び元に書きます。
- トレイトの「必要なときだけ書くメソッド」は、トレイト側に何もしない版があります。コントローラーに同じ名前で書けば、そちらが使われます。

## 全体に共通する約束

- **`$input` には送信される項目だけを入れる**：コントローラーが画面に渡す `$input` は、フォームから送信される（次の画面へ hidden で持ち越す）項目だけにします。表示だけに使う値（区分の名称、プレビューの URL など）は `$input` に混ぜず、画面の中でヘルパーを呼んで求めます（`code_label()`・`upload_preview_url()` など）。こうしておくと、確認画面の hidden を `$input` から機械的に作れます。
- **検証のルールは `rules()` に書く**：画面の必須マークは `rules()` から組み立てます。`validate([...])` にルールを直接書かず、`rules()` に切り出します（必須マークを出さない、フォームではない送信だけは例外）。
- **検証のエラーの文言は「この項目は〜」と書く**：`lang/ja/validation.php`。エラーは入力欄のすぐ下に出すので、項目の名前は入れません。項目の名前の対応表（`attributes`）も持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。ほかの項目と比べるルール（`after_or_equal:publish_start_at` など）は、相手の項目の名前が英語のまま出るので、その項目のそのルールの文言を、同じファイルの `custom` に書きます。
- **生成物の形を決める呼び出しは、名前付き引数で全部書く**：`downloadCsv()`・`CsvImportSettings`・`downloadPdf()` の引数には既定の値を持たせていないので、全部を名前付き引数で書きます。どんな結果になるかが、呼び出しの1か所で分かります。
- **定義の中の値の変換**：書式の変換は `'カラム|date:Y/m/d'` のような短い記法、外から渡す一覧による置き換えは配列、特殊な変換はクロージャではなく英字の識別名（`'@age'`）でコントローラーのメソッドを呼びます。
- **画面ごとに決まる値は、コントローラーの定数に持つ**：共通部品に画面ごとの一覧を持たせません（例：試行制限の `THROTTLE_SCOPE`）。画面が増えても共通部品を変えずに済むようにするためです。
- **データ項目の仕様はモデルの定数に持つ**：画像の横幅のように、どの画面から登録しても変わらない仕様はモデルの定数にし、コントローラーや画面はそれを参照します（例：`News::LIST_IMAGE_WIDTH`）。
- **選択肢のように決まった種類の値は列挙型にする**：`app/Enums/`。区分表として一覧を使うときは、出どころに関係なく `code_table()` 系のヘルパーを通します（[code-table.md](code-table.md)）。
- **テンプレートは細かく部品化しない**：デザイナーが触れる HTML のまま保ちます。コーナーの入力欄は `_fields.blade.php` 1つにまとめ、4つの画面から呼びます（[form.md](form.md)）。Blade にコントローラー名は書きません。
- **設定の既定の値は、フレームワークが持つ**：既定の値は、`vendor/neobit/core/config/neobit/` に機能ごとのファイルで持ちます（`route`・`layouts`・`form`・`code_table`・`operation_log`・`security`・`file_watch`・`seo`・`schedule`・`upload`・`cleanup`）。
- **案件は `config/neobit.php` の1ファイルに、変える値だけを書く**：機能ごとの節で書きます（例：`'seo' => ['redirects' => true]`）。書いたキーが既定に優先し、書いていないキーは既定の値になります。こうしておくと、案件が何を変えたかが1つのファイルで分かり、パッケージの更新で足した既定の値が、案件のファイルを直さずに効きます。節の中の配列（`neobit.upload.image_types`・`neobit.operation_log.report_thresholds` など）は丸ごと置き換わるので、変えない要素も一緒に書きます。その部分は、パッケージの更新が効かなくなるので、更新のときに見比べます。`php artisan vendor:publish --tag=neobit-config` で、見本（`packages/neobit/core/stubs/config/neobit.php`）を `config/` へ写せます。
- **フレームワークの設定を変えるために、`config/` にファイルを増やさない**：案件の機能の設定は、名前の頭に `site_` を付けたファイル（`site_contact.php`・`site_login.php`・`site_bulk_mail.php`）に置きます。`config/` を見て、フレームワークの設定（`neobit.php`）・案件の設定（`site_` のファイル）・Laravel のファイル（それ以外）が見分けられるようにするためです。フレームワークは `site_` のファイルを読みません。
- **Blade のコメントは `{{-- --}}` で書く**：`<style>` や `<script>` の中でも同じです。`/* */`・`//`・`<!-- -->` は HTML のソースに出て、訪問者に見えるので使いません。
- **コメント**：今のコードが何をしていて、なぜそうなっているかだけを書きます。試行錯誤の経緯は書きません。書き方は「コメントの書き方」にまとめてあります。

## コメントの書き方

コメントは、削りすぎずに「なぜ」を残します。コードを読めば、何をしているかは分かります。分からないのは、そうしている理由です。理由が書いていないと、後から触る人も AI も、不要に見える処理を消したり、よくある形に直したりして、元の狙いを壊します。

コメントの分だけ、ファイルは長くなります。それでも、理由を取り違えて作り直す手間の方がずっと大きいので、理由のコメントは惜しまずに書きます。

**残すコメント**

| 種類 | 例 | ある所 |
|---|---|---|
| そうしている理由 | メールアドレスが変わったときは、変わる前のアドレスにも送る。他人に書き換えられたときに本人が気付けるのは、変わる前のアドレスだけだから | `LoginProfileNotice` |
| あえてしていないこと | パスワードと同じ bcrypt は使わない。同じ値でも毎回違うハッシュ値になるので検索できず、記録の数だけログインが遅くなる | `TrustedDeviceManager` |
| 順番や場所に意味があること | `/members/csv` は `/members/{member}` より前に書く。後ろだと csv が会員の id として扱われる | `routes/admin.php` |
| ほかの所とのつながり | 自分自身は削除できない。判断は `StaffPolicy` | `routes/admin.php` |
| 処理の段落の見出し | 行を消す前に、お知らせの宛先を控える | `Admin\CompanyController::reject()` |

「あえてしていないこと」は、特に書いておきます。コードに無いものは、読んでも気付けないためです。

**書かないコメント**

| 種類 | 例 | 理由 |
|---|---|---|
| コードの言い換え | `// idを取得する` | コードを読めば分かる。コードを直したときに、コメントだけ古くなる |
| 試行錯誤の経緯 | 以前は〇〇だったが、△△に変えた | 今のコードを読む人には要らない。経緯は Git の履歴に残る |
| 名前を変えた理由 | 分かりにくいので名前を変えた | 同じ |

**置く場所**

- クラス・メソッドの上には、全体の目的と「なぜそうするか」を書きます。1文目に何をするものかを書き、理由はその後に続けます。
- 説明は、それが当てはまる行のすぐそばに置きます。離れた所にまとめると、コードを直したときに直し忘れます。
- コメントだけを上から読めば、処理の流れと分岐が追えるようにします。処理の段落ごとに、何をするかを1〜2行で書きます。
- 部品の細かい仕様は、その部品のファイルの冒頭に書きます。このガイドには「どこを見て、どう組むか」だけを書きます。

**形**

- コメントと画面の文言は日本語で書きます。
- クラス・関数・メソッド・定数・プロパティ・列挙型の `case` の上の説明は、PHPDoc の形（`/** */`）で書きます。VS Code などのエディタが、使っている側でその説明を表示できるようにするためです。1行で済む説明は `/** 説明 */` と1行で書きます。
- 処理の中のコメントは、行コメント（`//`）にします。短いコメントで、処理の流れを追いやすくするためです。
- `@param`・`@return` などの型の注記は、宣言の型で表せないとき（配列の形など）だけ書きます。
- 補足は括弧書きにせず、別の文にします。読点は、節の切れ目くらいにとどめます。
- Blade では `{{-- --}}` で書きます（[blade.md](blade.md)）。

**直すとき**

コードを直したら、そばのコメントも一緒に直します。理由が変わったのにコメントが元のままだと、無いよりも悪くなります。コメントに書いた理由がもう当てはまらないと気付いたら、コメントを消すか、今の理由に書き直します。
