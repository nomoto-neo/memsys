# フレームワークの名前の一覧

案件のコードが、名前で頼っているフレームワークの部品の一覧。ここにある名前を変えると、配った後は全案件のコードを直すことになる。名前を変えるときは、この一覧も直す。

2026-10-15 にコードから集めた。見直しの進み具合は、計画書（`docs/packaging-plan.md`）の段階4-5 にある。「メモ」の列は、集めたときに気付いた見直しの候補。

## 1. コントローラーが書くメソッド

トレイトが、コントローラーの同じ名前のメソッドを呼ぶ。「要否」の「○」は必ず書くもの、空は要るときだけ書くもの（トレイトに何もしない版がある）。

| トレイト | メソッド | 要否 | 中身 | メモ |
|---|---|---|---|---|
| `FormFlow` | `rules($record)` | ○ | 検証のルール | |
| `FormFlow` | `formValues($record)` | 詳細・編集があれば○ | モデルの今の値を、フォームに出す値の形にする | |
| `FormFlow` | `saveValues($validated, $record)` | ○ | 保存する値（列 => 値） | |
| `FormFlow` | `defaultValues()` | | 新規登録のフォームに出す初期値 | |
| `FormFlow` | `prepareInput($validated)` | | 検証の後、確認画面と保存の前の整形 | |
| `FormFlow` | `afterSave($record, $validated, $changedFields)` | | 保存の直後の処理 | |
| `FormFlow` | `beforeDelete($record)` | | 削除の直前の処理 | |
| `FormFlow` | `savedLogAction($created)` | | 操作ログに残す操作の種類 | 名前から中身が読み取りにくい |
| `SearchableList` | `srchRules()` | ○ | 検索項目の検証ルール | `srch` だけ略している |
| `SearchableList` | `applyCustomSearch($query, $key, $value)` | ○ | 列と直接比べられない検索条件 | |
| `CsvDownload` | `csvColumns()` | ○ | CSV の項目の定義 | |
| `CsvDownload` | `csvCustomColumn($key, $record)` | | 「@名前」の項目の値 | |
| `CsvImport` | `csvImportSettings()` | ○ | 取り込みの設定 | |
| `CsvImport` | `csvCustomImport($key, $value, $row)` | | 「@名前」の列の取り込み | |
| `CsvImport` | `validateCsvRows($result)` | | 行をまたいだ確かめ | |
| `CsvImport` | `afterCsvImportRow($row)` | | 1行保存するたびの処理 | |
| `CsvImport` | `afterCsvImport($result)` | | 全件が確定した後の処理 | |
| `CsvImport` | `processCsvRows($result)` | | 処理だけの取り込みの本体 | |
| `TwoFactorLogin` | `loginRedirectUrl()` | ○ | ログインの後の行き先 | |
| `PasskeyLogin` | `passkeyRedirectUrl()` | ○ | パスキーでログインした後の行き先 | |
| `TwoFactorManagement` | `twoFactorManageUrl($owner)` | ○ | 管理の画面の URL | |
| `TwoFactorManagement` | `twoFactorHomeUrl()` | ○ | 戻り先の URL | |

## 2. コントローラーが呼ぶメソッド

トレイトにあり、コントローラーの入口から呼ぶもの。ルートから直接呼ばれる入口のメソッドも、ルートの定義に名前が出るのでここに入れる。

