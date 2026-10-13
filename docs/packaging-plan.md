# パッケージ化の作業計画

ネオビットフレームワーク（`packages/neobit/core/`）を、見本のサイト（memsys）から切り離して配れる形にするための計画。作業の途中で経緯を忘れても、ここを読めば続きから始められるように書く。

作業のブランチは `packaging`。段階が1つ済むたびに、この文書の「状態」の列を書き換える。

## 目指す形

まっさらな Laravel のプロジェクトに `neobit/core` を入れるだけで、フレームワークの部品が動く。プロジェクトは、変えたい所だけを自分の側に書いて上書きする。

今は、フレームワークが見本のサイトの側に置いたファイル（設定・メールのテンプレート・JavaScript）を前提にしている所が残っている。それを無くすのが、残りの作業の中心になる。

## 決まっている方針

2026-10-14 に決めたもの。

| 対象 | 方針 |
|---|---|
| 設定の値 | パッケージの側に既定の値を持たせる。プロジェクトは Laravel の仕組みで上書きする |
| メールのテンプレート | 設定と同じ。パッケージの側に既定のテンプレートを持たせ、プロジェクトが上書きする |
| JavaScript・CSS など | パッケージの中に置く。プロジェクトの `vite.config.js` に、`vendor/neobit/` から始まるパスを直接書く |

## 済んだ作業

タグ `v5` が、パッケージ化に着手する前の状態。それ以降に `packaging` で済ませたもの。

