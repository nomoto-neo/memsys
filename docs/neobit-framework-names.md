# フレームワークの名前の一覧

案件のコードが、名前で頼っているフレームワークの部品の一覧。ここにある名前を変えると、配った後は全案件のコードを直すことになる。名前を変えるときは、この一覧も直す。

2026-11-02 に、段階4-5 の総点検で見直した。決めた内容は `docs/name-review-spec.md` にある。「メモ」の列は、使うときの補足。

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
| `FormFlow` | `operationLogActionType($created)` | | 操作ログに残す操作の種類 | |
| `SearchableList` | `searchRules()` | ○ | 検索項目の検証ルール | |
| `SearchableList` | `applyCustomSearch($query, $key, $value)` | ○ | 列と直接比べられない検索条件 | |
| `SearchableList` | `indexRoute()` | | 一覧のルートの名前。`INDEX_ROUTE` で決められないとき（ルートの名前を案件が付けるフレームワークの画面）に書く | |
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
| `CsvRead` | `readCsv(...)` | CSV を読んで検証する | |
| `AjaxFileUpload` | `uploadAjaxFile($request)` | 入口。アップロードを受け取る | |
| `AjaxFileUpload` | `ajaxUploadRules()` | アップロードの欄の検証ルール | |
| `ChildTables` | `childTableRules()` | 子テーブルの検証ルール | |
| `SeoFields` | `seoRules()` | 記事ごとの SEO の欄の検証ルール | |
| `AjaxFileUpload` | `ajaxUploadValues($model, $source)` | アップロードの欄の画面の値 | |
| `AjaxFileUpload` | `commitUploads($model, $input, $fromImport)` | 一時ファイルを正式な保存先へ移す | |
| `AjaxFileUpload` | `deleteAllUploads($model)` | 1件分のファイルを全部消す | |
| `AjaxFileUpload` | `checkImportedUploadFilename(...)` | CSV で取り込むファイル名の確かめ | |
| `PdfDownload` | `downloadPdf(...)`・`downloadPdfOnSheet(...)` | PDF のダウンロード。定型紙に文字を載せる形も | |
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
| `SearchableList`・`CsvImport` | `INDEX_ROUTE` | 一覧のルートの名前 | |
| `SearchableList` | `PER_PAGE` | 1ページの件数 | |
| `SearchableList` | `ORDER_OPTIONS` | 並び順の選択肢 | |
| `SearchableList` | `FREE_WORD_COLUMNS` | フリーワード検索の対象の列 | |
| `AjaxFileUpload` | `UPLOAD_FILES` | アップロードの欄。欄の名前 => `type`・`width`・`required`（書き方は `UploadFieldDefinitions`）。いくつでも足せる欄は、子テーブルの中の欄 `組.*.列` で書く | |
| `ChildTables` | `CHILD_TABLES` | 子テーブル。組の名前 => `relation`・`fields`・`order`・`delete_insert`・`max`・`fixed`・`initial`・`min`（書き方は `ChildTableDefinitions`） | |
| `AjaxFileUpload` | `WYSIWYG_IMAGE_FIELDS` | エディタの欄と、入れた画像を縮小する横幅 | |
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
| 操作ログ | `operationLogNames($ids)`（`OperationLogNamedModel`） | 操作ログに出す名前 | |
| 操作ログ | `operationLogSummary()`・`operationLogDetails()`・`operationLogUrl()`（`OperationLogRelatedRecord`） | 件数を1行にまとめた文字、内訳の窓の項目、詳しい画面の URL | |
| ログイン | `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX`・`LOGIN_ID_TYPE`（定数） | ガード・ルートの頭・ログインに使う値の種類。無ければ種類の名前 | |
| ログイン | `TWO_FACTOR_METHOD`・`BACKUP_CODES`（定数） | 2段階認証の方式、バックアップコードを使うか | |
| SEO | `seoTitle()`・`seoDescriptionSource()`・`seoImageUrl()`（`SeoSubject`） | タイトル・説明文の元・シェアの画像 | |
| SEO | `sitemapQuery()`（静的）・`sitemapUrl()`（`SitemapSource`） | サイトマップに載せる記事のクエリと URL | |
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
| インターフェース | `OperationLogNamedModel` | 操作ログに名前を出すモデル | |
| インターフェース | `OperationLogRelatedRecord` | 操作ログの件数を持つ記録 | |
| インターフェース | `TeamInvitationOwner` | 招待を持つモデル | |
| インターフェース | `UploadDirectoryRule` | 保存先の規則 | |
| インターフェース | `SheetPagingRule` | 定型紙のページの使い方 | |
| インターフェース | `SeoSubject` | SEO の値の元になる記事のモデル | |
| インターフェース | `SeoArticle`（`seoPublishedAt()`・`seoModifiedAt()`） | 構造化データで記事として出すモデル。`SeoSubject` を継ぐ | |
| インターフェース | `SitemapSource` | サイトマップに載せる記事のモデル | |
| モデルのトレイト | `HasSeoMeta` | 記事ごとの SEO の欄（`seoMeta()`）を持つ | |
| モデル | `IpBlock` | IPブロック管理の1行 | |
| モデル | `Setting` | 管理画面から変えられる設定の1行（キーと値） | |
| モデルのトレイト | `IsLoginAccount`・`HasTwoFactor`・`HasPasskeys`・`HasTeamInvitations` | ログイン・2段階認証・パスキー・招待 | |
| コントローラーのトレイト | `FormFlow`・`SearchableList`・`AjaxFileUpload`・`ChildTables`・`SeoFields`・`CsvDownload`・`CsvImport`・`CsvRead`・`PdfDownload`・`EmailChange`・`TwoFactorLogin`・`TwoFactorManagement`・`PasskeyLogin`・`PasskeyManagement` | | |
| 列挙型 | `UploadType`（`Image`・`File`）・`SheetPaging`（`Once`・`Sequential`・`Cycle`）・`OperationLogAction`（`ip_block`・`ip_unblock`・`access_restriction_change`・`seo_settings_change`・`basic_auth_change`・`basic_auth_user_add`・`basic_auth_user_delete` を含む）・`TwoFactorMethod`・`LoginIdType`・`CsvEncoding`・`CsvImportMode`・`SpamCheckResult` | | |