| トレイト | メソッド | 中身 | メモ |
|---|---|---|---|
| `FormFlow` | `inputPageValues($record, $source)` | 新規登録・詳細・編集の画面に渡す `$input` | |
| `FormFlow` | `confirmPageValues($request, $record)` | 確認画面に渡す `$input`。検証に失敗すると入力画面へ戻る | |
| `FormFlow` | `requiredFields($record, $alsoRequired)` | 必須マークの配列 | |
| `FormFlow` | `validatedInput($request, $record)` | 検証と整形をした入力値 | |
| `FormFlow` | `pickValues($validated, $fieldNames)` | 入力値から保存する列を取り出す | |
| `FormFlow` | `saveData($record, $request)` | 登録・更新の実行 | |
| `FormFlow` | `saveValidated($record, $validated, $input, $fromImport)` | 検証済みの値の保存 | |
| `FormFlow` | `deleteData($record)` | 削除の実行 | |
| `SearchableList` | `listPageValues($request, $query)` | 一覧の画面に渡す値。`?back` ならリダイレクト | |
| `SearchableList` | `applyListConditions($query)` | 保存した検索条件と並び順を当てる | |
| `SearchableList` | `storeSearchCondition($request)` | 入口。検索条件を保存する | |
| `CsvDownload` | `downloadCsv(...)` | CSV のダウンロード | |
| `CsvImport` | `csvImport()`・`csvImportConfirm()`・`csvImportExecute()` | 入口。取り込みの画面・確認・実行 | |
| `CsvReader` | `readCsv(...)` | CSV を読んで検証する | トレイトなのに「〜er」 |
| `AjaxFileUpload` | `uploadAjaxFile($request)` | 入口。アップロードを受け取る | ほかは `ajaxUpload〜` の語順 |
| `AjaxFileUpload` | `ajaxUploadRules()` | アップロードの欄の検証ルール | |
| `AjaxFileUpload` | `ajaxUploadValues($model, $source)` | アップロードの欄の画面の値 | |
| `AjaxFileUpload` | `commitUploads($model, $input, $fromImport)` | 一時ファイルを正式な保存先へ移す | |
| `AjaxFileUpload` | `deleteAllUploads($model)` | 1件分のファイルを全部消す | |
| `AjaxFileUpload` | `checkImportedUploadFilename(...)` | CSV で取り込むファイル名の確かめ | |
| `PdfDownload` | `downloadPdf(...)` | PDF のダウンロード | |
| `PdfDownload` | `pdfImageData($path, $aspect)` | PDF に埋め込む画像 | |
| `EmailChange` | `holdForEmailChange($request, $account)` | メールアドレスの変更を確認待ちにする | |
| `EmailChange` | `emailChangeForm()`・`emailChangeConfirm()`・`emailChangeResend()`・`emailChangeBack()` | 入口 | |
| `TwoFactorLogin` | `continueAfterPassword($request, $account, $remember)` | パスワードの後の2段階認証へ進む | |
| `TwoFactorLogin` | `showVerification()`・`verifyCode()`・`resendCode()` | 入口 | |
| `PasskeyLogin` | `passkeyLoginOptions()`・`passkeyLogin()` | 入口 | |
| `PasskeyManagement` | `passkeyIndex()`・`passkeySendCode()`・`passkeyConfirm()`・`passkeyRegistrationOptions()`・`passkeyStore()`・`passkeyDestroy()` | 入口 | |
| `TwoFactorManagement` | `twoFactorIndex()`・`showNewBackupCodes()`・`sendBackupCodesCode()`・`regenerateBackupCodes()`・`selfReset()` | 入口 | |

### 決まりで書く入口とルートの名前

フレームワークのコードが呼ぶ名前ではないが、案件は見本のサイトを写して作るので、ここに載せる。Laravel の `Route::resource()` の7つの名前に、確認画面と戻る入口を足した形。確認画面は、行き先の保存の入口（`store`・`update`）の名前で呼ぶ。

| 入口のメソッド | ルートの名前 | 中身 |
|---|---|---|
| `index`・`create`・`store`・`show`・`edit`・`update`・`destroy` | `xxx.index` など同じ名前 | Laravel の標準 |
| `confirmForStore` | `xxx.confirm.store` | 新規登録の確認画面 |
| `backToCreate` | `xxx.confirm.store.back` | 新規登録の確認画面から入力画面へ戻る |
| `confirmForUpdate` | `xxx.confirm.update` | 編集の確認画面 |
| `backToEdit` | `xxx.confirm.update.back` | 編集の確認画面から入力画面へ戻る |
| `confirm`・`back` | `xxx.confirm`・`xxx.confirm.back` | 行き先が1つしかない流れ（お問い合わせ） |

## 3. コントローラーが書く定数

