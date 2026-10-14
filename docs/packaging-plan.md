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
| プロジェクトの設定ファイル | 既定と同じ値は、頭に `//` を付けて見本として見せる。変えた値だけが生きた行になる。パッケージは、既定の値（`config/`）とは別に、値をコメントにした写す元（`stubs/config/`）を持つ |
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
| 1-2 | 設定の写す元を、値をコメントにした見本にする。見本のサイトの設定ファイルも同じ形にする | 済み |
| 2 | フレームワークが送るメールのテンプレートを、パッケージに持たせる | 済み |
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
- `php artisan vendor:publish --tag=neobit-config` で、設定の見本をプロジェクトの `config/` へ写せる。写す元は段階1-2

後の段階でも気を付けること。

- `mergeConfigFrom()` が混ぜるのは、いちばん上の階層のキーだけ。配列の値（`upload.image_types`、`cleanup.tasks`、`neobit.operation_report_thresholds`）は、プロジェクトが書くと丸ごと置き換わる。中の1つだけを変えるつもりで一部だけ書くと、残りが消える

### 段階1-2　設定の写す元

済み。2026-10-14 に決めた形。

| 役割 | 置き場所 | 中身 | 使われ方 |
|---|---|---|---|
| 既定の値 | パッケージの `config/upload.php` など | 値が生きている | `mergeConfigFrom()` が読む |
| 写す元 | パッケージの `stubs/config/upload.php` など | 値に `//` を付けた見本 | `vendor:publish --tag=neobit-config` がプロジェクトの `config/` へ写す |

- 見本のサイトの `config/upload.php`・`config/cleanup.php`・`config/neobit.php` も、既定と同じ値をコメントにしてある
- 既定の値と写す元は別のファイルなので、ずれる。写す元の `//` を外した結果が既定の値と一致することを、テスト（`ConfigStubTest`）で確かめている
- `neobit` の設定のキーは、全部に既定の値がある。`db_code_types`・`code_table_merge` は空の配列、`operation_log_actions` はフレームワークの操作の種類だけ、`operation_log_route` は `null`
- 写す元では、値の行を「`//` の後ろが `'`・`[`・`]`・`\` で始まる行」と決めている。テストがこの決まりで `//` を外すので、説明のコメントをこれらの文字で始めない

### 段階2　メールのテンプレート

済み。フレームワークが送るメールの既定のテンプレートは、パッケージの `resources/mail-templates/` にある。

| テンプレート | 送る部品 | 名前の決まり方 |
|---|---|---|
| `error_notify` | `ErrorNotifyHandler` | 固定 |
| `operation_log_report` | `ReportOperationLogs` | 固定 |
| `verification_code` | `LoginVerificationCode` | ログインする人の種類ごと |
| `password_changed` | `PasswordChange` | 同じ |
| `profile_changed` | `LoginProfileNotice` | 同じ |
| `invitation` | `TeamInvitationManager` | 同じ |

- `MailTemplate` は、プロジェクトの `resources/mail-templates/` を先に探し、無ければパッケージのものを使う
- 種類ごとのテンプレートは、3段で探す。プロジェクトの `member_verification_code`、プロジェクトの `verification_code`、パッケージの `verification_code` の順。プロジェクトに種類の名前を付けない名前で置くと、全部の種類に共通の文面になる
- 探す名前は、`TemplatedMail` の名前に配列で渡す。部品が `[種類の名前を付けた名前, 付けない名前]` を書く。`LoginAccount` にメソッドは足していない
- `php artisan vendor:publish --tag=neobit-mail-templates` で、既定のテンプレートをプロジェクトへ写せる
- 既定のテンプレートは、部品が渡す値だけで展開できる。プロジェクトのルート（お問い合わせの画面など）には頼らない。本人以外がパスワードを変えたときの文面には、変えた人の氏名を出さない。会員宛てのメールにスタッフの氏名が出るため
- 件名の頭は `【サイトの名前】` にそろえた。確認コード・パスワードの変更・登録情報の変更の部品が、`site_name` を渡す。`SITE_NAME` が空のサイトでは `【】` になるので、プロジェクトの側でテンプレートを置いて書き換えてもらう
- 見本のサイトは、種類ごとのテンプレート（`member_…`・`company_user_…`・`staff_…`）を残し、件名だけ同じ形にそろえた。`error_notify` と `operation_log_report` は消して、パッケージの既定を使う

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

2026-10-14 に話した形。案件は数十から100以上を見込む。開発とテストは、今後も memsys で行う。リクエストを受けて memsys で実装とテストをしてから、フレームワークの部分を配り直す。

| リポジトリ | 役割 | 直す人 |
|---|---|---|
| memsys | フレームワークと見本のサイトの両方。`packages/neobit/core/` も git で管理する | 開発する人 |
| 配る用のリポジトリ（新規） | `packages/neobit/core/` の中身だけ。版のタグを付けて配る | 誰も直さない。memsys からの書き出しだけを受け取る |

- 版を出すときに、`git subtree split` で `packages/neobit/core/` の履歴だけを取り出して、配る用のリポジトリへ送る。流れは memsys から配る用への一方向
- 各案件は、`composer.json` に配る用のリポジトリの場所と版を書いて取り込む。案件に `packages/` は無く、フレームワークは `vendor/neobit/core/` に入る
- git submodule にはしない。フレームワークと見本のサイトを、1つのコミットで直せなくなるため
- 配る用のリポジトリは直接直さない。案件で見つかった不具合も、memsys に持ち帰って直す

着手するときに決めること。

- 版の付け方。直しただけの版、機能を足した版、今までの書き方が動かなくなる版を、番号で見分けられるようにする
- 版ごとの変更の記録。案件の側で直すことと、設定の見本が変わったときの案内を書く
- サーバーからの取得。サーバーで `composer install` を実行するので、配る用のリポジトリを読む手段がサーバーごとに要る。読み取り専用の鍵を配るか、認証付きの配布用の置き場を1つ立てるか
- 新しい案件の雛形。memsys からフレームワークを除いた部分も、案件を始めるときの元として配る。雛形の `composer.json` は、手元のパスではなく、配る用のリポジトリの版を見るようにする
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