## 6. 案件が呼ぶクラスと、その公開のメソッド

| クラス | 公開のメソッドと定数 | メモ |
|---|---|---|
| `CodeTable` | `get()`・`normalizeKey()` | ふつうはヘルパーから使う |
| `FrameworkRoutes` | `codes()`・`ipBlocks()`・`accessRestriction()`・`basicAuth()`・`fileWatch()`・`seoSettings()`・`redirects()`・`operationLogs()` | フレームワークの画面のルートを1行で書く。案件の `routes/admin.php` が呼ぶ |
| `UploadFieldDefinitions` | `of()`・`find()` | ふつうは `AjaxFileUpload` と `upload_field()` が使う |
| `UploadFilePath` | `directory()`・`isPrivate()`・`disk()`・`url()`・`path()`・`tmpUrl()`・`previewUrl()`、定数 `TMP_DIR`・`TMP_DISK`・`TMP_SESSION_KEY`・`TMP_SESSION_MAX`・`PUBLIC_DISK`・`PRIVATE_DISK`・`SAFE_FILENAME` | |
| `OperationRecorder` | `record()`・`loggableFields()` | |
| `CsvWriter` | `download()`・`fetch()` | CSV の共通の書き出し方 |
| `Seo` | `for()`・`noindex()`・`breadcrumb()`・`breadcrumbs()`・`jsonLd()`・`value()`、定数 `KEYS`・`IMAGE_URL_PATTERN` | ふつうはコントローラーが `for()`・`noindex()` を呼び、画面は `seo()` で読む |
| `RedirectResolver` | `find()`・`hit()`・`forget()`・`normalize()`・`isWildcard()`・`prefix()`・`encodeLocation()`、定数 `WILDCARD` | リダイレクトの当て方 |
| `RedirectChecker` | `errors()`・`opensPage()` | リダイレクトの登録のときの確かめ |
| `SeoSettings` | `titleFormat()`・`description()`・`imageUrl()`・`siteVerification()`・`organizationName()`・`organizationLogoUrl()`・`page()`・`pageRoutes()`・`settings()`・`save()`・`extractVerificationContent()` | 管理画面の「SEO設定」の値 |
| `SlugRule` | 定数 `PATTERN`・`NESTED_PATTERN` | ルートの `where()` に使う |
| `NotReservedPathRule` | `isReserved()` | |
| `OperationLog` | `actionOptions()`・`modelOptions()`・`modelNames()`・`operatorLabel()`・`relatedRecords()`・`operatorTypes()`・`relatedKey()`・`countSummary()`・`csvName()`・`detailText()` | |
| `OperationLogStats` | `hourly()`・`daily()`・`topOperators()`・`countsBy()` | |
| `OperationLogReport` | `for()` | |
| `TemplatedMail` | `new TemplatedMail($template, $variables)` | |
| `MailTemplate` | `render()` | |
| `MailTemplateParser` | `parse()` | |
| `PasswordChange` | `resetAndNotify()` | |
| `LoginProfileNotice` | `send()` | |
| `LoginVerificationCode` | `issue()`・`issueForAddress()`（引数 `purposeLabel:` は省略可）・`hasPending()`・`verify()`・`verifyForAddress()`、定数 `PURPOSE_〜` | |
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
| `FileWatcher` | `init()`・`check()`・`inspect()`・`acceptAlerts()`・`requestApproval()`・`pendingApproval()`・`applyApproval()`・`recentlyAccepted()`・`acceptedOn()`、定数 `DEFAULT_PATHS` | 改ざん監視 |
| `PublicFiles` | `handle()`・`file()`・`isDirectory()` | 入口の `public/index.php` が `handle()` を呼ぶ |
| `gatekeeper.php`（パッケージの直下） | 定数 `NEOBIT_GATEKEEPER_STATE` | 入口の `public/index.php` の頭で読む。状態のファイルの場所を定数で渡す。WordPress には、`auto_prepend_file` か `wp-config.php` の `require` でこのパスを書く |
| 設定 `neobit.wordpress` | キー `gatekeeper`・`admin_paths`・`open_paths`・`hidden_paths`・`wp_config`・`upload_path`・`watch_exclude`、`.env` の `WORDPRESS_GATEKEEPER`（`prepend`・`wp-config`） | WordPress を置く案件の `config/neobit.php` が書く |
| `PublicScriptResponse` | 古い `.php` を動かす目印の応答。`script` | |
| `BasicAuth` | `isOn()`・`save()`・`addUser()`・`deleteUser()`、定数 `AREAS`（`site`・`admin`）・`USERNAME_PATTERN` | 範囲ごとの目印のファイルと `.htpasswd` を作る |
| `AccessRestriction` | `allowsSite()`・`allowsAdmin()`・`allowedIps()`・`siteIps()`・`adminIps()`・`envSiteIps()`・`envAdminIps()`・`settings()`・`save()`・`turnOff()`・`parseList()`・`lockoutMessage()` | |
| `IpBlocker` | `isBlocked()`・`countMiss()`・`block()`・`changeExpiry()`・`release()`・`deleteExpired()`・`normalizeRange()`・`rangeBounds()`・`coversProtected()` | |
| `CsvImportSettings`・`CsvImportResult`・`CsvImportRow`・`CsvColumnSet` | 取り込みの設定と結果 | |
| 検証のルール | `KatakanaRule`・`HiraganaRule`・`PhoneNumberRule`・`SameCountAsRule`・`UploadRequiredRule`・`SlugRule`・`NotReservedPathRule` | |
| ミドルウェア | `AjaxFormResponse`・`ApplyRedirects`・`NoIndexHeader`・`NormalizeInput`・`RejectBlockedIp`・`RestrictAdminAccess`・`RestrictSiteAccess`・`RunScheduleOnRequest`・`SecurityHeaders` | |
| コントローラー | `CodeTableController`・`IpBlockController`・`AccessRestrictionController`・`BasicAuthController`・`FileWatchController`・`SeoSettingsController`・`RedirectController`・`OperationLogController`（どれもルートは案件が `FrameworkRoutes` で書く）・`UploadedFileController`・`SitemapController`・`RobotsController`・`PublicFileController`（どの URL も受けるルートを案件が書くときは、自分のページが無ければこれに渡す） | |