| トレイト | 定数 | 中身 | メモ |
|---|---|---|---|
| `SearchableList`・`CsvImport` | `INDEX_ROUTE` | 一覧のルートの名前 | ほかのトレイトの定数は頭に機能の名前が付くが、一覧の定数には付かない |
| `SearchableList` | `PER_PAGE` | 1ページの件数 | 同じ |
| `SearchableList` | `ORDER_OPTIONS` | 並び順の選択肢 | 同じ |
| `SearchableList` | `FREE_WORD_COLUMNS` | フリーワード検索の対象の列 | 同じ |
| `AjaxFileUpload` | `UPLOAD_FILES` | アップロードの欄 | 同じ |
| `AjaxFileUpload` | `WYSIWYG_FIELDS` | エディタの欄 | 同じ |
| `InputNormalizer` | `RAW_INPUT_FIELDS` | 全角と半角をそろえない項目 | |
| `TwoFactorLogin` | `LOGIN_ACCOUNT` | ログインする人のモデル | |
| `TwoFactorLogin` | `TWO_FACTOR_VIEW` | 2段階認証の画面 | |
| `TwoFactorManagement` | `TWO_FACTOR_GUARD`・`TWO_FACTOR_MANAGE_VIEW`・`TWO_FACTOR_BACKUP_CODES_VIEW` | ガードと画面 | |
| `PasskeyLogin`・`PasskeyManagement` | `PASSKEY_GUARD`・`PASSKEY_ROUTE`・`PASSKEY_VIEW` | ガード・ルートの頭・画面 | |
| `EmailChange` | `EMAIL_CHANGE_GUARD`・`EMAIL_CHANGE_VIEW`・`EMAIL_CHANGE_ROUTE`・`EMAIL_CHANGE_EDIT_ROUTE`・`EMAIL_CHANGE_DONE_ROUTE`・`EMAIL_CHANGE_DONE_MESSAGE`・`EMAIL_CHANGE_THROTTLE_SCOPE` | ガード・画面・ルート・文言 | |

## 4. モデルが書くもの

| 対象 | 名前 | 中身 | メモ |
|---|---|---|---|
| アップロード | `PRIVATE_FILE_FIELDS`（定数） | 非公開のファイルの欄 | |
| 入力 | `RAW_INPUT_FIELDS`（定数） | 全角と半角をそろえない項目。コントローラーが同じ名前の定数で引く | |
| 操作ログ | `OPERATION_LOG_IGNORE`（定数） | 操作ログに残さない列 | |
| 操作ログ | `operationLogNames($ids)`（`OperationLogSubjectModel`） | 操作ログに出す名前 | |
| 操作ログ | `operationLogSummary()`（`OperationLogRelatedRecord`） | 件数を1行にまとめた文字 | |
| ログイン | `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX`・`LOGIN_ID_TYPE`（定数） | ガード・ルートの頭・ログインに使う値の種類。無ければ種類の名前 | |
| ログイン | `TWO_FACTOR_METHOD`・`BACKUP_CODES`（定数） | 2段階認証の方式、バックアップコードを使うか | |
| ログイン | `displayName()`・`notificationEmail()`（`LoginAccount`） | 名前と、お知らせを送るアドレス | |
| パスキー | `getPasskeyDisplayName()`・`getPasskeyUsername()` | 端末に出る名前 | laravel/passkeys の約束の名前 |
| 招待 | `invitations()`・`invitedAccountClass()`（`TeamInvitationOwner`） | 招待のリレーションと、招待した人のモデル | |
| コード表 | `label()`（`CodeTableEnum`） | 列挙型の値の名前 | |
| 操作の種類 | `relatedKey($log)`（`OperationLogActionType`） | 件数を持つ記録 | |

## 5. インターフェース・トレイト・列挙型

