# ネオビットフレームワーク 利用ガイド

## 目次

- [0. このガイドについて](#0.%20このガイドについて)
- [1. 全体像](#1.%20全体像)
- [2. 新しいコーナーを作る手順](#2.%20新しいコーナーを作る手順)
- [3. 一覧・検索（SearchableList）](#3.%20一覧・検索（SearchableList）)
- [4. 詳細表示](#4.%20詳細表示)
- [5. 登録・更新（FormFlow）](#5.%20登録・更新（FormFlow）)
- [6. 削除](#6.%20削除)
- [7. ファイルアップロード・WYSIWYG（AjaxFileUpload）](#7.%20ファイルアップロード・WYSIWYG（AjaxFileUpload）)
- [8. 区分表・選択肢](#8.%20区分表・選択肢)
- [9. CSV ダウンロード（CsvDownload）](#9.%20CSV%20ダウンロード（CsvDownload）)
- [10. CSV 取り込み（CsvImport）](#10.%20CSV%20取り込み（CsvImport）)
- [11. メール送信（MailTemplate・TemplatedMail）](#11.%20メール送信（MailTemplate・TemplatedMail）)
- [12. 訪問者向けのフォーム（お問い合わせ）](#12.%20訪問者向けのフォーム（お問い合わせ）)
- [13. 権限](#13.%20権限)
- [14. ログイン認証](#14.%20ログイン認証)
- [15. トランザクションとファイルの削除](#15.%20トランザクションとファイルの削除)
- [16. テーブル・モデルの決まり](#16.%20テーブル・モデルの決まり)
- [17. 画面（Blade）の決まり](#17.%20画面（Blade）の決まり)
- [18. PDF 出力（PdfDownload）](#18.%20PDF%20出力（PdfDownload）)
- [19. スケジューラー（定期的な処理）](#19.%20スケジューラー（定期的な処理）)
- [20. エラーの通知（ErrorNotifyHandler）](#20.%20エラーの通知（ErrorNotifyHandler）)
- [21. キュー（一斉メール）](#21.%20キュー（一斉メール）)
- [22. 操作ログ（OperationRecorder）](#22.%20操作ログ（OperationRecorder）)

## 版の履歴

| 版 | 日付 | 内容 |
|---|---|---|
| 第1版 | 2026-09-29 | 最初の版（一覧・検索、詳細、登録・更新、削除、アップロード・WYSIWYG、区分表、CSVダウンロード・取り込み、メール、お問い合わせ、権限、ログイン認証） |
| 第1.1版 | 2026-09-29 | フレームワークの名前を「ネオビットフレームワーク」にした |
| 第1.2版 | 2026-09-30 | CSV取り込みの設定に labelColumn（エラー・警告の行に添える列）を追加 |
| 第1.3版 | 2026-10-01 | パスキー（PasskeyLogin・PasskeyManagement）と、パスワードを変えたときの後始末（PasswordChange）を追加（14章） |
| 第1.4版 | 2026-10-01 | 区分表の出どころにDB（t_codes・項目見出し一覧）を追加（8章） |
| 第1.5版 | 2026-10-01 | 動作条件（PHP・Laravelの最低バージョン）を追加（1-1） |
| 第1.6版 | 2026-10-03 | ログインした人だけが見られるアップロードファイル（フィールド単位の `PRIVATE_FILE_FIELDS`・`UploadedFileController`）を追加し、お問い合わせの添付ファイルを非公開にした。アップロード直後の一時ファイルをアップロードしたセッションだけが見られる場所に移した（7章）。PDF 出力（`PdfDownload`）を追加（18章） |
| 第1.7版 | 2026-10-03 | 「改版について」と「まだ無い機能」を、最後の章から0章に移した |
| 第1.8版 | 2026-10-03 | 非公開のファイルを、ログインしていない人にも記事の状態などで見せられるようにした（Policy の `$user` を null 可に。一般公開のファイルはブラウザに残してよい返し方）。ニュースの全部のファイルを非公開の場所に移し、会員限定の記事を追加（7章） |
| 第1.9版 | 2026-10-03 | お問い合わせのスパム対策（`SpamGuard`。ハニーポット・送信までの時間・Cloudflare Turnstile）を追加（12章） |
| 第1.10版 | 2026-10-03 | 実例の表に、ニュースの掲載期間（掲載開始日時・掲載終了日時）を追加 |
| 第1.11版 | 2026-10-03 | スケジューラーと、一時データの後片付け（`TemporaryDataCleaner`・`app:cleanup-temporary-data`）を追加（19章） |
| 第1.12版 | 2026-10-04 | ログインの後、開こうとしていた画面へ戻す（`LoginRedirect`）。管理画面も戻すようにし、会員と管理画面で戻り先が入れ違わないようにした（14章） |
| 第1.13版 | 2026-10-04 | `downloadCsv()`・`CsvImportSettings` の引数から既定の値を外し、全部を書かないと動かないようにした |
| 第1.14版 | 2026-10-04 | エラーの通知（`ErrorNotifyHandler`）を追加。処理されなかった例外を critical で記録するようにした（20章） |
| 第1.15版 | 2026-10-04 | CSV 取り込みを、取り込み画面と保存の流れ（`CsvImport`）と、CSV を読んで確かめる部分（`CsvReader`）に分けた。処理だけのモードで `query` を null にできるようにした（10章） |
| 第1.16版 | 2026-10-04 | キューと、その見本の一斉メール（文面の管理・CSV の宛先・送信の状況）を追加（21章） |
| 第1.17版 | 2026-10-04 | サーバーへ送った後に実行するコマンド（`config:cache` が必須で、`config:clear` は実行しない）を追加（0章・19章） |
| 第1.18版 | 2026-10-04 | スパム対策で、Turnstile の判定が終わるまで案内を出して送信ボタンを押せなくする（12章） |
| 第1.19版 | 2026-10-04 | Blade のコメントは `{{-- --}}` で書く決まりを追加（1章） |
| 第1.20版 | 2026-10-04 | 一斉メールを1分に送る通数の見本の値を、さくらのレンタルサーバの送信の上限に合わせて20から5に変更（21章） |
| 第1.21版 | 2026-10-04 | エラーの通知で、同じ内容かどうかを例外の原因の行（最初に出てくる自分たちのコード）で見分ける（20章） |
| 第1.22版 | 2026-10-04 | ログのファイルの分け方（サーバーは `daily` で90日残す）を追加（20章） |
| 第1.23版 | 2026-10-04 | 冒頭に章の目次を、各章に目次へ戻るリンクを追加 |
| 第1.24版 | 2026-10-04 | サーバーの前にプロキシを置くときの設定（`TRUSTED_PROXIES`。信頼するプロキシからの X-Forwarded-For を訪問者の IP アドレスとして使う）を追加（0章） |
| 第1.25版 | 2026-10-04 | 操作ログ（`OperationRecorder`・管理画面の一覧）と、会員情報が変わったときのお知らせメール（`MemberProfileNotice`）を追加（22章）。`afterSave()` に、値が変わった列と変わる前の値を渡すようにした（5章） |
| 第1.26版 | 2026-10-04 | 検証のエラーの文言を「この項目は〜」に統一し、項目の名前の対応表をやめた（1章） |
| 第1.27版 | 2026-10-04 | エラーの通知の件名を「【要確認：サイト名】レベル：内容」に変え、本文の日時の右にレベルを載せた（20章） |
| 第1.28版 | 2026-10-04 | ログイン後の管理画面の全体に、スタッフごとの回数の制限（`AdminRequestLimit`）を追加。超えたら操作ログと通知に残す（22章） |

## 0. このガイドについて

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

ネオビットフレームワークは、Laravel の上に「コーナー（一覧・詳細・登録・編集・削除などをひとまとめにした管理単位）を同じ型で作るための共通部品」を載せたものです。見本のサイト（memsys）で、実際に動くコーナーを作りながら育てています。このガイドは、新しいコーナーや機能を作るときに、どの部品を使い、コントローラー・画面・ルートに何を書けばよいかを、機能ごとにまとめたものです。

- Laravel そのものの基礎（ルーティング、Eloquent、Blade、マイグレーション、バリデーションのルールなど）は含めていません。
- 各部品の細かい仕様（引数の意味、内部の動き、なぜそうしているか）は、それぞれのファイルの冒頭のコメントに書いてあります。このガイドは「どこを見て、どう組むか」の地図で、詳しくは各章に書いたファイルを参照してください。
- 実例として、見本のサイト（memsys）に、どの機能にも動いているコーナーがあります。迷ったら実例のコントローラーと画面をそのまま写すのが一番早い方法です。

| 実例 | 使っている機能 |
|---|---|
| 管理画面：ニュース（`Admin\NewsController`） | 一覧・検索、登録・編集・確認画面、削除、アップロード（単数・複数）、WYSIWYG、CSVダウンロード・取り込み（追加あり）、記事の状態で見せ方の変わるファイル（一般公開・会員限定・非表示） |
| 管理画面：会員（`Admin\MemberController`） | 一覧・検索、詳細・編集・確認画面、区分表（都道府県）、CSVダウンロード・取り込み（更新だけ）、`@名前` の列、ログインした人だけが見られるアップロード（顔写真）、PDF（履歴書） |
| マイページ（`MypageController`） | 確認画面なしの編集（FormFlow）、ログインした人だけが見られるアップロード（顔写真）、PDF（履歴書）、退会、パスキー |
| 管理画面：スタッフ（`Admin\StaffController`） | 一覧・検索、登録・編集、論理削除と取り消し、権限（Policy）、列挙型、パスワード |
| 管理画面：カテゴリー（`Admin\CategoryController`） | 確認画面なしの登録・編集、並び替え、ページ分けしない一覧 |
| 管理画面：項目見出し一覧（`Admin\CodeController`） | DBで管理する区分表の編集（複数行をまとめて保存、行の追加・削除・並び替え） |
| お問い合わせ（`ContactController`） | 訪問者向けの入力・確認・送信、添付ファイル、メール送信、二重送信防止、スパム対策 |
| 訪問者向けニュース（`NewsController`） | ログイン不要の一覧・検索・詳細、会員限定の記事（ログイン中の会員にだけ見せる）、掲載期間（掲載開始日時・掲載終了日時。表示するたびに今の時刻と比べるので、スケジューラーは使わない）。条件は `News::visibleTo()`・`isVisibleTo()` にまとめてある |
| 会員の認証まわり（`AuthSessionController` ほか） | ログイン、確認コード、パスワード再設定・変更、会員登録、退会、パスキー |
| 管理画面のログイン（`Admin\AuthSessionController` ほか） | TOTP・バックアップコード、信頼済み端末、パスキー |

### 改版について

- 共通部品を足したり、使い方が変わったりしたら、このガイドの該当する章を直し、冒頭の版の表に1行足します。
- 章を足したら、冒頭の目次にも1行足します。目次のリンク先は、章の見出しの文言そのままで、空白だけを `%20` に変えたものです（`## 9. CSV ダウンロード（CsvDownload）` なら `#9.%20CSV%20ダウンロード（CsvDownload）`）。Obsidian で飛べる形です。章の見出しを変えたら、目次のリンクも同じ文言に直します。章の見出しのすぐ下には、右寄せの「目次へ戻る」のリンクを置きます。Markdown には右寄せの書き方が無いので、ほかの章と同じ HTML の1行（`<p align="right"><a href="#目次" ...>目次へ戻る</a></p>`）を写します。
- 新しい機能の章を足すときは、「ファイル・実例 → コントローラーに書くもの → ルート → 画面 → 決まりごと」の順にそろえ、最後の章の後ろに足します。
- 細かい仕様は各ファイルの冒頭のコメントに書き、このガイドには「どこを見て、どう組むか」だけを書きます（同じことを2か所に詳しく書くと、片方だけ直して食い違うため）。

### サーバーへ送った後に実行するコマンド

ファイルをサーバーへ送った後、サイトのディレクトリで、上から順に要るものだけを実行します。実行するのは、デプロイ用のユーザーです。開発サーバーでは devapp で、root になる必要はありません。

| 順 | コマンド | 要るとき |
|---|---|---|
| 1 | `composer install --no-dev --optimize-autoloader` | `composer.json`・`composer.lock` を変えたとき |
| 2 | `npm ci` | `package.json`・`package-lock.json` を変えたとき |
| 3 | `npm run build` | `resources/js`・`resources/css`・`vite.config.js` を変えたとき |
| 4 | `php artisan migrate --force` | マイグレーションを足したとき |
| 5 | `php artisan config:cache` | 毎回。`.env` か `config/` を変えたときは必ず |
| 6 | `php artisan view:clear` | 毎回 |

- **`config:cache` は必須です**。サーバーでは、PHP-FPM を apache で動かし、`.env` は apache から読めないようにしています。`config:cache` は `.env` の値を取り込んだ `bootstrap/cache/config.php` を作り、Laravel はそれだけを読むようになるので、apache が `.env` を読めなくても画面も cron も動きます。
- **`config:clear` は実行しません**。`bootstrap/cache/config.php` が消えて設定の出どころが無くなり、DB が既定の SQLite になって、画面も cron（19章）も動かなくなります。
- `.env` を変えただけでは効きません。`config:cache` をやり直したときに効きます。
- `bootstrap/cache/` は、apache が読めて書けない状態にしています。ディレクトリに setgid と既定の ACL（`default:user:apache:r-x`）を付けてあるので、中に作ったファイルは誰が作っても apache が読めます。作った後に権限を直す必要はありません。`ls -l` では 660 に見えますが、末尾の `+` が ACL の印で、中身は `getfacl` で確かめます。
- 画面が「The bootstrap/cache directory must be present and writable」のエラーで止まったときは、中のファイルが古くなっています。apache は書けないので自分では作り直せません。`php artisan config:cache` をやり直します。
- `storage/` の下は、全部を apache が書けるようにします。ログのほか、コンパイル済みの画面とメールのテンプレートの展開（`storage/framework/views`）、エラーの通知の間引きの記録（`storage/framework/cache/data`）、アップロードしたファイルと一時ファイル（`storage/app`）を置くためです。書けないと、メールの送信が「tempnam(): file created in the system's temporary directory」のエラーで止まります。
- devapp が `storage/` の下に作ったファイル（artisan を実行したときのログなど）も、既定の ACL（`default:user:apache:rwx`）で apache が書けます。作った後に権限を直す必要はありません。
- 設定が効いているかは、画面を開いて確かめます。sudo を使えるときは `sudo -u apache php artisan config:show database.default` でも確かめられ、`mariadb` と出れば apache が読めています。devapp で同じコマンドを実行しても、devapp は `.env` を読めるので確かめになりません。
- 手元の開発環境は `.env` を直接読むので、`config:cache` は要りません。

### サーバーの前にプロキシを置くとき（TRUSTED_PROXIES）

ロードバランサーや CDN をサーバーの前に置くと、サーバーに直接つないでくるのはそのプロキシになります。訪問者の IP アドレスは、プロキシが付ける `X-Forwarded-For` というヘッダーで届きます。

```
# .env（プロキシの IP アドレス。カンマ区切り。範囲は 192.168.0.0/24 の形）
TRUSTED_PROXIES=10.0.0.5,10.0.0.6
```

- ここに書いたプロキシから届いたヘッダーだけを、訪問者の IP アドレスとして使います（`AppServiceProvider`）。`https` かどうかやホスト名も、同じプロキシのヘッダーから取ります。
- **プロキシを置いたのに書かないと**、全員の IP アドレスがプロキシのものになります。ログインの試行制限が1人の失敗で全員に効く、回数の制限が全員で共有される、操作ログやエラーの通知の IP アドレスが全部同じになる、といったことが起きます。
- **プロキシを置いていないのに書いてはいけません**。このヘッダーは送る側が自由に書けるので、IP アドレスを偽って試行制限を逃れられるようになります。置いていなければ、空のままにします。
- 訪問者の側がプロキシや VPN を通してきたときは、そのプロキシの IP アドレスが残ります。本当の IP アドレスは、こちらからは分かりません。
- `.env` を変えたら `php artisan config:cache` をやり直します。
### まだ無い機能（今後の予定）

自動テスト。作ったときに章を足します。操作ログ（22章）は、記録・一覧・管理画面の回数の制限までできています。件数の多い時間帯の表示と定期の報告は、これから足します。

## 1. 全体像

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

### 1-1. 動作条件

| | 最低バージョン | 根拠の代表例 |
|---|---|---|
| PHP | 8.3 | Laravel 13 本体が PHP 8.3 以上を要求する（`laravel/framework` の composer.json が `"php": "^8.3"`）。フレームワーク自身のコードで使っている一番新しい書き方は、列挙型の定数のキーに case の値を使う `self::Gender->value => [...]`（PHP 8.2 から。`CodeType::SETTINGS`・`StaffAcl::LABELS`） |
| Laravel | 13 | 見本のサイト（memsys）の composer.json が `"laravel/framework": "^13.17"` で、この版で開発・動作確認している。フレームワークのコードで使っている機能の一番新しいものは、モデルのスコープを属性で書く `#[Scope]`（Laravel 12 から。`News::visible()`） |

- PHP の最低バージョンは、フレームワーク自身の書き方ではなく、Laravel 13 の要求で決まっています。
- Laravel 12 でも動く可能性はありますが、動作を確かめていないので対象外とします。新しい案件は、その時点の最新の Laravel で始める前提です。
- このほか、パスキー（14章）を使う場合は、ブラウザで開くアドレスが HTTPS であること（開発時の `localhost` は例外）が必要です。
- 新しい機能を足して、ここに書いたより新しい PHP・Laravel の機能を使ったときは、この表を直します。

### 1-2. 部品の一覧

| 部品 | 場所 | 役割 | 章 |
|---|---|---|---|
| `SearchableList` | `app/Support/` | 一覧・検索（検索条件の検証と保存、完全一致・部分一致の自動判定、並び順、ページと検索条件の復元） | 3 |
| `FormFlow` | `app/Support/` | 入力 → 確認 → 保存、削除（トランザクション込み）、必須マーク | 5・6 |
| `AjaxFileUpload` | `app/Support/` | 画像・添付ファイルの Ajax アップロード、WYSIWYG の画像 | 7 |
| `UploadFilePath` | `app/Support/` | アップロードしたファイルの保存先と URL の規則（公開・非公開・一時ファイル） | 7 |
| `UploadedFileController` | `app/Http/Controllers/` | ログインした人だけが見られるファイルと、一時ファイルを返す | 7 |
| `PdfDownload` | `app/Support/` | Blade のテンプレートから PDF を作って返す（mPDF、同梱の IPAex フォント） | 18 |
| `HtmlSanitizer`（`safe_html()`） | `app/Support/` | WYSIWYG の HTML の無害化 | 7 |
| `CodeTable`（`code_table()` など） | `app/Support/`・`app/helpers.php` | 区分表（列挙型・CSV・DB） | 8 |
| `CsvDownload`・`CsvColumnSet` | `app/Support/` | CSV ダウンロードと、CSV の項目の定義 | 9 |
| `CsvImport` ほか | `app/Support/` | CSV 取り込み（取り込み画面と保存の流れ） | 10 |
| `CsvReader` | `app/Support/` | CSV を読んで確かめる部分。自分の画面を持つ機能が、CSV の読み込みと検証だけを借りるときに使う | 10 |
| `MailTemplate`・`TemplatedMail` | `app/Support/`・`app/Mail/` | テンプレートファイルによるメール送信 | 11 |
| `LoginThrottle` | `app/Support/` | 認証の失敗回数による試行制限 | 14 |
| `LoginRedirect` | `app/Support/` | ログインの後の移動先（開こうとしていた画面へ戻す。会員と管理画面で入れ違わない） | 14 |
| `MemberVerificationCode` | `app/Support/` | メールで送る確認コード | 14 |
| `TrustedDeviceManager` | `app/Support/` | 2段階目を省略できる信頼済み端末 | 14 |
| `TwoFactorAuthenticator`・`BackupCodeGenerator` | `app/Support/` | 管理ログインの TOTP とバックアップコード | 14 |
| `PasskeyLogin`・`PasskeyManagement` | `app/Support/` | パスキーでのログインと、本人によるパスキーの登録・削除 | 14 |
| `PasswordChange` | `app/Support/` | パスワードを変えたときの後始末（信頼済み端末・パスキーの無効化、お知らせのメール） | 14 |
| `_confirm_hidden` | `resources/views/` | 確認画面の hidden を `$input` から組み立てる | 5 |
| `_ajax_upload_block`・`_ajax_upload_group` | `resources/views/` | アップロード欄（単数・複数） | 7 |
| `admin/csv_import/` | `resources/views/` | CSV 取り込みの画面（全コーナー共通） | 10 |
| `_passkeys` | `resources/views/` | パスキーの一覧・本人確認・登録の画面の中身 | 14 |
| `TemporaryDataCleaner` | `app/Support/` | 一時データの後片付け（一時ファイル・期限の切れたキャッシュと信頼済み端末・古い操作ログ） | 19 |
| `app:cleanup-temporary-data` | `app/Console/Commands/` | 一時データの後片付けのコマンド（スケジューラーから1時間ごと） | 19 |
| `ErrorNotifyHandler` | `app/Support/` | ログに書いたエラーを開発者にメールで知らせる（レベル・宛先・間隔は .env） | 20 |
| `SendBulkMail` | `app/Jobs/` | 一斉メールを1通送るキューのジョブ（送る速さの制限・試し直し・バッチ） | 21 |
| `OperationRecorder` | `app/Support/` | 操作ログ（誰が・いつ・どこから・何に・何をしたか）を書く | 22 |
| `AdminRequestLimit` | `app/Support/` | ログイン後の管理画面の全体に掛ける、スタッフごとの回数の制限 | 22 |
| `MemberProfileNotice` | `app/Support/` | 会員情報が変わったことを、本人へメールで知らせる | 22 |
| `SpamGuard`・`_spam_guard` | `app/Support/`・`resources/views/` | 訪問者向けフォームのスパム対策（ハニーポット・送信までの時間・Cloudflare Turnstile） | 12 |
| `app.js` | `resources/js/` | フォームの補助（必須マークから required 属性、エラー表示） | 17 |

トレイトは、コントローラーが `use` するだけで働きます。コントローラーに書くのは「このコーナーの項目の定義」と「ルートから呼ばれる入口」だけ、というのが全体の考え方です。

### 1-3. コントローラーの並び順

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
    private function saveFieldNames(...): array { ... }
    private function inputFromModel(...): array { ... }
    // defaultInput()・prepareInput()・additionalFields()・afterSave()・beforeDelete() は必要なときだけ

    // ---- 一覧・検索 ----
    public function index(...) { ... }
    private function srchRules(): array { ... }
    private function applyCustomSearch(...): bool { ... }

    // ---- CSVダウンロード ----  ---- CSV取り込み ----
    // ---- 登録 ----
    // ---- 詳細・編集・削除 ----
}
```

- 定数はクラスの冒頭にまとめ、何で使うものか（一覧・検索の設定、アップロードの設定など）でブロックに区切ります。
- 1か所からしか呼ばれない数行の処理は、細かいメソッドに分けず、コメントを1行入れて呼び元に書きます。
- トレイトの「必要なときだけ書くメソッド」は、トレイト側に何もしない版があります。コントローラーに同じ名前で書けば、そちらが使われます。

### 1-4. 全体に共通する約束

- **`$input` には送信される項目だけを入れる**：コントローラーが画面に渡す `$input` は、フォームから送信される（次の画面へ hidden で持ち越す）項目だけにします。表示だけに使う値（区分の名称、プレビューの URL など）は `$input` に混ぜず、画面の中でヘルパーを呼んで求めます（`code_label()`・`upload_preview_url()` など）。こうしておくと、確認画面の hidden を `$input` から機械的に作れます。
- **検証のルールは `rules()` に書く**：画面の必須マークは `rules()` から組み立てます。`validate([...])` にルールを直接書かず、`rules()` に切り出します（必須マークを出さない、フォームではない送信だけは例外）。
- **検証のエラーの文言は「この項目は〜」と書く**：`lang/ja/validation.php`。エラーは入力欄のすぐ下に出すので、項目の名前は入れません。項目の名前の対応表（`attributes`）も持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。ほかの項目と比べるルール（`after_or_equal:publish_start_at` など）は、相手の項目の名前が英語のまま出るので、その項目のそのルールの文言を、同じファイルの `custom` に書きます。
- **生成物の形を決める呼び出しは、名前付き引数で全部書く**：`downloadCsv()`・`CsvImportSettings`・`downloadPdf()` の引数には既定の値を持たせていないので、全部を名前付き引数で書きます。どんな結果になるかが、呼び出しの1か所で分かります。
- **定義の中の値の変換**：書式の変換は `'カラム|date:Y/m/d'` のような短い記法、外から渡す一覧による置き換えは配列、特殊な変換はクロージャではなく英字の識別名（`'@age'`）でコントローラーのメソッドを呼びます。
- **画面ごとに決まる値は、コントローラーの定数に持つ**：共通部品に画面ごとの一覧を持たせません（例：試行制限の `THROTTLE_SCOPE`）。画面が増えても共通部品を変えずに済むようにするためです。
- **データ項目の仕様はモデルの定数に持つ**：画像の横幅のように、どの画面から登録しても変わらない仕様はモデルの定数にし、コントローラーや画面はそれを参照します（例：`News::LIST_IMAGE_WIDTH`）。
- **選択肢のように決まった種類の値は列挙型にする**：`app/Enums/`。区分表として一覧を使うときは、出どころに関係なく `code_table()` 系のヘルパーを通します（8章）。
- **テンプレートは細かく部品化しない**：デザイナーが触れる HTML のまま保ちます。コーナーの入力欄は `_fields.blade.php` 1つにまとめ、4つの画面から呼びます（5章）。Blade にコントローラー名は書きません。
- **Blade のコメントは `{{-- --}}` で書く**：`<style>` や `<script>` の中でも同じです。`/* */`・`//`・`<!-- -->` は HTML のソースに出て、訪問者に見えるので使いません。
- **コメント**：今のコードが何をしていて、なぜそうなっているかだけを書きます。試行錯誤の経緯は書きません。

## 2. 新しいコーナーを作る手順

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

管理画面に「一覧・検索・登録・詳細・編集・削除」のあるコーナーを作る場合の手順です。一番近い実例（多くはニュースか会員）を写しながら進めます。

1. **テーブル**：マイグレーションを作る。業務で参照・削除するテーブルは `t_` を付ける（16章）。
2. **モデル**：`$table`・`$fillable`・`$casts`、リレーション、データ項目の仕様の定数（画像の横幅など）、表示用のアクセサ（ファイルの URL など）。
3. **コントローラー**：1-3 の並びで書く。
   - `use SearchableList;` と一覧の定数、`srchRules()`・`applyCustomSearch()`（3章）
   - `use FormFlow;` と `rules()`・`saveFieldNames()`・`inputFromModel()`（5章）
   - アップロードがあれば `use AjaxFileUpload;` と `UPLOAD_FILES`（7章）
   - 入口：`index`・`create`・`confirmStore`・`backToCreate`・`store`・`show`・`edit`・`confirmUpdate`・`backToEdit`・`update`・`destroy`
4. **ルート**：`routes/web.php` の管理画面のグループ（`auth:admin`・`auth.session` の内側）に足す。`/xxx/csv` のような固定の URL は、`/xxx/{id}` より前に書く。
5. **画面**：`resources/views/admin/<コーナー>/` に `index`・`_fields`・`create`・`edit`・`confirm`・`show`。
6. **入口のリンク**：管理画面のトップ（`admin/dashboard.blade.php`）からリンクする。
7. **権限**：管理者だけの機能なら `acl.manager`、1件ごとに判断が要るなら Policy（13章）。
8. **確かめる**：一覧の検索と並び順、詳細から「一覧へ戻る」で検索条件とページが戻ること、登録・編集の確認画面と「戻る」、必須マーク、削除。

## 3. 一覧・検索（SearchableList）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/SearchableList.php`　**実例**：`Admin\StaffController`・`Admin\MemberController`・`Admin\NewsController`・`NewsController`（訪問者向け）

### コントローラーに書くもの

```php
use SearchableList;

// 一覧画面のルート名。セッションのキーにも、登録・更新・削除の後の戻り先にも使う
private const INDEX_ROUTE = 'admin.members.index';
// フリーワード（q）検索の対象カラム（使わなければ空配列）
private const FREE_WORD_COLUMNS = ['name', 'kana'];
// 1ページの件数
private const PER_PAGE = 20;
// 並び順の選択肢（先頭が既定）。orderBy は [カラム, 方向] を書いた順に重ねる
private const ORDER_OPTIONS = [
    'updated_desc' => ['label' => '更新日が新しい順', 'orderBy' => [['updated_at', 'desc'], ['id', 'desc']]],
    'updated_asc'  => ['label' => '更新日が古い順',   'orderBy' => [['updated_at', 'asc'],  ['id', 'asc']]],
];

public function index(Request $request): View|RedirectResponse
{
    $result = $this->buildListData($request, Member::query());
    if ($result instanceof RedirectResponse) {
        return $result;   // ?back で戻ってきたとき
    }

    return view('admin.members.index', [
        'members' => $result['paginated'],
        'filters' => $result['filters'],
        'orderOptions' => $result['orderOptions'],
        'selectedOrder' => $result['orderKey'],
    ]);
}

// 検索項目の検証ルール。キーはフォームの name で、そのまま検索するカラム名になる
private function srchRules(): array
{
    return [
        'email' => ['nullable', 'string', 'max:255'],                       // 部分一致
        'prefecture' => ['nullable', 'array'],                               // 配列なら IN()
        'prefecture.*' => ['integer', Rule::in(code_keys('prefectures'))],  // 完全一致
    ];
}

// カラムと直接比べられない項目（多対多、チェックボックスなど）。処理したら true
private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
{
    return false;
}
```

### ルート

```php
Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
Route::post('/members', [AdminMemberController::class, 'storeSearchCondition'])->name('members.search');
```

`storeSearchCondition()` はトレイトにあるので、コントローラーには書きません。

### 決まりごと

- **完全一致か部分一致か**は、`srchRules()` のルールから自動で決まります。`integer`・`boolean`・`Rule::in`・`Rule::enum` のどれかがあれば完全一致、無ければ部分一致、値が配列なら `IN()`。コードやフラグに型のルールを付け忘れると部分一致になるので注意します。
- `q`（フリーワード）と `orderby`（並び順）はトレイトが使う名前なので、検索項目の名前には使えません。
- `srchRules()` に書いていない項目は捨てられます。`applyCustomSearch()` で処理する項目も、`srchRules()` には必ず書きます。
- 例：多対多の絞り込み（ニュースのカテゴリー）は `whereHas()`、「削除済みも含める」（スタッフ）は `withTrashed()` を `applyCustomSearch()` で書いています。
- 検索条件・ページ番号はセッションに保存されます。メニューから入ると（`?page` が無いと）検索条件は消えます。
- **一覧へ戻る**：詳細・編集・登録の後は `route(self::INDEX_ROUTE, ['back'])` に戻します。`?back` が付いていると、保存してあったページ番号へリダイレクトし、検索条件もそのまま戻ります。画面の「一覧へ戻る」「キャンセル」も `route('admin.xxx.index', ['back'])` にします。
- 一覧と同じ検索条件・並び順で絞り込んだクエリが欲しいとき（CSV ダウンロードなど）は `applyListConditions($query)` を使います。

### 画面（index.blade.php）

- 検索フォームは `route('admin.xxx.search')` へ POST。値は `$filters['項目名']` から出す（配列の項目は文字列の配列）。
- 並び順のプルダウンは `$orderOptions` をそのまま並べ、`$selectedOrder` を選択状態にする。
- ページ送りは `{{ $list->links('pagination::bootstrap-5') }}`。
- 削除済み（論理削除）の行は `<tr @class(['row-deleted' => $row->trashed()])>` で赤字になります（CSS はレイアウトにあります）。

ページ分けしない一覧（並び替えのためにすべて並べるカテゴリーなど）は、`SearchableList` を使わず `index()` で普通に取得します。

## 4. 詳細表示

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`Admin\MemberController::show()`・`Admin\NewsController::show()`

詳細画面も、入力欄と同じ `_fields.blade.php` を読み取り専用で表示します。値は `formInput($record)` で作ります（モデルの今の値。アップロード項目も含む）。

```php
public function show(News $news): View
{
    $news->load('categories', 'attach');

    return view('admin.news.show', [
        'news' => $news,
        'input' => $this->formInput($news),
        'categories' => $news->categories,
    ]);
}
```

```blade
@include('admin.news._fields', [
    'input' => $input, 'model' => $news, 'categories' => $categories,
    'readonly' => ' readonly', 'disabled' => ' disabled', 'required' => [],
])
```

訪問者向けの詳細（フォームの無い「モデルをそのまま見せる」画面）では `$input` を作らず、モデルとアクセサ（`$news->list_image_url` など）で表示します。

## 5. 登録・更新（FormFlow）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/FormFlow.php`　**実例**：`Admin\NewsController`（確認画面あり）・`Admin\CategoryController`（確認画面なし）

### コントローラーに書く「項目の定義」

| メソッド | 必須 | 中身 |
|---|---|---|
| `rules($record)` | ○ | 検証のルール。`$record` は新規なら null。使わなければ引数なしの `rules()` でよい |
| `saveFieldNames($validated, $record)` | ○ | 保存するカラム名の一覧。ここに書いた項目だけを保存する。外した項目は更新でもDBの値が残る |
| `inputFromModel($record)` | 詳細・編集があれば○ | モデルの今の値から `$input` を作る（日付は `Y-m-d`、フラグは `'1'`/`'0'`、多対多は id の配列など、フォームの値の形に） |
| `defaultInput()` | | 新規登録の初期値 |
| `prepareInput($validated)` | | 検証の後、確認画面と保存の前の整形（例：`safe_html()`、郵便番号の整形） |
| `additionalFields($validated, $record)` | | 入力値をそのまま使わずに保存する項目（例：ハッシュ化したパスワード、操作したスタッフの id、表示順） |
| `afterSave($record, $validated, $changedFields)` | | 保存の直後（例：多対多の `sync()`）。`$changedFields` は更新で値が変わった列で、`列の名前 => 変わる前の値`。使わなければ引数に書かなくてよい。新規登録と CSV 取り込みでは空 |
| `savedLogAction($created)` | | 操作ログ（22章）に残す操作の種類。登録・更新とは別の名前で残したいコーナーで書く（例：一斉メールの送信） |
| `beforeDelete($record)` | | 削除の直前（6章） |

アップロード項目は `saveFieldNames()` に書きません（`commitUploads()` が保存します）。多対多は `afterSave()` で保存します。

### 入口（確認画面あり）

```php
// 新規登録フォーム
public function create(): View
{
    return view('admin.news.create', [
        'input' => $this->formInput(null, old()),   // 初期値は defaultInput()、old() が優先
        'categories' => $this->allCategories(),
        'required' => $this->requiredFields(),
    ]);
}

// 確認画面
public function confirmStore(Request $request): View
{
    return view('admin.news.confirm', [
        'isCreate' => true, 'news' => null,
        'input' => $this->confirmInput($request),   // 検証（失敗なら入力画面へ戻る）＋整形
        'categories' => $this->allCategories(),
    ]);
}

// 確認画面の「戻る」
public function backToCreate(Request $request): RedirectResponse
{
    return redirect()->route('admin.news.create')->withInput($request->except('_token'));
}

// 登録の実行
public function store(Request $request): RedirectResponse
{
    $this->saveData(new News(), $request);   // 検証し直し → トランザクションで保存

    return redirect()->route(self::INDEX_ROUTE, ['back'])->with('status', 'ニュース記事を登録しました。');
}
```

編集は `edit(News $news)` で `formInput($news, old())`・`requiredFields($news)`、`confirmUpdate()` で `confirmInput($request, $news)`、`update()` で `saveData($news, $request)` です。

**確認画面なし**（項目が少なく、間違えてもすぐ直せるもの）は、`store()`・`update()` からそのまま `saveData()` を呼びます。検証に失敗すれば入力画面へ戻ります（実例：カテゴリー）。

### ルート

```php
Route::get('/news/create', ...'create')->name('news.create');
Route::post('/news/confirm', ...'confirmStore')->name('news.confirm.create');
Route::post('/news/back', ...'backToCreate')->name('news.confirm.create.back');
Route::post('/news/store', ...'store')->name('news.store');
Route::get('/news/{news}/edit', ...'edit')->name('news.edit');
Route::patch('/news/{news}/confirm', ...'confirmUpdate')->name('news.confirm.edit');
Route::post('/news/{news}/back', ...'backToEdit')->name('news.confirm.edit.back');
Route::patch('/news/{news}/update', ...'update')->name('news.update');
```

### 画面

- **`_fields.blade.php`**：入力欄一式。新規登録・編集・確認・詳細の4画面から同じものを呼びます。受け取る変数は `$input`・`$model`（新規は null）・`$readonly`（`' readonly'` か `''`）・`$disabled`（`' disabled'` か `''`）・`$required`（必須マークの配列。読み取り専用の画面では `[]`）と、選択肢など。
- **1項目の書き方**：

```blade
<div class="mb-3">
    <label for="title" class="form-label">タイトル {!! $required['title'] ?? '' !!}</label>
    <input id="title" type="text" name="title" class="form-control"
           value="{{ $input['title'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="title">{{ $errors->first('title') }}</div>
</div>
```

  エラー欄の `<div>` と `{{ }}` の間に改行や空白を入れないでください（中身が空のときだけ隠れる CSS のため）。ラジオボタン・チェックボックス・セレクトには `$disabled` を付けます。
- **確認画面**：`_fields` を読み取り専用で表示し、「戻る」「登録する」の2つのフォームに `@include('_confirm_hidden', ['input' => $input])` で hidden を入れます。項目が増えても確認画面は直さずに済みます。
- **パスワード**：確認画面の「戻る」側は `'exclude' => ['password', 'password_confirmation']` で hidden から外します。`rules()` に無い `password_confirmation` は、確認画面の `$input` に足して持ち回ります。入力画面に戻したときは、`Arr::except(old(), [...])` で再表示しません（実例：スタッフ）。
- **必須マーク**：`requiredFields($record, ['password_confirmation'])` の第2引数で、`rules()` に `required` が無いが必須にしたい項目を足せます（`accepted` の同意チェックなど）。マークの HTML は `config/form.php` の `required_mark`（管理画面と訪問者向けで別）にあります。
- 入力画面では、必須マークの付いた欄に JavaScript が `required` 属性を付け、ブラウザの入力チェックの結果も同じエラー欄に出します（17章）。

### トランザクション

`saveData()` は、本体の保存・アップロードの確定・`afterSave()` の中の書き込みまでを1つのトランザクションで行います。`afterSave()` の中で自分でトランザクションを書く必要はありません。保存の処理だけを使いたいとき（CSV 取り込みなど）は、トランザクションの中で `saveValidated($record, $validated, $input)` を呼びます。

## 6. 削除

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`Admin\NewsController::destroy()`（物理削除）・`Admin\StaffController`（論理削除と取り消し）

```php
public function destroy(News $news): RedirectResponse
{
    $this->deleteData($news);   // beforeDelete() → アップロードファイルの削除 → 本体の削除（トランザクション）

    return redirect()->route(self::INDEX_ROUTE, ['back'])->with('status', 'ニュース記事を削除しました。');
}

// 削除の直前に、関連テーブルを片付ける
private function beforeDelete(News $news): void
{
    $news->categories()->detach();
}
```

- ルートは `Route::delete('/news/{news}/delete', ...)`。一覧の削除ボタンは Bootstrap のモーダルで確認してから DELETE を送ります（実例：`admin/news/index.blade.php`）。
- アップロードファイルの実物は、トランザクションが確定した後に消えます（15章）。
- **論理削除**：モデルに `SoftDeletes` を付けると、`deleteData()` は論理削除になります。削除済みを一覧に出すのは `applyCustomSearch()` の `withTrashed()`、削除済みを表示・取り消しするルートは `->withTrashed()` を付けます。取り消しは `$record->restore()`（実例：スタッフ）。削除済みのときにできない操作は Policy で false を返します（13章）。
- 画面の削除ボタンで防いでいる条件（使用中のカテゴリーは削除できない、など）も、`destroy()` の中で必ずもう一度確かめます。

## 7. ファイルアップロード・WYSIWYG（AjaxFileUpload）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/AjaxFileUpload.php`・`UploadFilePath.php`　**実例**：`Admin\NewsController`（一覧用画像・添付ファイル・本文）、`ContactController`（添付ファイル1つ）

ファイルは選んだ時点で Ajax で一時ディレクトリ（`tmp/`）に送り、確認画面を経て保存するときに正式な保存先へ移します。コントローラー・画面の書き方は、アップロードの有無で変わらないようにしてあります。

### コントローラー

```php
use AjaxFileUpload;

// フィールド名 => 横幅(px)。0 は添付ファイル（縮小しない）、0 以外は画像（その横幅に縮小）。
// 末尾が「.*」なら複数（添付ファイルの行を増やせる）
private const UPLOAD_FILES = [
    'list_image' => News::LIST_IMAGE_WIDTH,
    'attach.*' => 0,
];
// 画像を埋め込める WYSIWYG の欄（無ければ書かない）
private const WYSIWYG_FIELDS = [
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

- `$input` の組み立て（`formInput()`・`confirmInput()`）と保存（`saveData()`）は FormFlow が自動でアップロードの処理を呼ぶので、入口の書き方は変わりません。
- ルートにアップロード先を1本足します：`Route::post('/news/ajax-upload', [AdminNewsController::class, 'uploadAjaxFile'])->name('news.ajaxUpload');`（訪問者向けは `throttle` を付ける）。
- **単数のフィールド**（`list_image`）：テーブルに `list_image`（保存ファイル名）と `list_image_origin`（元のファイル名）のカラムを作り、モデルの `$fillable` に入れます。
- **複数のフィールド**（`attach.*`）：子テーブル（`filename`・`original_name`（NULL可）・親の id）と、フィールドと同じ名前の HasMany リレーション（`News::attach()`）を用意します。
- 許可する拡張子・大きさの上限は、サイト全体の方針としてトレイトの定数にあります（コーナーごとには変えません）。
- **保存先**：`UploadFilePath` の規則で、モデルのクラス名と id から決まります（例：`news/000/000012/xxxx.jpg`）。1件分のファイルはフィールドに関係なく1つのディレクトリに入ります。表示の URL は `UploadFilePath::url(クラス, id, フィールド, ファイル名)` で作り、モデルのアクセサにしておきます（例：`News::list_image_url`、`NewsAttachment::url`）。

### 画面

```blade
{{-- 単数 --}}
@include('_ajax_upload_block', [
    'model' => $model, 'input' => $input, 'field' => 'list_image',
    'width' => \App\Models\News::LIST_IMAGE_WIDTH, 'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
])
{{-- 複数 --}}
@include('_ajax_upload_group', [
    'model' => $model, 'input' => $input, 'field' => 'attach',
    'width' => 0, 'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
])
```

- `$readonly` を渡すだけで、入力画面ではアップロードの UI、確認・詳細画面では表示だけに切り替わります。
- アップロード欄のある画面（新規登録・編集）は、`@push('head-extra')` で CSRF の `<meta>` と `resources/js/ajax_upload.js` を読み込みます。
- **WYSIWYG**：`<textarea class="wysiwyg" data-upload-url="...">` を置き、`wysiwyg_ckeditor.js` か `wysiwyg_summernote.js` を読み込みます（どちらでもサーバー側は同じ）。確認・詳細画面では `{!! safe_html($input['body']) !!}` で表示します。
- 表示名（元のファイル名）が空のときは、リンクの文字を「添付ファイル1」のようにします（`_ajax_upload_block` と訪問者向けのニュース詳細で実装済み）。

### ログインした人だけが見られるファイル（非公開）

**実例**：会員の顔写真（`Member::PRIVATE_FILE_FIELDS`、`Admin\MemberController`・`MypageController`）、お問い合わせの添付ファイル（`Inquiry::PRIVATE_FILE_FIELDS`、`ContactController`）、ニュースのファイル（`News::PRIVATE_FILE_FIELDS`、`NewsPolicy`）

個人情報のように、URL を知っているだけで誰でも見られては困るファイルは、持ち主のモデルに、非公開にするフィールドの名前を並べます。公開か非公開かはデータ項目の性質なので、コントローラーではなくモデルが決めます。同じモデルに公開と非公開のフィールドがあってもかまいません。コントローラー・画面の書き方は、公開のファイルと同じです。

```php
// モデル。複数のフィールド（attach.*）は末尾の「.*」を除いた名前、WYSIWYG欄は欄の名前
public const PRIVATE_FILE_FIELDS = ['photo'];
```

```php
// そのモデルの Policy（例：app/Policies/MemberPolicy.php）。$user はログイン中の会員かスタッフ
public function viewFiles(Member|Staff $user, Member $member, string $field): bool
{
    return $user instanceof Staff || $user->id === $member->id;
}
```

```php
// ログインしていない人にも見せることがあるなら、$user を null 可にする（例：app/Policies/NewsPolicy.php）
public function viewFiles(Member|Staff|null $user, News $news, string $field): bool
{
    return $user instanceof Staff || $news->isVisibleTo($user);   // 一般公開なら誰でも、会員限定なら会員だけ
}
```

- 非公開のフィールドのファイルは `"public"` ディスクではなく `"local"` ディスク（`storage/app/private`、Web サーバーから直接は見えない）に、公開と同じ規則のディレクトリ（`member/000/000005/` のように id を上位と下位に分けた2階層）で保存されます。
- URL は `/uploads/{種類}/{id}/{フィールド}/{ファイル名}`（ルート `uploads.show`）になります。`UploadedFileController` は、そのファイルが今そのフィールドに保存されているものかを DB で確かめ、Policy の `viewFiles()` で許されたときだけ返します。見てはいけない人には 404 を返します。`$field` で、フィールドごとに見てよい人を変えられます。
- Policy には、まずログインしていない人（`$user` が null）として聞きます。許されれば、誰にでも見せてよいファイルとして、ブラウザやプロキシに残してよい形（`Cache-Control: public, no-cache` と ETag。2回目からは変わっていなければ 304）で返します。毎回サーバーに問い合わせさせるので、記事を会員限定や非表示に変えれば、その時点から見られなくなります。
- 許されなければ、ログイン中のユーザー（会員・スタッフのどのガードでも）の誰かが許されたときだけ、ブラウザに残させない形（`Cache-Control: private, no-store`）で返します。
- 一般公開と会員限定を切り替えられるデータのように、見せてよい人が変わるものは、全部のフィールドを非公開の場所に置き、見せるかどうかを Policy でデータの今の状態から決めます。置き場所で分けると、切り替えのたびにファイルを移し、本文の画像の URL も書き換えることになるためです。
- 見せる人を絞る必要の無いフィールドは、`PRIVATE_FILE_FIELDS` に書きません（公開の `"public"` ディスクのまま）。PHP を通さずに Web サーバーが直接返すので軽く、手前に Nginx などを置けばそこで返せます。非公開は、制御が要るフィールドだけに使います。
- URL の「種類」は `AppServiceProvider` の `Relation::enforceMorphMap()` の名前です。非公開のフィールドを持つモデルは、必ずそこに載せます（載っていなければ URL を作るときに例外）。
- `UploadFilePath::url(クラス, id, フィールド, ファイル名)`・`upload_preview_url()`・モデルのアクセサは、そのまま非公開の URL を返します。PDF に埋め込む・メールに添付するときなど、サーバー上のパスが要るときは `UploadFilePath::path(クラス, id, フィールド, ファイル名)` を使います（例：`Member::photo_path`、`ContactController` の通知メール）。
- 退会などでレコードを消すときは、FormFlow の `deleteData()` か、`deleteAllUploads($record)` をトランザクションの中で呼びます（実例：`MypageController::destroy()`）。公開・非公開の両方のディレクトリが消えます。
- 運用を始めた後にフィールドを公開から非公開へ（または逆へ）変えるときは、すでにあるファイルをディスクの間で移すマイグレーションを書きます（実例：`move_inquiry_attach_files_to_private_disk`・`move_news_files_to_private_disk`。WYSIWYG 欄の本文の `<img>` の URL も書き換えます）。

### 一時ファイル（tmp）

アップロードした直後のファイルは、公開・非公開に関係なく `"local"` ディスクの `tmp/` に置き、URL は `/uploads/tmp/{ファイル名}`（ルート `uploads.tmp`）です。アップロードしたときにセッションへファイル名を覚えておき、同じセッション（アップロードしたブラウザ）にだけ返します。保存するときに、持ち主のモデルのディスク（公開なら `"public"`）へ移します。保存されずに残った一時ファイルは、24時間を過ぎるとスケジューラーが消します（19章）。

### 消えるファイル

古いファイルは、保存の前後で「そのレコードがどこからも参照しなくなったファイル」だけを、トランザクションの確定後に消します。WYSIWYG の本文から外した画像も同じです（15章）。

## 8. 区分表・選択肢

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/CodeTable.php`・`app/helpers.php`・`app/Enums/`・`code/*.csv`・`App\Enums\CodeType`・`t_codes`（`Admin\CodeController`）

区分表（値と名称の組）は、出どころに関係なく次のヘルパーで使います。

| ヘルパー | 使いどころ |
|---|---|
| `code_table('prefectures')` | 選択肢の一覧（値 => 名称）。画面のセレクト、CSV の一覧 |
| `code_keys('prefectures')` | 値の一覧。検証の `Rule::in(code_keys(...))` |
| `code_label('prefectures', $value, '（未設定）')` | 名称1つ。画面の表示、メール |

出どころは3種類で、`CodeTable` がコード名から自動で探します（2つ以上にあればエラー）。

- **列挙型**（`App\Enums\<コード名の StudlyCase>`、`CodeTableEnum` を実装し `label()` を持つ）：プログラムが値によって動きを変えるもの（スタッフの権限 `StaffAcl` など）。モデルの `$casts` や判定では列挙型を直接使います。
- **CSV**（`code/<コード名>.csv`、「値,名称」を1行1件、`#` はコメント）：選択肢として並べるだけのもの（都道府県など）。
- **DB**（`t_codes`。コード名を `App\Enums\CodeType` に載せる）：CSVと同じ扱いのもの（性別・連絡方法など）を、管理画面の「項目見出し一覧」から書き換えたいとき。値・名称の扱い（数字だけの値は int、名称の `\n` は改行）も CSV と同じです。

DBのコード表を増やすときは、`CodeType` に `case` と `label()` を足し、必要なら `CodeSeeder` に初期データを書きます。プルダウンは `code_table('code_type')` です。プログラムが特定のコード値を前提に処理するコード表は、`CodeType::isFixed()` で true を返します。そのコード表は、画面で並び替えと表示名の変更だけができ、コード値の変更・追加・削除はできません（サーバー側でも断ります）。

列挙型の値ごとの名前や設定は、`match` でメソッドの中に書かず、冒頭の定数に1行ずつまとめ、メソッドはそれを引くだけにします。キーは `case` の値を参照します（PHP 8.2から使える書き方）。

```php
case Gender = 'gender';
case Contact = 'contact';

private const SETTINGS = [
    self::Gender->value => ['label' => '性別', 'fixed' => true],
    self::Contact->value => ['label' => '連絡方法', 'fixed' => false],
];

public function label(): string
{
    return self::SETTINGS[$this->value]['label'];
}
```

Blade には `\App\Enums\...` を書かず、`code_table()` 系で書きます。画面の説明文のような文面は、列挙型に持たせず Blade に書きます。

## 9. CSV ダウンロード（CsvDownload）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/CsvDownload.php`・`CsvColumnSet.php`（項目の定義の書き方）　**実例**：`Admin\MemberController`・`Admin\NewsController`

```php
use CsvDownload;

public function csv(): StreamedResponse
{
    return $this->downloadCsv(
        query: Member::query()->with('editorStaff'),   // 使うリレーションは with() で先読み
        name: '会員一覧',                                // ファイル名「会員一覧_年月日_時分.csv」・記録の名前
        encoding: CsvEncoding::Utf8Bom,                  // Utf8Bom か Sjis
        header: true,                                    // 1行目に見出し
        escapeFormula: true,                             // Excel の数式の無害化
    );
}

// 見出し => 値の場所。書いた順に列が並ぶ
private function csvColumns(): array
{
    $prefectures = code_table('prefectures');

    return [
        '会員ID' => 'id',
        'お名前' => 'name',
        '生年月日' => 'birthdate|date:Y/m/d',              // 書式の変換（date・format・number）
        '都道府県' => ['prefecture', $prefectures],         // 一覧の表示名に置き換え
        '最終更新者' => 'editorStaff.name',                 // リレーション
        '年齢' => '@age',                                   // csvCustomColumn('age', $record)
        '更新日時' => 'updated_at|date:Y/m/d H:i:s',        // 取り込みの衝突チェックに使う
        '都道府県:*' => ['prefecture', $prefectures],       // 一覧の件数分の列に横展開（該当に1）
    ];
}
```

- 書き方の全体（複数の値の「、」区切り、連番の横展開 `'添付ファイル:*' => 'attach.*.original_name'`、組の横展開 `'添付:*' => ['attach', 'group' => [...]]` など）は `CsvColumnSet.php` の冒頭にあります。
- 一覧の検索条件・並び順で絞り込んだ全件を出します（`SearchableList` を使っていれば自動）。
- ルート：`Route::get('/members/csv', ...)->name('members.csv')`（`/members/{member}` より前）。一覧画面に「CSVダウンロード」ボタン。
- ダウンロードのたびに `t_csv_download_logs` に記録します。

## 10. CSV 取り込み（CsvImport）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/CsvImport.php` ほか（冒頭のコメント）、設計の詳細は「CSV取り込みの設計」（`docs/csv-import-spec.md`）　**実例**：`Admin\MemberController`（更新だけ）・`Admin\NewsController`（追加あり、アップロード項目あり）

項目の定義は、ダウンロードと同じ `csvColumns()` を使います。ダウンロードした CSV を直して、そのまま取り込めます。検証・保存は画面からの登録・更新（FormFlow）と同じ処理を通ります。

```php
use CsvImport;

private function csvImportSettings(): CsvImportSettings
{
    return new CsvImportSettings(
        query: Member::query(),              // id で探す範囲
        name: '会員一覧',                     // 画面の見出し・取り込み記録の名前
        labelColumn: 'お名前',                // エラー・警告の「何行目」に添える列（見出し、または何列目か）
        route: 'admin.members.csv-import',   // 取り込み画面のルート名（確認は .confirm、実行は .execute）
        mode: CsvImportMode::Save,           // Save：追加・更新／Process：保存せず処理だけ
        encoding: null,                      // null なら自動判定
        header: true,                        // false なら見出し無し（定義の順に全部の列）
        escapeFormula: true,                 // ダウンロードで付けた先頭の「'」を外す
        allowInsert: false,                  // id が空欄の行（追加）を認めるか
        maxRows: null,                       // 行数の上限
    );
}
```

```php
// ルート（/members/{member} より前）
Route::get('/members/csv-import', [AdminMemberController::class, 'csvImport'])->name('members.csv-import');
Route::post('/members/csv-import/confirm', [AdminMemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
Route::post('/members/csv-import/execute', [AdminMemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
```

- 入口の3つ（取り込み画面・確認・実行）はトレイトにあり、画面は `admin/csv_import/` の共通のものを使います。取り込みが終わると取り込み画面に戻って結果を出します。「一覧へ戻る」は `INDEX_ROUTE` から作ります。
- 取り込めるのは `rules()` にある項目だけです。リレーションをたどる列は `'import' => '項目名'` で取り込み先を書きます（例：`['categories.*.id', $categories, 'import' => 'category_ids']`）。`@名前` の列は `'import' => [...]` と `csvCustomImport()` を書いたときだけ取り込みます。
- CSV にある列だけを更新します。変更の無い行は保存しません。
- `labelColumn` を指定すると、確認画面のエラー・警告と実行を中止したときのメッセージで、「2行目（山田太郎）」のように行の見分けになる値を添えます（10文字を超えたら「...」で縮める。空欄なら添えない）。
- **処理を組み込む**：`validateCsvRows()`（行をまたいだチェック）・`afterCsvImportRow()`（1行保存するたび）・`afterCsvImport()`（確定後の通知など）・`processCsvRows()`（処理だけのモード）。
- 取り込むたびに `t_csv_import_logs` に記録します。
- 自分の画面を持つ機能が CSV の読み込みと検証だけを使うときは、`CsvReader` を `use` します。項目の定義と行の検証ルールを引数で渡し、画面と流れは自分で作ります（設計 15）。

## 11. メール送信（MailTemplate・TemplatedMail）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/MailTemplate.php`・`MailTemplateParser.php`・`app/Mail/TemplatedMail.php`・`resources/mail-templates/`　**実例**：`ContactController::sendStaffNotification()`・`MemberVerificationCode`

送信元・宛先・件名・本文は、テンプレートファイルがすべて決めます。呼び出し側は変数と添付ファイルを渡すだけです。

```text
{{-- resources/mail-templates/contact_staff.blade.php --}}
FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $staff_mail !!}
REPLY_TO: {!! $email !!}
SUBJECT: 【お問い合わせ】{!! $name !!} 様より

（空行の後が本文）
{!! $name !!} 様から…
```

```php
Mail::send(new TemplatedMail('contact_staff', [
    'from_mail' => config('mail.from.address'),
    'staff_mail' => config('contact.staff_email'),
    'name' => $inquiry->name,
    // ...
], [
    ['path' => $absolutePath, 'name' => $originalName],   // 添付ファイル（任意）
]));
```

- 見出し行は `FROM_MAIL`・`FROM_NAME`・`TO_MAIL`・`CC_MAIL`・`BCC_MAIL`・`SUBJECT`・`REPLY_TO`。宛先はカンマ区切りで複数書けます。最初の空行の後が本文です。
- 同じ名前に `_html` を付けたファイル（`contact_staff_html.blade.php`、本文だけ）を置くと、HTML とテキストのマルチパートになります。
- テキスト版は `{!! !!}`、HTML 版の利用者の入力値は `{{ }}` で埋め込みます。条件分岐や繰り返しは Blade の `@if`・`@foreach` が使えます。
- `Mail::to()` は使わず `Mail::send()` で送ります（宛先はテンプレートが持つため）。
- 保存と一緒に送るメールは、保存のトランザクションが確定した後に送り、送信に失敗しても保存は取り消さず、ログに残します（実例：お問い合わせ）。

## 12. 訪問者向けのフォーム（お問い合わせ）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`ContactController`・`resources/views/contact/`

管理画面の登録と同じく FormFlow で作ります。違いは次の4つです。

- **確認画面を経由した1回だけの送信**：確認画面を出すたびに使い捨ての合言葉（`confirm_token`）を発行してセッションと hidden に持たせ、送信のときに一致を確かめ、保存できたら消します。合言葉は `$input` に混ぜず、送信フォームにだけ埋めます。
- **連続送信の制限**：送信とアップロードのルートに `throttle:回数,分,識別名` を付けます。識別名はルートごとに重ならない名前にします（省略すると別のルートとカウンターを共有してしまいます）。
- **保存の後の処理**：保存（`saveData()`）の後に通知メールを送り、完了画面へリダイレクトします。
- **スパム対策**：下の「スパム対策（SpamGuard）」。

### スパム対策（SpamGuard）

**ファイル**：`app/Support/SpamGuard.php`（冒頭のコメント）・`resources/views/_spam_guard.blade.php`・`app/Enums/SpamCheckResult.php`　**実例**：`ContactController::confirmStore()`

人に手間をかけさせない3つの仕組みを組み合わせます。画像を選ばせる問題は出しません。

| 仕組み | 中身 | 引っかかったとき |
|---|---|---|
| ハニーポット | 人には見えない入力欄。入力があれば機械とみなす | `Bot` |
| 送信までの時間 | 入力画面を表示した時刻を暗号化して hidden に持たせ、送信までが短すぎれば機械とみなす | `Bot` |
| Cloudflare Turnstile | 入力画面の枠がブラウザの裏側で判定し、送られてきたトークンをサーバーから Cloudflare に問い合わせる | `Failed` |

```blade
{{-- 入力画面の <form> の中 --}}
@include('_spam_guard')
```

```php
// コントローラー。入力画面から送信を受け取るところ（確認画面を表示する処理）で呼ぶ
private const SPAM_GUARD_MIN_SECONDS = 3;   // 表示から送信までの、いちばん短い秒数

$spam = SpamGuard::check($request, minSeconds: self::SPAM_GUARD_MIN_SECONDS);
if ($spam === SpamCheckResult::Bot) {
    return redirect()->route('contact.thanks');   // 送れたように見せて、何も保存しない
}
if ($spam === SpamCheckResult::Failed) {
    return redirect()->route('contact.create')->withInput(...)->with('error', '...');   // 入力を残して戻す
}
```

- **確かめるのは入力画面から進むときの1回だけ**：Turnstile のトークンは1回しか使えず、発行から5分で切れます。確認画面から先は、確認画面を通った人にだけ発行する `confirm_token` で守ります。トークンは、期限が近づくと枠が裏で自動的に取り直す（`refresh-expired: 'auto'`）ので、入力に時間がかかっても切れません。
- **判定が終わるまで送信ボタンを押せなくする**：枠が出て判定が終わるまで、数秒かかることがあります。その間は `_spam_guard` が案内の文を出し、フォームの送信ボタンを押せなくします（Bootstrap の `disabled` クラス。`disabled` 属性は、同意のチェックのようにフォームの側が使うので触りません）。10秒たっても終わらないときと、Cloudflare のスクリプトを読めないとき（広告を止める拡張機能など）は、案内を「確認できませんでした」に変えます。案内の文は `_spam_guard.blade.php` の中の HTML です。使う側の画面は `@include` だけで、何も足しません。
- **Cloudflare に障害があるときは通す**：問い合わせできない・時間切れ（5秒）・Cloudflare の側のエラーのときは `Passed` にしてログに残します。鍵が設定されていない・間違っているときも、送信は止めずにログに残します。
- **戻すときは、スパム対策の値を `withInput()` から外す**：古いトークンや時刻を持ち越さないためです（入力画面を表示し直すと、新しい値になります）。
- **鍵**：`.env` の `TURNSTILE_SITE_KEY`・`TURNSTILE_SECRET_KEY`（`config/services.php`）。本番は Cloudflare のダッシュボードで、サイトのドメインごとに発行します。手元の開発では、Cloudflare が公開しているテスト用の鍵（必ず通る）を使います（`.env.example` に書いてあります）。サイト自体を Cloudflare に載せる必要はありません。
- **回数の制限**：確認画面へ進むたびに Cloudflare に問い合わせるので、そのルートにも `throttle` を付けます（`throttle:20,1,contact-confirm`）。
- **プライバシーポリシー**：Turnstile は、利用者のブラウザの情報を Cloudflare に送ります。外部送信規律（電気通信事業法）に合わせて、外部のサービスに情報を送っていることを、プライバシーポリシーなどで示しておきます。

レイアウトは訪問者向けの `layouts/app.blade.php` を使います。必須マークは `config/form.php` の `public` が使われます。

## 13. 権限

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Policies/StaffPolicy.php`・`app/Http/Middleware/EnsureStaffIsManager.php`　**実例**：スタッフ

- **管理者だけの機能**（一覧・新規登録など、特定の1件に対する操作ではないもの）：ルートを `Route::middleware('acl.manager')` のグループに入れます。
- **1件ごとの判断**（本人か管理者なら編集できる、自分自身は削除できない、削除済みは編集できない、など）：`app/Policies/<モデル名>Policy.php` にメソッドを書くと、名前の対応で自動的に使われます。同じ判断を3か所から使います。
  - ルート：`->middleware('can:update,staff')`（false なら 403）
  - 画面：`@can('update', $staff)`（ボタンやリンクの出し分け）
  - コントローラー：`Auth::guard('admin')->user()->can('updateAcl', $staff ?? Staff::class)`
- 権限によって入力欄を出さない項目は、`rules()` で `'exclude'` にし、`saveFieldNames()` からも外します（外さないと NULL で上書きしてしまう。実例：スタッフの権限）。
- 管理者かどうかの判定は `Staff::isManager()` です。

## 14. ログイン認証

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：会員は `AuthSessionController`・`LoginVerificationController`・`PasswordResetController`・`AuthPasswordController`・`AuthRegisteredMemberController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\TwoFactorChallengeController`

認証の画面は遷移が独特なので、FormFlow には載せず、それぞれのコントローラーで書いています。新しく作るより、実例を写して直す方が安全です。

### ガードと画面の区分

| | 会員 | 管理画面 |
|---|---|---|
| ガード | `web`（`Member`、`t_members`） | `admin`（`Staff`、`t_staffs`） |
| ログインが要る画面 | `Route::middleware(['auth', 'auth.session'])` | `Route::middleware(['auth:admin', 'auth.session'])` |
| ログイン前だけの画面 | `guest` | `guest:admin` |
| 2段階目 | メールの確認コード（`MemberVerificationCode`） | TOTP（`TwoFactorAuthenticator`）＋バックアップコード |
| 2段階目の省略 | 「このデバイスを記憶する」（`TrustedDeviceManager::forMember()`） | 「この端末を信頼する」（`TrustedDeviceManager::forStaff()`） |

未ログインのときの行き先・ログイン済みでゲスト専用画面に来たときの行き先は、`bootstrap/app.php` でルート名（`admin.*` かどうか）から振り分けています。

### ログインの流れ（会員・管理とも同じ形）

1. ID・パスワードを `Auth::guard(...)->validate()` で確かめる（まだログインはしない）。
2. 信頼済み端末なら、そのまま `Auth::login()`。
3. そうでなければ「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ。2段階目の画面は `guest` にも `auth` にも入れず、コントローラー自身がセッションで守る。
4. 2段階目が通ったら `Auth::login()` と `session()->regenerate()`。
5. ログインが必要な画面から来た場合はその画面へ、そうでなければ既定の画面（マイページ・管理画面TOP）へ移す。移動先は `LoginRedirect::forMember()`・`forStaff()` で決める（2・4とパスキーで通ったとき）。

開こうとしていた画面の記録（セッションの `url.intended`）は、会員と管理画面で1つしか無いので、`redirect()->intended()` を直接使わず `LoginRedirect` を通します。記録された URL がログインした側の画面（管理画面なら `admin.*` のルート）のときだけ戻り先にし、そうでなければ既定の画面へ移して、記録はもう一方の側のために残します。

### 試行制限（LoginThrottle）

```php
// コントローラーの定数。値はほかのコントローラーと重ならない名前にする
private const THROTTLE_SCOPE = 'member-login';

$throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['email']);
if ($throttle->isBlocked()) {
    throw ValidationException::withMessages(['email' => $throttle->blockedMessage('ログイン')]);
}
// 失敗したら $throttle->hit()、成功したら $throttle->clear()
```

失敗した回数だけを、IP 単位とアカウント単位の2本で数えます。使われている scope は、コントローラーを `THROTTLE_SCOPE` で検索すると一覧できます。メール送信を伴うルート（確認コードの再送など）には、ルートの `throttle` を付けます。

### 確認コード（MemberVerificationCode）

用途（`PURPOSE_LOGIN`・`PURPOSE_PASSWORD_RESET`・`PURPOSE_MYPAGE_PASSWORD`・`PURPOSE_REGISTER`・`PURPOSE_PASSKEY`）ごとにセッションで管理します。`issue()` で発行とメール送信、`verify()` で照合します。会員登録のようにまだ会員がいない場合は `issueForAddress()`・`verifyForAddress()` です。

### パスワードを変えるとき

- `auth.session` が、パスワードを変えたときにほかの端末のログインと「ログイン状態を保持する」の Cookie を無効にします。
- パスワードの更新は、必ず「ログイン中のユーザーのインスタンス」に対して行います（別のインスタンスを更新すると本人までログアウトされます）。別のインスタンスを保存した場合は `Auth::guard(...)->setUser($record)` で差し替えます（実例：`StaffController::afterSave()`）。
- パスワードを変えたら、保存の直後に `PasswordChange::resetAndNotify($record, changedBy: 管理画面から変えたスタッフ)` を呼びます（トランザクションの中でよい）。信頼済み端末とパスキーをすべて無効にし、確定後に登録されているメールアドレスへお知らせを送ります（テンプレートは `member_password_changed`・`staff_password_changed`）。スタッフの2段階認証（TOTP）は消しません。戻り値は削除したパスキーの件数で、画面のメッセージに使えます。

### 認証まわりのテーブル

信頼済み端末（`trusted_devices`）・バックアップコード（`two_factor_backup_codes`）・パスキー（`passkeys`）のようなフレームワーク内部のテーブルは、会員・スタッフで共通の1つにし、`authenticatable_type`（`'member'`・`'staff'`）と `authenticatable_id` で区別します。新しい認証対象のモデルを足したら、`AppServiceProvider` の `Relation::enforceMorphMap()` にも足します。

### パスキー（PasskeyLogin・PasskeyManagement）

**ファイル**：`app/Support/PasskeyLogin.php`・`PasskeyManagement.php`・`PasskeyCeremony.php`・`HasPasskeys.php`・`UserAgentLabel.php`、`app/Models/Passkey.php`、`resources/views/_passkeys.blade.php`、`resources/js/passkeys.js`、設計の詳細は「パスキーの設計」（`docs/passkeys-spec.md`）　**実例**：会員は `AuthSessionController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\StaffController`

ログイン画面の「パスキーでログイン」で通ったときは、パスワードも2段階目も求めずにログインさせます。登録の前の本人確認は、その人のログインの2段階目と同じ方法（会員：メールの確認コード、スタッフ：TOTP）で、トレイトが持ち主の種類から決めます。

パッケージ（composer の `laravel/passkeys`、npm の `@laravel/passkeys`）・`passkeys` テーブル・モデルの `implements PasskeyUser` と `use HasPasskeys` は、標準構成として常に入れておきます。使うかどうかは、次のコントローラーの `use` とルートで切り替えます。画面の入口（ログイン画面のボタン、マイページ・スタッフ詳細のリンク）は `Route::has()` で出し分けているので、ルートを消せば消えます。

```php
// ログインのコントローラー
use PasskeyLogin;
private const PASSKEY_GUARD = 'web';                  // ログインさせるガード
private function passkeyRedirectUrl(): string         // ログイン後の移動先
{
    return redirect()->intended(route('mypage'))->getTargetUrl();
}

// 本人のパスキーを扱うコントローラー
use PasskeyManagement;
private const PASSKEY_GUARD = 'web';                           // ログイン中の本人を取るガード
private const PASSKEY_ROUTE = 'mypage.passkeys';               // 一覧画面のルート名
private const PASSKEY_VIEW = 'mypage.passkeys';                // 一覧画面のビュー
private const PASSKEY_THROTTLE_SCOPE = 'member-passkey-code';  // 本人確認のコードの試行制限
```

| トレイト | アクション | ルート名 |
|---|---|---|
| `PasskeyLogin` | `passkeyLoginOptions()`（GET）・`passkeyLogin()`（POST） | `login.passkey.options`・`login.passkey`（管理は `admin.` を付ける。ゲスト用のグループに入れ、`throttle` を付ける） |
| `PasskeyManagement` | `passkeyIndex()`（GET）・`passkeySendCode()`（POST、会員だけ）・`passkeyConfirm()`（POST）・`passkeyRegistrationOptions()`（GET）・`passkeyStore()`（POST）・`passkeyDestroy()`（DELETE） | `PASSKEY_ROUTE` と、その後ろに `.code`・`.confirm`・`.options`・`.store`・`.destroy` |

- 一覧画面のビューは、`@push('head-extra')` で CSRF の `<meta>` と `resources/js/passkeys.js` を読み込み、`@include('_passkeys')` するだけにします。
- スタッフの `PASSKEY_THROTTLE_SCOPE` は `TwoFactorChallengeController::THROTTLE_SCOPE` にします（TOTP の失敗回数を、ログインの2段階目などと同じカウンターで数える）。
- パスキーは `APP_URL` のドメインに結び付きます。`APP_URL` をブラウザで開いているアドレスと完全に同じにし、HTTPS（開発時の `localhost` は例外）で使います。
- 退会と2段階認証の登録解除では、`$record->passkeys()->delete()` でパスキーも消します。パスワードを変えたときは `PasswordChange` が消します。

## 15. トランザクションとファイルの削除

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- DB への書き込みが2つ以上続く処理は `DB::transaction()` で囲みます。FormFlow の `saveData()`・`deleteData()`、CSV 取り込みの実行は、中で囲んでいます。
- ファイルの実物の削除は `DB::afterCommit()` に渡し、トランザクションが確定してから行います（取り消されたときに、元に戻った DB が参照しているファイルを消さないため）。トランザクションの外で呼ばれたときは、その場で消えます。
- メールの送信も、保存のトランザクションが確定した後に行います。
- 検証（`validate()`）はトランザクションの外で行います。

## 16. テーブル・モデルの決まり

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- **テーブル名**：業務のデータ、業務の側で参照したり古い行を消したりする記録（問い合わせ、CSV のダウンロード・取り込みの記録など）は `t_` を付けます。認証の保持や試行制限のようなフレームワーク内部のものは `t_` を付けず、会員・スタッフ共通の汎用のテーブルにします。
- DB の外部キー制約（`constrained()`）は付けていません。関連の片付けは `beforeDelete()` などでコードで行います。
- **モデル**：データ項目の仕様（画像の横幅など）は定数、表示用の値（ファイルの URL など）はアクセサにします。日付は `date`、フラグは `boolean`、列挙型は列挙型のクラスで `$casts` に書きます。
- 論理削除が要るものは `SoftDeletes`（`deleted_at`）を使います。

## 17. 画面（Blade）の決まり

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- **レイアウト**：管理画面は `layouts/admin.blade.php`、訪問者向けは `layouts/app.blade.php`。処理結果のメッセージは `->with('status', '...')`（緑）・`->with('error', '...')`（赤）で渡すと、レイアウトが表示します。
- **画面ごとのスクリプト**：その画面だけで使うもの（アップロード、WYSIWYG、並び替えなど）は `@push('head-extra')` で読み込みます。全画面で使うものは `resources/js/app.js` に書きます。
- **フォームの補助（app.js）**：必須マーク（`.required-mark`）の付いたラベルの `for` の欄に `required` 属性を付ける、エラー欄（`.invalid-feedback[data-item]`）に文言があれば同じ名前の欄を赤くする、ブラウザの入力チェックの結果を同じエラー欄に出す、入力し直したらエラー表示を消す。画面ごとの設定は要りません。ラベルの `for` と入力欄の `id` を必ず対応させてください。
- **読み取り専用の表示**：確認・詳細画面は `$readonly = ' readonly'`・`$disabled = ' disabled'` で同じ `_fields` を表示します。見た目はレイアウトの CSS で整えています。
- **一覧**：削除済みの行は `row-deleted`、ページ送りは `pagination::bootstrap-5`。
- **戻り先**：一覧へ戻るリンクは `route('admin.xxx.index', ['back'])`。
- **共通の部分ビュー**（`_confirm_hidden`・`_ajax_upload_block` など）は `resources/views/` 直下に置き、モデル名やコントローラー名を書きません。コーナー専用のテンプレート（`admin/news/_fields` など）は、そのコーナーのモデルの定数を参照してかまいません。

## 18. PDF 出力（PdfDownload）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/PdfDownload.php`（冒頭のコメント）・`resources/fonts/ipaex/`（IPAex フォントとライセンス）・`resources/views/pdf/`　**実例**：履歴書（`Admin\MemberController::resume()`・`MypageController::resume()`・`resources/views/pdf/resume.blade.php`）

PDF の見た目は、普通の画面と同じく Blade のテンプレート（HTML と CSS）で書き、mPDF（composer の `mpdf/mpdf`）で PDF にします。

### コントローラーに書くもの

```php
use PdfDownload;

public function resume(Member $member): Response
{
    return $this->downloadPdf(
        view: 'pdf.resume',                       // resources/views/pdf/resume.blade.php
        data: ['member' => $member],              // テンプレートに渡す変数
        name: '履歴書_'.$member->name,            // ファイル名「名前_年月日_時分.pdf」
        images: ['photo' => ['path' => $member->photo_path, 'aspect' => Member::PHOTO_ASPECT]],
        paper: 'A4',                              // 用紙（mPDF の format）
        orientation: 'P',                         // P：縦、L：横
        inline: true,                             // true：ブラウザの中で開く、false：ダウンロード
    );
}
```

- `images` の `aspect`（`[横, 縦]`）を書くと、画像の真ん中をその比で切り抜いてから埋め込みます。mPDF は CSS の `object-fit` に対応していないので、決まった大きさの枠に写真をゆがめずに収めるためです。`path` が null か、ファイルが無いときは埋め込みません。
- ルート：`Route::get('/members/{member}/resume', ...)->name('members.resume')`。画面のボタンは `target="_blank"` で開きます。
- 返す PDF には、ブラウザやプロキシに残させないヘッダー（`Cache-Control: private, no-store`）を付けています。

### テンプレート

- 埋め込める画像は `$images`（名前 => `<img>` の src に書く値）に入っています。`@if (isset($images['photo'])) <img src="{{ $images['photo'] }}" style="width: 30mm; height: 40mm;"> @endif` のように書きます。画像はファイルのパスや URL ではなく mPDF の「`var:名前`」で渡すので、非公開のファイルも埋め込めます。
- フォントは、同梱の IPAex ゴシック（`ipaexg`、既定）と IPAex 明朝（`ipaexm`）を `font-family` で指定します。使った文字だけが PDF に埋め込まれるので、どの環境でも同じ見た目になります。
- 余白は `@page { margin: ... }`、大きさは mm で書きます。
- mPDF が解釈できる CSS はブラウザより少なく、flex や grid は使えません。枠や罫線は `<table>` で組みます。

### 決まりごと

- mPDF の作業用のディレクトリは `storage/framework/mpdf`（Git の対象外）です。最初の1回はフォントを解析するので、少し時間がかかります。
- フォントを足すときは、`resources/fonts/` にファイルとライセンスを置き、`PdfDownload` の `PDF_FONTS` に足します。

## 19. スケジューラー（定期的な処理）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`routes/console.php`（スケジュールの一覧）・`app/Console/Commands/`（コマンド）　**実例**：一時データの後片付け（`app/Support/TemporaryDataCleaner.php`・`app/Console/Commands/CleanupTemporaryData.php`）

「決まった時刻に何かを実行する」処理は、Artisan のコマンドとして作り、`routes/console.php` でいつ動かすかを決めます。表示するたびに今の時刻と比べれば済むもの（ニュースの掲載期間など）は、スケジューラーを使いません。

### コマンドとスケジュール

```php
// app/Console/Commands/CleanupTemporaryData.php（php artisan make:command で作る）
#[Signature('app:cleanup-temporary-data')]
#[Description('一時ファイル・期限の切れたキャッシュなどの一時データを消す')]
class CleanupTemporaryData extends Command
{
    public function handle(): int
    {
        $counts = TemporaryDataCleaner::all();   // 中身は app/Support に置き、コマンドは呼ぶだけ
        // 画面とログに件数を出す
        return self::SUCCESS;
    }
}
```

```php
// routes/console.php
Schedule::command(CleanupTemporaryData::class)->hourly()->withoutOverlapping();
```

- 処理の中身はコマンドに書かず、`app/Support/` のクラスに置きます。画面から呼ぶ処理（アップロードのついでの片付けなど）と同じものを使えるようにするためです。
- `withoutOverlapping()` で、前の回が終わっていなければ重ねて動かしません。
- 登録した一覧と次に動く時刻は `php artisan schedule:list`、すぐに動かすときは `php artisan app:cleanup-temporary-data` のようにコマンドを直接実行します。
- 何かを消したときだけログに残し、何もしなかった回は残しません（同じ行が1時間ごとに並ばないように）。

### 一時データの後片付け（TemporaryDataCleaner）

| 一時データ | 置き場所 | 消すもの |
|---|---|---|
| アップロード直後の一時ファイル | `storage/app/private/tmp` | 24時間（`MAX_AGE_HOURS`）より古いファイル |
| CSV 取り込みの作業用ファイル | `storage/app/private/csv_import` | 24時間より古いファイル |
| 試行制限の回数などのキャッシュ | `cache`・`cache_locks` テーブル | 期限の切れた行（キャッシュを database に置いているときだけ） |
| 「このデバイスを記憶する」の記録 | `trusted_devices` テーブル | 期限（`expires_at`）の切れた行 |
| 操作ログ（22章） | `t_operation_logs` テーブル | `OPERATION_LOG_DAYS`（既定は365日）より古い行 |

- 一時ファイルは、アップロードと CSV 取り込みのたびにも同じ処理で消します。サーバーの cron が動いていなくても溜まり続けないようにするための控えです。
- 新しい一時データ（使い終わっても残るファイルや行）を作ったら、このクラスにメソッドを足し、`all()` に加えます。

### サーバーの設定（cron）

スケジューラーは、サーバーの cron で毎分 `php artisan schedule:run` を動かしたときに働きます。cron が無いと、`routes/console.php` に書いた処理も、キューのワーカー（21章）も何も動きません。

サイトごとに `/etc/cron.d/` へファイルを1つ置きます。例は、開発用のサイトの `/etc/cron.d/memsys_dev` です。

```cron
* * * * * apache cd /var/www/memsys_dev && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

- `/etc/cron.d/` のファイルは、時刻の後に実行するユーザーを書きます。**PHP-FPM のそのサイトのプールと同じユーザー**にします（プールの設定の `user`）。root で動かすと、ログ（`storage/logs`）などのファイルが root の持ち物になり、画面からの処理が書き込めなくなります。apache のようにログインできないユーザーでも動きます。
- cron から動かすコマンドも、`bootstrap/cache/config.php` から設定を読みます。`schedule:run` が SQLite のエラーで止まるときは、`config:cache` をやり直します（0章「サーバーへ送った後に実行するコマンド」）。
- cron の PATH は最小限なので、`php` は絶対パスで書きます。場所は `which php` で確かめます。サイトのパスもサーバーに合わせます。
- ファイルは root の持ち物で 644 にし、最後の行の後ろに改行を入れます（無いと最後の行が読まれないことがある）。ファイル名に `.` を入れると読み飛ばされます。
- 開発用と本番が同じサーバーにあるときは、`memsys_dev`・`memsys` のようにファイルを分け、パスとユーザーをそれぞれに合わせます。
- 動いているかは、1〜2分待ってから `php artisan schedule:list` で次に動く時刻を見るか、`storage/logs` に一時データの片付けのログが出るかで確かめます。

## 20. エラーの通知（ErrorNotifyHandler）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/ErrorNotifyHandler.php`・`config/logging.php`（`error_notify` のチャンネル）・`resources/mail-templates/error_notify.blade.php`

本番で起きたエラーを、ログを見に行かなくても気付けるよう、ログに書くのと一緒に開発者へメールで知らせます。ログのチャンネルとして働くので、例外も `Log::error()` も同じ仕組みで届き、コントローラーや部品のコードに通知のための処理は書きません。

### .env の設定

```
ERROR_NOTIFY_LEVEL=error
ERROR_NOTIFY_TO=dev1@example.com,dev2@example.com
ERROR_NOTIFY_INTERVAL=10
```

| 項目 | 意味 |
|---|---|
| `ERROR_NOTIFY_LEVEL` | 知らせる最低のレベル。空なら知らせない。手元の開発は空にする |
| `ERROR_NOTIFY_TO` | 宛先。カンマで区切って複数書ける |
| `ERROR_NOTIFY_INTERVAL` | 同じ内容のものを送る間隔（分）。間隔の中の2回目からは送らずに数え、次の通知に件数を添える。0なら間引かない |

開発環境かどうかで切り替えるのではなく、`ERROR_NOTIFY_LEVEL` だけで切り替えます。調べものの間だけ `warning` に下げる、という使い方もできます。

### レベルの使い分け

| レベル | 意味 | 例 |
|---|---|---|
| `critical` | 処理が止まった障害 | 処理されなかった例外（プログラムの不具合、DB が落ちたなど）。`bootstrap/app.php` で例外を critical で記録している |
| `error` | 処理は続いたが、運用に支障が出る | メールが送れなかった、Turnstile の鍵の設定が誤っている、アップロードの保存に失敗した |
| `warning` | 動いてはいるが、気になる | Cloudflare が応答せず判定を素通りした、hidden の書き換えらしき送信 |
| `info` | 記録だけ | スパムの検出、一時データの片付けの件数 |

- 新しい処理でログを書くときは、この表に合わせてレベルを選びます。知らせてほしい失敗は `error`、知らせなくてよいものは `warning` 以下にします。
- 404・入力エラー・ページの有効期限切れなど、利用者の操作で普通に起きるものは、Laravel が例外として報告しないので届きません。

### 決まりごと

- **件名**は `【要確認：サイト名】レベル：内容` です。頭の `【要確認：サイト名】` は全部の通知で同じなので、受け取る人がメールの振り分けに使えます。レベルは、一覧で急ぎかどうかを見分けるために載せています。内容は、例外なら種類、それ以外なら文言の頭です。文言の頭の「クラス名: 」は、件名には出しません（本文の「内容」には残ります）。
- メールには、日時・内容・起きたところ（画面ならメソッドと URL と IP とログイン中の人の id、コマンドならそのコマンド）・例外の種類と場所・スタックトレースの先頭・`Log::error()` に添えた情報を載せます。入力値と URL の問い合わせの部分は載せません。
- 同じ内容かどうかは、例外なら種類と原因の場所、それ以外ならレベルと文言で見分けます。原因の場所は、スタックトレースの中で最初に出てくる自分たちのコードの行です（`vendor/` の下と、入口の `public/index.php`・`artisan` は除く）。SQL のエラーは、どの画面で起きてもフレームワークの同じ行から投げられるので、投げられた行ではなく原因の行で見分けます。メールの「例外」の欄にも、この行を載せます。間引きの記録はファイルのキャッシュに置くので、DB が落ちていても間引けます。
- SMTP が落ちているなどで通知が送れないときは、そのことをログに残すだけにします。通知が送れないこと自体はメールでは分からないので、サーバーのログも時々見ます。
- `LOG_CHANNEL` が `stack` のときに働きます（`config/logging.php` の `stack` に、`ERROR_NOTIFY_LEVEL` があれば `error_notify` が加わります）。

### ログのファイル

ログは `storage/logs` に書きます。ファイルの分け方は `.env` の `LOG_STACK` で決めます。

| `LOG_STACK` | ファイル | 古いログ | 使うところ |
|---|---|---|---|
| `single` | `laravel.log` の1つ | 消さない。書き足し続ける | 手元の開発 |
| `daily` | `laravel-2026-10-04.log` のように1日1ファイル | `LOG_DAILY_DAYS` の日数を過ぎたものを自動で消す | サーバー |

- サーバーは `LOG_STACK=daily`・`LOG_DAILY_DAYS=90` にします。`single` のままだと、ファイルが大きくなり続けます。
- 残す日数は、障害に気付いてから見に行くまでの間を考えて、長めにします。週ごとやサイズでの区切りはありません。区切れるのは1日ごとなので、後から見られるかどうかは日数で決まります。
- `.env` を変えたら `php artisan config:cache` をやり直します（0章）。
- `daily` のときに今日のログを見るには、`tail -n 80 storage/logs/laravel-$(date +%F).log` とします。
- エラーの通知の間引きは、メールだけのものです。ログには、起きたエラーが1件ずつ全部残ります。

## 21. キュー（一斉メール）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Jobs/`（ジョブ）・`routes/console.php`（ワーカーのスケジュール）　**実例**：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`）

画面の中で終わらない重い処理や、外のサーバーへの送信をたくさん行う処理は、キューに積んで裏で動かします。キューは DB（`QUEUE_CONNECTION=database`）を使い、積んだジョブは `jobs` テーブルに入って、終われば消えます。

### ワーカー

```php
// routes/console.php。毎分動かし、積まれているものが無くなったら止まる
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50'])->everyMinute()->withoutOverlapping();
```

- スケジューラー（19章）の cron のままで動き、Supervisor などの常駐の仕組みは要りません。積んでから送り始めるまでに、最大1分ほど掛かります。
- 手元で試すときは `php artisan queue:work --stop-when-empty` を直接実行します。

### ジョブの書き方

```php
class SendBulkMail implements ShouldQueue
{
    use Batchable, Queueable;

    public array $backoff = [60, 300];   // 例外の後に試し直すまでの秒数
    public int $maxExceptions = 3;       // 例外がこの回数を超えたら失敗にする

    public function retryUntil(): DateTimeInterface { return now()->addDay(); }   // 速さの制限で戻されても、この期限までは試す
    public function middleware(): array { return [new RateLimited('bulk-mail')]; }  // 送る速さの制限
}
```

- **送る速さ**：`AppServiceProvider` の `RateLimiter::for('bulk-mail', ...)` で1分の上限を決め、ジョブの `RateLimited` で守ります。上限は `.env` の `MAIL_BULK_PER_MINUTE` で、SMTP の送信の上限を超えない値にします。見本の値は5です。さくらのレンタルサーバ（ライト・スタンダード・プレミアム）は15分に100通ほどが目安（[メール送信数の制限について](https://help.sakura.ad.jp/rs/2251/)）なので、1分に5通（15分に75通）にして、お問い合わせの通知などほかのメールの分を残しています。1,000通を送るのに3時間20分ほどかかります。
- **失敗**：試し直しても送れなかったジョブは `failed_jobs` に残り、エラーの通知（20章）にも届きます。`php artisan queue:retry all` で送り直せます。
- **バッチ**：たくさんのジョブを `Bus::batch()` で1つにまとめると、進み具合（済み・失敗・残り）を `job_batches` から読めます。`finally()` で、全部が終わったときの処理を書けます。
- **トランザクション**：DB に書いた内容をジョブが読むときは、確定してから積みます。

### 一斉メール

- **文面の管理**：タイトル（管理用）・件名・本文を登録しておきます。確認画面を挟まない型（カテゴリーと同じ）です。
- **送信**：入力（文面を選ぶと欄に入る。添付ファイルは1つ）→ 確認 → 送信 → 状況の画面。宛先は「氏名,メールアドレス」の見出し無しの CSV で、`CsvReader`（10章）で読み込みと検証だけを借ります。
- **氏名の差し込み**：件名と本文の `{{$name}}` を宛先の氏名に置き換えます。Blade では展開せず、文字列を置き換えるだけです（画面から入力された文を Blade で展開すると、サーバーで何でも実行できてしまうため）。ほかの `{{…}}` は入力エラーにします。件名と CSV の氏名は、メールの見出しを崩さないよう改行を許しません。
- **宛先は保存しない**：送るたびに送信の記録（`t_bulk_mails`）を1件作りますが、持つのは件名・本文・添付ファイル・件数だけです。宛先はジョブの中にだけあり、送り終えれば消えます。送り終えたら送れた件数と失敗した件数を記録に写します。
- **二重の送信**：送信中の一斉メールがあれば、入力画面を開いても状況の画面に回します。送信の実行はロックの中で確かめるので、2つの画面から同時に押しても1つしか送りません。
- 使えるのは管理者だけです。

## 22. 操作ログ（OperationRecorder）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/OperationRecorder.php`（冒頭のコメント）・`app/Models/OperationLog.php`・`app/Enums/OperationLogAction.php`・`app/Enums/OperationLogSubject.php`　**実例**：`Admin\OperationLogController`（一覧）・`Admin\MemberController::show()`（詳細の閲覧）

誰が・いつ・どこから・何に・何をしたかを、`t_operation_logs` に1行ずつ残します。情報が漏れたときや、「変えていないのに変わっている」という問い合わせがあったときに、後からたどれるようにするためです。

### 残すもの・残さないもの

| 列 | 中身 |
|---|---|
| `operator_type`・`operator_id` | 操作した人の種類（`staff`・`member`）と id。誰もログインしていない訪問者の操作は空で、画面では「訪問者」と出す |
| `action` | 操作の種類（`OperationLogAction`） |
| `target_type`・`target_id` | 対象の種類と id。CSV のダウンロードのように、1件に決まらないときは空 |
| `changed_fields` | 更新のときだけ。値が変わった列の名前の一覧 |
| `detail` | 種類ごとの補足。CSV の名前、ログインに失敗したログイン ID など |
| `ip`・`device` | IP アドレスと、端末の種類（`UserAgentLabel`。例：iPhone・Safari） |

- **氏名などの個人情報と、変更の前後の値は残しません**：操作ログが個人情報の写しにならないようにするためです。前の値までスタッフが知っていることは、会員の不信感にもつながります。画面では、id から今の氏名を引いて出します。
- **種類の名前**は、`AppServiceProvider` の `Relation::enforceMorphMap()` に書いた名前です。操作ログに残すモデルを増やしたら、`enforceMorphMap()` と `OperationLogSubject` の両方に足します。

### 記録するところ

| 操作 | 記録するところ | コーナーに書くもの |
|---|---|---|
| 登録・更新・削除 | `FormFlow` の `saveData()`・`deleteData()` | 無い（別の名前で残すときだけ `savedLogAction()`） |
| ログイン・ログアウト | `AppServiceProvider`（Laravel のログイン・ログアウトのイベント） | 無い |
| ログインの失敗 | ログインのコントローラー（試行制限の回数を足すところ） | 無い |
| パスワードの変更 | `PasswordChange::resetAndNotify()` | 無い |
| パスキーの登録・削除 | `PasskeyManagement` | 無い |
| CSV のダウンロード・取り込み | `CsvDownload`・`CsvImport` | 無い |
| 詳細の閲覧 | 個人情報を持つコーナーの `show()` | 1行書く |
| PDF の出力 | PDF を出す入口 | 1行書く |

```php
// 詳細の閲覧。個人情報を持つコーナーの show() の先頭に書く
OperationRecorder::record(OperationLogAction::View, $member);

// PDF の出力
OperationRecorder::record(OperationLogAction::Pdf, $member, detail: ['name' => '履歴書']);

// FormFlow を通らない更新や削除を、自分で記録するとき
OperationRecorder::record(OperationLogAction::Restore, $staff);
```

### 決まりごと

- **操作した人**：渡さなければ、その画面のガードでログインしている人です。管理画面ならスタッフ、マイページなら会員になります。2段階目の途中や、ログアウトした後のように、ログインはしていないが誰か分かっている操作は、`operator:` で渡します。
- **訪問者の操作も残します**：お問い合わせの送信やログインの失敗のように、誰もログインしていない操作は、操作した人を空にして残します。IP アドレスは残るので、回数の制限をすり抜けるような大量の操作があったときに、後から調べられます。
- **登録・更新とは別の名前で残す**：コントローラーに `savedLogAction()` を書きます。一斉メールは、送信の記録を1件作るのが送信の始まりなので、「登録」ではなく「一斉メールの送信」として残しています。
- **一覧の表示と検索は記録しません**：1ページに出る件数が限られているためです。機械的に続けて取る動きは、下の「管理画面の回数の制限」で止めて記録します。
- **CSV 取り込みは、取り込み全体で1行です**：1件ずつの更新は記録しません。件数の内訳は取り込みの記録（`t_csv_import_logs`）が持ち、`detail` の id でつながります。CSV のダウンロードも同じです。
- **変わった列に数えない列**：`created_at`・`updated_at` などの保存のたびに変わる列と、アップロードの元のファイル名の列（`〇〇_origin`）は数えません。最後に更新したスタッフの id のように、入力とは関係なく変わる列は、モデルの定数 `OPERATION_LOG_IGNORE` に書きます（例：`Member`）。
- **変わった列は、本体のテーブルの列だけです**：多対多のような関連のテーブルの変更は、列の名前には出ません。「更新した」ことは残ります。
- **変わった項目は、列の名前のまま出します**（`phone`・`disp_flg` など）。日本語の名前の対応表は持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。
- **`detail` に個人情報と入力値を入れません**。ログインの失敗のログイン ID だけは、どのアカウントが狙われたかを調べるために残しています。
- **残す期間**：`.env` の `OPERATION_LOG_DAYS`（既定は365日）。過ぎた行は、一時データの後片付け（19章）が消します。

### 管理画面の回数の制限（AdminRequestLimit）

**ファイル**：`app/Support/AdminRequestLimit.php`（冒頭のコメント）・`routes/web.php`（管理画面のグループの `throttle:admin-screen`）

ログイン後の管理画面の全体に、スタッフごとの回数の制限を掛けています。一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止めるためです。

- **回数**：`.env` の `ADMIN_REQUESTS_PER_MINUTE`（既定は1分に120回）。人が普通に使って届かない数にします。画面の表示のほか、アップロードや、送信中の画面の読み直しも1回に数えます。
- **数える単位**：操作しているスタッフの id です。同じ事務所から複数の人が使っても、互いに影響しません。
- **超えたとき**：その回は処理せず、少し待つよう案内する画面（`admin/too_many_requests.blade.php`）を返します。操作ログに「回数の制限」を残し、`warning` のログも書きます。`ERROR_NOTIFY_LEVEL` が `warning` なら、エラーの通知のメール（20章）が届きます。件名は `【要確認：サイト名】WARNING：管理画面の操作が上限を超えました（スタッフID:3）` です。
- **記録は、スタッフごとに1分に1回まで**：続けて取り出そうとすると、超えた回数だけ記録が増えてしまうためです。
- **管理画面のルートを足すとき**：`auth:admin` のグループの内側に書けば、この制限が掛かります。足すものはありません。
- **止められないもの**：上限より遅く、時間をかけて取る動きは止まりません。そちらは、詳細の閲覧や CSV のダウンロードの記録から調べます。

### 画面

- **一覧と検索**（`/admin/operation-logs`）：日付の範囲・操作・操作した人・対象・IP アドレスで絞れます。CSV でも出せます。見るだけの画面で、登録・編集・削除はありません。
- **どちらの画面も、管理者だけが開けます**：記録を取っていることを、スタッフの全員に見せる必要は無いためです。管理画面のトップのリンクも、管理者にだけ出ます。
- **会員ごとの操作ログ**（`/admin/members/{id}/operation-logs`。会員の詳細画面の「操作ログ」のボタン）：その会員を対象にした操作と、その会員が行った操作だけを出します。本人が変えたのか、スタッフが変えたのか、誰が詳細を開いたのかが、時刻の順に並びます。問い合わせに答えるのに使います。こちらも管理者だけが開け、ボタンも管理者にだけ出ます。
- 表は、2つの画面で同じ部分ビュー（`admin/operation_logs/_table.blade.php`）を使います。

### 会員情報が変わったときのお知らせメール（MemberProfileNotice）

会員がマイページで自分の情報を変えると、本人へメールで知らせます（`resources/mail-templates/member_profile_changed.blade.php`）。本人が変えたのなら記録が本人の手元にも残り、他人がログインして変えたのなら本人が気付けます。

```php
// マイページのコントローラー
private function afterSave(Member $member, array $validated, array $changedFields): void
{
    MemberProfileNotice::send($member, $changedFields);
}
```

- メールには、変わったことだけを書きます。どの項目が変わったかと、その値は載せません。
- 何も変わっていないときと、パスワードだけが変わったときは送りません。パスワードの変更は、`PasswordChange` が別のメールで知らせます。
- **管理画面からスタッフが変えたときは送りません**。本人から頼まれて変えることがほとんどで、知らせる必要が無いためです。誰が変えたかは、操作ログに残ります。パスワードをスタッフが変えたときのお知らせ（`PasswordChange`）は、今までどおり送ります。
- メールアドレスが変わったときは、変わる前と後の両方のアドレスに送ります。他人にアドレスを書き換えられたときに、本人が気付けるのは変わる前のアドレスだけだからです。変わる前のアドレスは、`$changedFields['email']` で受け取ります。