## 7. ヘルパー

| 名前 | 中身 | メモ |
|---|---|---|
| `code_table($codeName)` | コード表の一覧 | |
| `code_keys($codeName)` | コード表の値の一覧 | |
| `code_label($codeName, $value, $default, $separator)` | 値の名前 | |
| `code_options($options, $selected)` | プルダウンの `<option>` | |
| `is_match($value, $key)` | 入力値が、選択肢の値に当たっているか。`@checked()`・`@selected()` に渡す | |
| `safe_html($html)` | エディタの HTML を安全にする | |
| `upload_input_value($input, $key, $idx)` | アップロードの欄の値 | |
| `upload_preview_url($model, $input, $field, $idx)` | アップロードの欄に出す URL | |
| `upload_field($field, $key)` | 今の画面のコントローラーの `UPLOAD_FILES` から、欄の `type`・`width`・`required`・`multiple` を1つ返す | |
| `seo($key)` | 今のページの SEO の値（`title`・`description`・`image`・`url`・`canonical`・`type`・`site_name`・`robots`・`site_verification`）を1つ返す | |
| `required_mark($area)` | 必須マーク | |
| `required_fields($rules, $alsoRequired)` | 必須マークの配列 | |

## 8. 設定のキー

| ファイル | キー | メモ |
|---|---|---|
| `neobit` | `site.name` | 画面やメールに出すサイトの名前。`.env` は `SITE_NAME` |
| `neobit` | `auth.legacy_passwords` | ログインする人の種類の名前ごとに、古い方式のパスワードのクラス。既定は空 |
| `neobit` | `layouts` | |
| `neobit` | `route.admin_path` | 管理画面の頭の URL。既定は `app/admin` |
| `neobit` | `file_watch`（`release_file`・`to`・`public_upload_extra`） | |
| `neobit` | `code_table.db`・`code_table.method`・`code_table.enum_namespaces` | |
| `neobit` | `operation_log.actions`・`operation_log.models`・`operation_log.route` | |
| `neobit` | `security.admin_requests_per_minute`・`security.admin_requests_exceeded_message` | |
| `neobit` | `security.ip_block_auto`・`security.ip_block_watch_minutes`・`security.ip_block_watch_count`・`security.ip_block_hours` | |
| `neobit` | `operation_log.report_to`・`operation_log.report_thresholds` | |
| `neobit` | `schedule.cron_enabled` | `.env` は `CRON_ENABLED` |
| `neobit` | `seo`（`title_format`・`description`・`description_length`・`image_url`・`organization_name`・`organization_logo_url`・`noindex_routes`・`sitemap_routes`・`sitemap_models`・`robots_disallow`・`page_routes`・`redirects`） | |
| `neobit` | `upload`（`image_types`・`attach_types`・`max_kb`・`max_image_pixels`・`csv_max_kb`・`directory_rule`） | |
| `neobit` | `cleanup`（`operation_log_days`・`failed_job_days`・`tasks`） | |
| `neobit` | `form.required_mark`（`admin`・`public`） | |
| `neobit` | `security.trusted_proxies`・`security.site_allowed_ips`・`security.admin_allowed_ips` | `.env` は `TRUSTED_PROXIES`・`SITE_ALLOWED_IPS`・`ADMIN_ALLOWED_IPS` |