| 種類 | 名前 | 中身 | メモ |
|---|---|---|---|
| インターフェース | `LoginAccount` | ログインする人のモデルの約束 | |
| インターフェース | `LegacyPassword` | 古い方式のパスワードの照合 | |
| インターフェース | `CodeTableEnum` | コード表にする列挙型 | |
| インターフェース | `OperationLogActionType` | 操作の種類の列挙型 | |
| インターフェース | `OperationLogSubjectModel` | 操作ログに名前を出すモデル | |
| インターフェース | `OperationLogRelatedRecord` | 操作ログの件数を持つ記録 | |
| インターフェース | `TeamInvitationOwner` | 招待を持つモデル | |
| インターフェース | `UploadDirectoryRule` | 保存先の規則 | |
| モデルのトレイト | `IsLoginAccount`・`HasTwoFactor`・`HasPasskeys`・`TeamInvitationRelation` | ログイン・2段階認証・パスキー・招待 | `TeamInvitationRelation` だけ `Has〜`・`Is〜` の形でない |
| コントローラーのトレイト | `FormFlow`・`SearchableList`・`AjaxFileUpload`・`CsvDownload`・`CsvImport`・`CsvReader`・`PdfDownload`・`EmailChange`・`TwoFactorLogin`・`TwoFactorManagement`・`PasskeyLogin`・`PasskeyManagement` | | `CsvReader` だけ「〜er」 |
| 列挙型 | `OperationLogAction`・`TwoFactorMethod`・`LoginIdType`・`CsvEncoding`・`CsvImportMode`・`SpamCheckResult` | | |

## 6. 案件が呼ぶクラスと、その公開のメソッド

| クラス | 公開のメソッドと定数 | メモ |
|---|---|---|
| `CodeTable` | `get()`・`normalizeKey()` | ふつうはヘルパーから使う |
| `UploadFilePath` | `directory()`・`isPrivate()`・`disk()`・`url()`・`path()`・`tmpUrl()`・`previewUrl()`、定数 `TMP_DIR`・`TMP_DISK`・`TMP_SESSION_KEY`・`TMP_SESSION_MAX`・`PUBLIC_DISK`・`PRIVATE_DISK`・`SAFE_FILENAME` | |
| `OperationRecorder` | `record()`・`loggableFields()` | |
| `OperationLog` | `actionOptions()`・`subjectNames()`・`operatorLabel()`・`relatedRecords()`・`operatorTypes()`・`relatedKey()`・`countSummary()`・`csvName()`・`detailText()` | |
| `OperationLogStats` | `hourly()`・`daily()`・`topOperators()`・`countsBy()` | |
| `OperationLogReport` | `for()` | |
| `TemplatedMail` | `new TemplatedMail($template, $variables)` | |
| `MailTemplate` | `render()` | |
| `MailTemplateParser` | `parse()` | |
| `PasswordChange` | `resetAndNotify()` | |
| `LoginProfileNotice` | `send()` | |
| `LoginVerificationCode` | `issue()`・`issueForAddress()`・`hasPending()`・`verify()`・`verifyForAddress()`、定数 `PURPOSE_〜` | コンストラクタの引数の名前が `$memberClass`（会員以外も渡す） |
| `LoginThrottle` | `isBlocked()`・`availableIn()`・`blockedMessage()`・`hit()`・`clear()` | |
| `LoginRedirect` | `remember()`・`for()` | |
| `LoginSession` | `logout()` | |
| `LoginIdMemory` | `store()`・`recall()` | |
| `TrustedDeviceManager` | `for()`・`isTrusted()`・`remember()`・`forgetAll()`、定数 `VALID_DAYS` | |
| `LegacyPasswordUserProvider` | `wrap()` | |
| `TeamInvitationManager` | `invite()`・`resend()`・`cancel()`・`find()`・`pending()`・`deleteExpired()`・`logDetail()`、定数 `VALID_DAYS` | |
| `TemporaryDataCleaner` | `all()` ほか片付けの種類ごと | |
| `InputNormalizer` | `normalize()`・`rawFieldsOf()` | |
| `HtmlSanitizer` | `clean()` | |
| `SpamGuard` | `startedToken()`・`check()`、定数 `HONEYPOT_FIELD`・`STARTED_FIELD`・`TURNSTILE_FIELD` | |
| `AdminRequestLimit` | `limit()` | |
| `CsvImportSettings`・`CsvImportResult`・`CsvImportRow`・`CsvColumnSet` | 取り込みの設定と結果 | |
| 検証のルール | `KatakanaRule`・`HiraganaRule`・`PhoneNumberRule`・`SameCountAsRule` | |
| ミドルウェア | `AjaxFormResponse`・`NormalizeInput`・`RestrictAdminAccess`・`RestrictSiteAccess`・`SecurityHeaders` | |
| コントローラー | `CodeTableController`（ルートは案件が書く）・`UploadedFileController` | |