| 中身 | 目印 |
|---|---|
| 共通部品を `packages/neobit/core/` へ移し、名前空間を `Neobit\Core\` にした | |
| フレームワークが業務のクラスを名指ししないようにした。対象は `UploadedFileController`・`Passkey`・`CodeTable` の DB のコード名・操作ログ | タグ `packaging-2a` |
| 画面の部品17個をフレームワークへ移し、`neobit::` の名前で呼ぶ形にした。レイアウトは `config('neobit.layouts')` で渡す | |
| アップロードファイルのルートをフレームワークへ移した | |
| 共通のテーブルのマイグレーションをフレームワークに集め、`t_codes` を `code_tables` に改名した | |
| コメントと文書に残っていた古い名前空間（`App\Support\`）を直した | |

## 残りの作業

依存の深いものから並べている。番号は、タグ `packaging-2a` の名前とは関係ない。

| 段階 | 中身 | 状態 |
|---|---|---|
| 1 | 設定の既定の値を、パッケージに持たせる | 済み |
| 2 | フレームワークが送るメールのテンプレートを、パッケージに持たせる | 未着手 |
| 3 | フレームワークの画面を動かす JavaScript と CSS を、パッケージへ移す | 未着手 |
| 4 | `CodeTable` が列挙型を探す名前空間（`App\Enums\`）の決め打ちを外す | 未着手 |
| 5 | 配布の形を決める | 未着手 |
| 6 | 文書を、切り離した後の形に合わせる | 未着手 |

### 段階1　設定の既定の値

済み。フレームワークが読む設定の、既定の値の置き場所は次のとおり。

| 設定のキー | 読んでいる部品 | 既定の値の置き場所 |
|---|---|---|
| `upload.*`（5つ） | `AjaxFileUpload`・`CsvReader`・`CsvImport` | パッケージの `config/upload.php` |
| `cleanup.operation_log_days`・`failed_job_days`・`tasks` | `TemporaryDataCleaner` | パッケージの `config/cleanup.php` |
| `neobit.admin_requests_per_minute`・`admin_requests_exceeded_message` | `AdminRequestLimit` | パッケージの `config/neobit.php` |
| `neobit.operation_report_to`・`operation_report_thresholds` | `ReportOperationLogs`・`OperationLogReport` | パッケージの `config/neobit.php` |
| `app.site_name` | 画面2つ・`ErrorNotifyHandler`・`ReportOperationLogs`・`TeamInvitationManager` | プロジェクトの `config/app.php` |
| `services.turnstile.*` | `SpamGuard`・`_spam_guard.blade.php` | プロジェクトの `config/services.php` |
| `login.{種類の名前}.legacy_passwords` | `LegacyPasswordUserProvider` | プロジェクトの `config/login.php` |

- パッケージの `config/` に置いたファイルを、サービスプロバイダーが `mergeConfigFrom()` で既定の値にする。プロジェクトの `config/` に同じ名前のファイルで書いたキーが優先する
- 管理画面の回数の制限と、操作ログの報告の設定は、`config/app.php`・`config/logging.php` から `neobit.*` へ移した。フレームワークだけが読む値で、Laravel 自身の設定ファイルに間借りしたままだと、パッケージが既定の値を持てないため
- `app.site_name`・`services.turnstile.*`・`login.*` は動かしていない。`site_name` はプロジェクトの画面も読み、`turnstile` は Laravel の習わしどおりの場所にあり、`login` は種類の名前がプロジェクトごとに違う。どれも、無ければ空として動く
- 期限の切れた招待の片付けは、`TemporaryDataCleaner` が自分で呼ぶ。プロジェクトの `cleanup.tasks` には、業務のデータの片付けだけを書く
- `php artisan vendor:publish --tag=neobit-config` で、既定の値のファイルをプロジェクトの `config/` へ写せる

後の段階でも気を付けること。

- `mergeConfigFrom()` が混ぜるのは、いちばん上の階層のキーだけ。配列の値（`upload.image_types`、`cleanup.tasks`、`neobit.operation_report_thresholds`）は、プロジェクトが書くと丸ごと置き換わる。中の1つだけを変えるつもりで一部だけ書くと、残りが消える

### 段階2　メールのテンプレート

フレームワークが送るメールのテンプレートは、すべてプロジェクトの `resources/mail-templates/` にある。`MailTemplate` は `resource_path()` だけを見るので、無ければ送信で例外になる。

| テンプレート | 送る部品 | 名前の決まり方 |
|---|---|---|
| `error_notify` | `ErrorNotifyHandler` | 固定 |
| `operation_log_report` | `ReportOperationLogs` | 固定 |
| `{種類の名前}_verification_code` | `LoginVerificationCode` | ログインする人の種類ごと |
| `{種類の名前}_password_changed` | `PasswordChange` | 同じ |
| `{種類の名前}_profile_changed` | `LoginProfileNotice` | 同じ |
| `{種類の名前}_invitation` | `TeamInvitationManager` | 同じ |

進め方。

- パッケージの `resources/mail-templates/` に既定のテンプレートを置く
- `MailTemplate` は、プロジェクトの `resources/mail-templates/` に同じ名前のファイルがあればそれを使い、無ければパッケージのものを使う
- プロジェクトへ写す手段（`vendor:publish --tag=neobit-mail-templates`）を足す

着手するときに決めること。

- 種類の名前（`member`・`company_user`・`staff`）はプロジェクトのものなので、パッケージは `member_verification_code` という名前のファイルを持てない。パッケージには種類の名前を除いた名前（`verification_code`）で置き、「種類ごとのテンプレート → 種類を除いた名前のテンプレート」の順に探す形が候補
- 今の見本のサイトでは、種類ごとに文面が違う。`password_changed` はスタッフ用だけ `login_id`・`changed_by_name` を使う。既定のテンプレートは、どの種類でも渡される変数だけで書く必要がある。部品が渡している変数を先に調べる

### 段階3　JavaScript と CSS

フレームワークの画面や部品を動かすスクリプトが、プロジェクトの `resources/js/` にある。

| ファイル | 対応するフレームワークの部品 | 移すか |
|---|---|---|
| `ajax_upload.js`・`upload_request.js` | `AjaxFileUpload`・`_ajax_upload_block`・`_ajax_upload_group` | 移す |
| `passkeys.js` | `PasskeyLogin`・`PasskeyManagement`・`_passkeys` | 移す |
| `wysiwyg_suneditor.js`・`wysiwyg_summernote.js`・`wysiwyg_suneditor_content.css` | `AjaxFileUpload`・`HtmlSanitizer` | 着手するときに決める |
| `app.js`・`app.css` | 全画面で使う。フォームの補助など | 着手するときに中身を見て分ける |
| `sortable.js`・`code_editor.js`・`contact_form.js` | 見本のサイトの画面 | 移さない見込み |

進め方。

- 移すファイルを `packages/neobit/core/resources/js/` に置く
- プロジェクトの `vite.config.js` の `input` と、画面の `@vite()` に、`vendor/neobit/core/resources/js/passkeys.js` のように書く

確かめること。

- 手元の `vendor/neobit/core` は、`packages/neobit/core` へのジャンクション。Vite がリンクをたどって実体のパス（`packages/…`）でマニフェストに載せると、`@vite('vendor/…')` が見つけられなくなる。移した直後に `npm run build` で確かめる。手元でビルドした `public/build` は、サーバーへは送らない
- スクリプトが import している npm のパッケージ（`@laravel/passkeys`・`suneditor`・`sortablejs`）は、プロジェクトの `package.json` に書く。パッケージの側にどう書いて伝えるかを決める
- PDF のフォント（`resources/fonts/ipaex`。`PdfDownload` が `resource_path()` で読む）の置き場所も、ここで決める

### 段階4　`CodeTable` の名前空間

`CodeTable` は、コード名から列挙型を探すときの名前空間に `App\Enums\` を直接書いている。フレームワークのコードがプロジェクトの側を名指ししているのは、ここだけ。`config('neobit.*')` で渡す形にして、既定の値を `App\Enums\` にする。

### 段階5　配布の形

人が決める事柄が多いので、後ろに回している。

- 置き場所。今は同じリポジトリの中の path リポジトリで、版は `*@dev`。別のリポジトリに分けるか、このリポジトリから書き出すか
- 版の付け方と、プロジェクトの `composer.json` での指定のしかた
- フレームワークのテストの置き場所。今は、見本のサイトの `tests/` が両方を確かめている
- 新しい案件を始めるときに配るもの。フレームワークのほかに、見本のサイトのどこまでを雛形にするか
- WYSIWYG エディタの選び方の手順を、利用ガイド7章へ書く。`npm ci` の前に `package.json` と `vite.config.js` を書き換えてもらう形

### 段階6　文書

- `docs/new-project-files.md` は 2026-10-05 時点の調査。書き換えるファイルの一覧を、切り離した後の形で調べ直す
- 利用ガイドに、設定・画面・メールのテンプレートを上書きする方法と、`vendor:publish` のタグをまとめる
- `docs/project-overview.md` のディレクトリの表を合わせる
- 済んだら、この計画書を消すか、設計の記録として残すかを決める

## 段階ごとの進め方

1. 変える予定のファイルを一覧で示し、「go」を待つ
2. 実装する。フレームワークの決まり（`docs/neobit-framework-rules.md`）を先に読む
3. `php artisan test` と `vendor/bin/pint --test` を通す
4. 変更の種類（`add:`・`update:`・`fix:`）ごとにコミットを分ける。`git push` は指示があったときだけ
5. この文書の「状態」を書き換える