## 9. 画面と、渡す変数

部分ビューは `@include('neobit::名前', [...])` で呼ぶ。案件の `resources/views/vendor/neobit/` に同じ名前で置くと上書きできる。

| 画面 | 渡す変数 | メモ |
|---|---|---|
| `_ajax_upload_block` | `$model`・`$input`・`$field`・`$readonly`・`$uploadUrl`・`$idx` | 種類・横幅・まとめて選べるかは `upload_field()` で読む。組の中の欄は `$idx` を渡す |
| `_repeatable_group` | `$group`・`$row`・`$model`・`$input`・`$readonly`・`$uploadUrl`・`$addLabel`・`$inline`（省略可） | 行の中身の部分ビュー（`$row`）は `$model`・`$input`・`$idx`・`$readonly`・`$uploadUrl` を受け取る |
| `_confirm_hidden` | `$input`・`$exclude` | |
| `_seo_head` | なし | 値は `seo()` で読む。訪問者の側のレイアウトの `<head>` に置く。構造化データも出す |
| `_breadcrumbs` | なし | パンくずリスト。訪問者の側のレイアウトの本文の上に置く |
| `_seo_fields` | `$input`・`$readonly` | 記事ごとの SEO の欄。送る名前は `seo_title`・`seo_description`・`seo_image_url`・`seo_noindex` |
| `_spam_guard`・`_passkey_suggest` | なし | |
| `_passkeys` | `$passkeys`・`$passkeyRoute`・`$identityConfirmed`・`$codeSent`・`$confirmBy`・`$twoFactorMissing` | |
| `_two_factor` | `$twoFactorState`・`$twoFactorRoutes`・`$twoFactorLoginId`・`$twoFactorQrCode`・`$twoFactorSecret`・`$twoFactorBackupCodes`・`$rememberLabel` | |
| `_two_factor_backup_code_form` | `$twoFactorRoutes`・`$backupCodeLead`・`$rememberLabel` | |
| `_two_factor_backup_codes` | `$codes`・`$twoFactorNextUrl`・`$nextLabel` | |
| `_two_factor_manage` | `$twoFactorAccount`・`$twoFactorRoutes`・`$totpConfirmed`・`$usesBackupCodes`・`$backupCodesCodeSent`・`$confirmByEmail`・`$issueLabel`・`$resetAlso` | |
| `_two_factor_reset` | `$twoFactorAccount`・`$twoFactorResetUrl` | |
| `admin.code_tables.index`・`admin.ip_blocks.index`・`admin.access_restriction.index`・`admin.basic_auth.index`・`admin.seo_settings.index`・`admin.redirects.index`・`admin.operation_logs.index`・`admin.operation_logs._table`・`admin.csv_import.form`・`admin.csv_import.confirm`・`admin.too_many_requests` | フレームワークのコントローラーが渡す | |
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
| `seo.sitemap`・`seo.robots` | フレームワークが登録する。`/sitemap.xml` と `/robots.txt` | |
| `public-files` | フレームワークが登録するフォールバックのルート。公開ディレクトリの HTML と古い PHP | |
| `{ログインの頭}.login`・`.login.verify`・`.login.verify.confirm`・`.login.verify.resend`・`.password.forgot` | 案件が書く。ログインの部品が、この名前で探す | |
| 項目見出し一覧の「頭.index」「頭.update」 | 案件が書く | |
| IPブロック管理の「頭.index」「頭.store」「頭.update」「頭.destroy」 | 案件が書く。update・destroy の URL に `{ipBlock}` | |
| 操作ログの「頭.index」「頭.search」「頭.csv」「頭.day」 | 案件が書く | |
| アクセス制限の「頭.index」「頭.update」 | 案件が書く | |
| ベーシック認証の「頭.index」「頭.update」「頭.store」「頭.destroy」 | 案件が書く。update・store の URL に `{area}`（`site`・`admin`）、destroy の URL に `{basicAuthUser}` | |
| SEO設定の「頭.index」「頭.update」 | 案件が書く | |
| リダイレクトの「頭.index」「頭.store」「頭.update」「頭.destroy」「頭.csv」「頭.csv-import」「頭.csv-import.confirm」「頭.csv-import.execute」 | 案件が書く。update・destroy の URL に `{redirect}` | |