## 7. ヘルパー

| 名前 | 中身 | メモ |
|---|---|---|
| `code_table($codeName)` | コード表の一覧 | |
| `code_keys($codeName)` | コード表の値の一覧 | |
| `code_label($codeName, $value, $default, $separator)` | 値の名前 | |
| `code_options($options, $selected)` | プルダウンの `<option>` | |
| `hit($value, $key)` | 入力値が選択肢に当たっているか | 名前から中身が読み取りにくい |
| `safe_html($html)` | エディタの HTML を安全にする | |
| `upload_input_value($input, $key, $idx)` | アップロードの欄の値 | |
| `upload_preview_url($model, $input, $field, $idx)` | アップロードの欄に出す URL | |
| `required_mark($area)` | 必須マーク | |
| `required_fields($rules, $alsoRequired)` | 必須マークの配列 | |

## 8. 設定のキー

| ファイル | キー | メモ |
|---|---|---|
| `neobit` | `layouts` | |
| `neobit` | `code_table_db`・`code_table_method`・`code_table_enum_namespaces` | |
| `neobit` | `operation_log_actions`・`operation_log_route` | |
| `neobit` | `admin_requests_per_minute`・`admin_requests_exceeded_message` | |
| `neobit` | `operation_report_to`・`operation_report_thresholds` | 操作ログの設定は `operation_log_〜`、報告は `operation_report_〜` |
| `upload` | `image_types`・`attach_types`・`max_kb`・`max_image_pixels`・`csv_max_kb`・`directory_rule` | |
| `cleanup` | `operation_log_days`・`failed_job_days`・`tasks` | |

## 9. 画面と、渡す変数

部分ビューは `@include('neobit::名前', [...])` で呼ぶ。案件の `resources/views/vendor/neobit/` に同じ名前で置くと上書きできる。

| 画面 | 渡す変数 | メモ |
|---|---|---|
| `_ajax_upload_block` | `$model`・`$input`・`$field`・`$readonly`・`$uploadUrl`・`$width` ほか | |
| `_ajax_upload_group` | `$model`・`$input`・`$field`・`$readonly`・`$uploadUrl`・`$width`・`$count` | |
| `_confirm_hidden` | `$input`・`$exclude` | |
| `_spam_guard`・`_passkey_suggest` | なし | |
| `_passkeys` | `$passkeys`・`$passkeyRoute`・`$identityConfirmed`・`$codeSent`・`$confirmBy`・`$twoFactorMissing` | |
| `_two_factor` | `$twoFactorState`・`$twoFactorRoutes`・`$twoFactorLoginId`・`$twoFactorQrCode`・`$twoFactorSecret`・`$twoFactorBackupCodes`・`$rememberLabel` | |
| `_two_factor_backup_code_form` | `$twoFactorRoutes`・`$backupCodeLead`・`$rememberLabel` | |
| `_two_factor_backup_codes` | `$codes`・`$twoFactorNextUrl`・`$nextLabel` | |
| `_two_factor_manage` | `$twoFactorAccount`・`$twoFactorRoutes`・`$totpConfirmed`・`$usesBackupCodes`・`$backupCodesCodeSent`・`$confirmByEmail`・`$issueLabel`・`$resetAlso` | |
| `_two_factor_reset` | `$twoFactorAccount`・`$twoFactorResetUrl` | |
| `admin.code_tables.index`・`admin.csv_import.form`・`admin.csv_import.confirm`・`admin.too_many_requests` | フレームワークのコントローラーが渡す | |
| `layouts.plain`・`maintenance`・`mail._raw_html`・`mail._raw_text` | | |

案件の画面がそろえる変数の名前（フレームワークの決まり）。

| 画面 | 変数 |
|---|---|
| 入力・確認・詳細 | `$input`・`$required` |
| 一覧 | `$records`・`$filters`・`$orderOptions`・`$selectedOrder` |

## 10. ルート

| 名前 | 中身 | メモ |
|---|---|---|
| `uploads.tmp`・`uploads.show` | フレームワークが登録する。一時ファイルと非公開のファイル | |
| `{ログインの頭}.login`・`.login.verify`・`.login.verify.confirm`・`.login.verify.resend`・`.password.forgot` | 案件が書く。ログインの部品が、この名前で探す | |
| 項目見出し一覧の「頭.index」「頭.update」 | 案件が書く | |

## 11. メールのテンプレートと、中で使える変数

| テンプレート | 変数 | メモ |
|---|---|---|
| `verification_code` | `$code`・`$member_name`・`$purpose_label`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | 宛先の名前がここだけ `$member_name`（ほかは `$name`） |
| `password_changed` | `$name`・`$changed_at`・`$changed_by_other`・`$passkeys_deleted`・`$reset_url`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `profile_changed` | `$name`・`$changed_at`・`$reset_url`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `invitation` | `$inviter_name`・`$url`・`$expires_at`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `error_notify` | `$title`・`$level`・`$message`・`$exception_class`・`$location`・`$where`・`$context`・`$trace`・`$suppressed`・`$datetime`・`$app_env`・`$app_name`・`$to`・`$from_mail`・`$from_name` | 宛先が `$to`（ほかは `$to_mail`）。サイトの名前が `$app_name`（ほかは `$site_name`） |
| `operation_log_report` | `$date`・`$counts`・`$notable`・`$operators`・`$url`・`$app_name`・`$to`・`$from_mail`・`$from_name` | 同じ |

## 12. JavaScript・CSS

| 種類 | 名前 | メモ |
|---|---|---|
| ファイル | `global.js`・`global.css`・`ajax_upload.js`・`upload_request.js`・`passkeys.js`・`code_editor.js`・`wysiwyg_suneditor.js`・`wysiwyg_suneditor_content.css`・`wysiwyg_summernote.js` | |
| 短い名前 | `@neobit`（`vite.config.js` の alias） | |
| 動かすクラス名 | `ajax_submit`・`wysiwyg`・`ajax_upload_block`・`ajax_upload_group`・`ajax_add_block`・`ajax_block_template`・`ajax_file_input`・`ajax_file_link`・`ajax_image_block`・`ajax_noimage_block`・`ajax_origin`・`ajax_tmp`・`ajax_del`・`ajax_cancel`・`ajax_submit_message`・`passkey_login`・`passkey_register`・`passkey_message`・`passkey_supported`・`passkey_unsupported`・`required-mark` | `required-mark` だけハイフン区切り |
| `data-` 属性 | `data-confirm`・`data-item`・`data-field`・`data-is-image`・`data-upload-url`・`data-submit-url`・`data-options-url`・`data-remember`・`data-current`・`data-unsaved`・`data-switch-answer`・`data-remove-row` | |

## 13. そのほか

| 種類 | 名前 | メモ |
|---|---|---|
| `vendor:publish` のタグ | `neobit-config`・`neobit-mail-templates` | |
| Artisan のコマンド | `app:cleanup-temporary-data`・`app:report-operation-logs`・`app:reset-two-factor`・`app:set-next-id` | 頭が案件と同じ `app:`。フレームワークのものと見分けられない |
| テーブル | `code_tables`・`operation_logs`・`csv_download_logs`・`csv_import_logs`・`trusted_devices`・`two_factor_backup_codes`・`two_factor_secrets`・`passkeys`・`team_invitations` | |
| 案件が用意するコード表 | `operation_log_subject` | 操作ログの部品が `code_label('operation_log_subject', …)` で読む。案件に無いと例外になる |