## 11. メールのテンプレートと、中で使える変数

| テンプレート | 変数 | メモ |
|---|---|---|
| `verification_code` | `$code`・`$name`・`$purpose_label`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `password_changed` | `$name`・`$changed_at`・`$changed_by_other`・`$passkeys_deleted`・`$reset_url`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `profile_changed` | `$name`・`$changed_at`・`$reset_url`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `invitation` | `$inviter_name`・`$url`・`$expires_at`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `error_notify` | `$title`・`$level`・`$message`・`$exception_class`・`$location`・`$where`・`$context`・`$trace`・`$suppressed`・`$datetime`・`$app_env`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |
| `operation_log_report` | `$date`・`$counts`・`$notable`・`$operators`・`$url`・`$site_name`・`$to_mail`・`$from_mail`・`$from_name` | |

## 12. JavaScript・CSS

| 種類 | 名前 | メモ |
|---|---|---|
| ファイル | `global.js`・`global.css`・`ajax_upload.js`・`repeatable_group.js`・`upload_request.js`・`passkeys.js`・`code_editor.js`・`ip_block.js`・`wysiwyg_suneditor.js`・`wysiwyg_suneditor_content.css`・`wysiwyg_summernote.js` | |
| 短い名前 | `@neobit`（`vite.config.js` の alias） | |
| 動かすクラス名 | `ajax_submit`・`wysiwyg`・`ajax_upload_block`・`ajax_file_input`・`ajax_file_link`・`ajax_image_block`・`ajax_noimage_block`・`ajax_origin`・`ajax_tmp`・`ajax_del`・`ajax_cancel`・`repeatable_group`・`repeatable_rows`・`repeatable_row`・`repeatable_inline`・`repeatable_add`・`repeatable_remove`・`repeatable_template`・`ajax_submit_message`・`passkey_login`・`passkey_register`・`passkey_message`・`passkey_supported`・`passkey_unsupported`・`required-mark` | |
| `data-` 属性 | `data-confirm`・`data-item`・`data-field`・`data-group`・`data-files-only`・`data-fixed`・`data-is-image`・`data-upload-url`・`data-submit-url`・`data-options-url`・`data-remember`・`data-current`・`data-unsaved`・`data-switch-answer`・`data-remove-row` | |
| イベント | `repeatable:add-row`・`repeatable:remove-row` | `ajax_upload.js` が出し、`repeatable_group.js` が受ける |

## 13. そのほか

| 種類 | 名前 | メモ |
|---|---|---|
| `vendor:publish` のタグ | `neobit-config`・`neobit-mail-templates` | |
| Artisan のコマンド | `neobit:access-denied-off`・`neobit:cleanup-temporary-data`・`neobit:gatekeeper`・`neobit:report-operation-logs`・`neobit:reset-two-factor`・`neobit:set-next-id`・`neobit:file-watch` | |
| テーブル | `code_tables`・`ip_blocks`・`settings`・`operation_logs`・`csv_download_logs`・`csv_import_logs`・`trusted_devices`・`two_factor_backup_codes`・`two_factor_secrets`・`passkeys`・`team_invitations` | |
| フレームワークが作るコード表 | `operation_log_action`・`operation_log_model` | 設定の `operation_log.actions`・`operation_log.models` の列挙型から作る |
