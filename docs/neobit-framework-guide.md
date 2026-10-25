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
- [23. 個人会員と企業会員（LoginAccount）](#23.%20個人会員と企業会員（LoginAccount）)
- [付録. フレームワーク外で考慮すべきセキュリティ対策](#付録.%20フレームワーク外で考慮すべきセキュリティ対策)

## 版の履歴

| 版 | 日付 | 内容 |
|---|---|---|
| 第1版 | 2026-09-29 | 最初の版（一覧・検索、詳細、登録・更新、削除、アップロード・WYSIWYG、区分表、CSVダウンロード・取り込み、メール、お問い合わせ、権限、ログイン認証） |
| 第2版 | 2026-10-05 | ネオビットフレームワークの基本機能を実装 |
| 第3版 | 2026-10-07 | 企業会員の概念の追加とセキュリティ面の強化 |
| 第4版 | 2026-10-08 | WYSIWYG エディタ SunEditor に対応 |
| 第5版 | 2026-10-13 | ログイン周り機能の汎用化 |

## 0. このガイドについて

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

ネオビットフレームワークは、Laravel の上に「コーナー（一覧・詳細・登録・編集・削除などをひとまとめにした管理単位）を同じ型で作るための共通部品」を載せたものです。見本のサイト（memsys）で、実際に動くコーナーを作りながら育てています。このガイドは、新しいコーナーや機能を作るときに、どの部品を使い、コントローラー・画面・ルートに何を書けばよいかを、機能ごとにまとめたものです。

- Laravel そのものの基礎（ルーティング、Eloquent、Blade、マイグレーション、バリデーションのルールなど）は含めていません。
- 各部品の細かい仕様（引数の意味、内部の動き、なぜそうしているか）は、それぞれのファイルの冒頭のコメントに書いてあります。このガイドは「どこを見て、どう組むか」の地図で、詳しくは各章に書いたファイルを参照してください。
- 実例として、見本のサイト（memsys）に、どの機能にも動いているコーナーがあります。迷ったら実例のコントローラーと画面をそのまま写すのが一番早い方法です。

| 実例 | 使っている機能 |
|---|---|
| 訪問者向けニュース（`NewsController`） | ログイン不要の一覧・検索・詳細、会員限定の記事（ログイン中の会員にだけ見せる）、掲載期間（掲載開始日時・掲載終了日時。表示するたびに今の時刻と比べるので、スケジューラーは使わない）。条件は `News::visibleTo()`・`isVisibleTo()` にまとめてある |
| 訪問者向け固定ページ（`PageController`） | URL の名前（`/aboutus`・`/service/price` のように、`/` を含む名前も使える）が、そのままパスになるページ。WYSIWYG で書いた本文の表示 |
| お問い合わせ（`ContactController`） | 訪問者向けの入力・確認・送信、添付ファイル、メール送信、二重送信防止、スパム対策 |
| 会員の認証まわり（`AuthSessionController` ほか） | ログイン、確認コード、パスワード再設定・変更、会員登録、退会、パスキー |
| マイページ（`MypageController`） | 確認画面なしの編集（FormFlow）、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書）、退会、パスキー |
| 企業会員（`app/Http/Controllers/Company/` の下） | 企業と担当者、企業ID・担当者ID でのログイン、登録と運営の承認、担当者の招待、既存のシステムから移したデータの初回のログイン（23章） |
| 管理画面のログイン（`Admin\AuthSessionController` ほか） | TOTP・バックアップコード、信頼済み端末、パスキー |
| 管理画面：ニュース（`Admin\NewsController`） | 一覧・検索、登録・編集・確認画面、削除、アップロード（単数・複数）、WYSIWYG、CSVダウンロード・取り込み（追加あり）、記事の状態で見せ方の変わるファイル（一般公開・会員限定・非表示） |
| 管理画面：固定ページ（`Admin\PageController`） | 一覧・検索、登録・編集・確認画面、削除、WYSIWYG（SunEditor。ニュースは summernote）、本文に入れる画像のアップロード |
| 管理画面：会員（`Admin\MemberController`） | 一覧・検索、詳細・編集・確認画面、区分表（都道府県）、CSVダウンロード・取り込み（更新だけ）、`@名前` の列、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書） |
| 管理画面：企業会員（`Admin\CompanyController`・`Admin\CompanyUserController`） | 一覧・検索、詳細・編集・確認画面、CSVダウンロード、列挙型の状態をボタンで変える操作（承認・却下・停止・再開）と、その操作ログ、お知らせのメール、企業の詳細から入る担当者の確認・編集・削除（親の id を URL に持つコーナー） |
| 管理画面：スタッフ（`Admin\StaffController`） | 一覧・検索、登録・編集、論理削除と取り消し、権限（Policy）、列挙型、パスワード |
| 管理画面：カテゴリー（`Admin\CategoryController`） | 確認画面なしの登録・編集、並び替え、ページ分けしない一覧 |
| 管理画面：項目見出し一覧（フレームワークの `CodeTableController`） | DBで管理する区分表の編集（複数行をまとめて保存、行の追加・削除・並び替え） |
| 管理画面：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`・`app/Services/MailUnsubscribe.php`） | キュー（宛先の数だけのジョブを1つのバッチにして積む、送る速さの制限、進み具合の表示）、宛先の CSV の読み込みと検証（`CsvReader`）、氏名の差し込み、添付ファイル、配信停止（21章） |
| 管理画面：操作ログ（フレームワークの `OperationLogController`） | 登録・編集・削除の無い、見るだけの一覧。検索（日付の範囲・補足の中の語など、列と直接比べられない条件）、件数の棒グラフと、棒を押しての絞り込み、CSVダウンロード（22章） |

### 改版について

- 共通部品を足したり、使い方が変わったりしたら、このガイドの該当する章を直します。
- 章を足したら、冒頭の目次にも1行足します。新しい章は、付録より前に置きます。目次のリンク先は、章の見出しの文言そのままで、空白だけを `%20` に変えたものです（`## 9. CSV ダウンロード（CsvDownload）` なら `#9.%20CSV%20ダウンロード（CsvDownload）`）。Obsidian で飛べる形です。章の見出しを変えたら、目次のリンクも同じ文言に直します。章の見出しのすぐ下には、右寄せの「目次へ戻る」のリンクを置きます。Markdown には右寄せの書き方が無いので、ほかの章と同じ HTML の1行（`<p align="right"><a href="#目次" ...>目次へ戻る</a></p>`）を写します。
- 新しい機能の章を足すときは、「ファイル・実例 → コントローラーに書くもの → ルート → 画面 → 決まりごと」の順にそろえ、最後の章の後ろに足します。
- 細かい仕様は各ファイルの冒頭のコメントに書き、このガイドには「どこを見て、どう組むか」だけを書きます（同じことを2か所に詳しく書くと、片方だけ直して食い違うため）。

### サーバーへ送った後に実行するコマンド

ファイルをサーバーへ送った後、サイトのディレクトリで、上から順に要るものだけを実行します。実行するのは、デプロイ用のユーザーです。開発サーバーでは devapp で、root になる必要はありません。

| 順 | コマンド | 要るとき |
|---|---|---|
| 1 | `composer install --no-dev --optimize-autoloader` | `composer.json`・`composer.lock` を変えたとき |
| 2 | `npm ci` | `package.json`・`package-lock.json` を変えたとき |
| 3 | `npm run build` | `resources/js`・`resources/css`・`vite.config.js` を変えたとき、フレームワークを更新したとき |
| 4 | `php artisan migrate` | マイグレーションを足したとき |
| 5 | `php artisan config:cache` | 毎回。`.env` か `config/` を変えたときは必ず |
| 6 | `php artisan view:clear` | 毎回 |

- **`config:cache` は必須です**。サーバーでは、PHP-FPM を apache で動かし、`.env` は apache から読めないようにしています。`config:cache` は `.env` の値を取り込んだ `bootstrap/cache/config.php` を作り、Laravel はそれだけを読むようになるので、apache が `.env` を読めなくても画面も cron も動きます。
- **composer のコマンドの後は、`config:cache` をやり直します**。`composer install`・`composer dump-autoload`・`composer update` は、Laravel の決まり（`post-autoload-dump`）で `bootstrap/cache/config.php` を消します。やり直さないと、下の `config:clear` と同じく DB が SQLite になって止まります。
- **`config:clear` は実行しません**。`bootstrap/cache/config.php` が消えて設定の出どころが無くなり、DB が既定の SQLite になって、画面も cron（19章）も動かなくなります。
- `.env` を変えただけでは効きません。`config:cache` をやり直したときに効きます。
- `bootstrap/cache/` は、apache が読めて書けない状態にしています。ディレクトリに setgid と既定の ACL（`default:user:apache:r-x`）を付けてあるので、中に作ったファイルは誰が作っても apache が読めます。作った後に権限を直す必要はありません。`ls -l` では 660 に見えますが、末尾の `+` が ACL の印で、中身は `getfacl` で確かめます。
- 画面が「The bootstrap/cache directory must be present and writable」のエラーで止まったときは、中のファイルが古くなっています。apache は書けないので自分では作り直せません。`php artisan config:cache` をやり直します。
- `storage/` の下は、全部を apache が書けるようにします。ログのほか、コンパイル済みの画面とメールのテンプレートの展開（`storage/framework/views`）、エラーの通知の間引きの記録（`storage/framework/cache/data`）、アップロードしたファイルと一時ファイル（`storage/app`）を置くためです。書けないと、メールの送信が「tempnam(): file created in the system's temporary directory」のエラーで止まります。
- devapp が `storage/` の下に作ったファイル（artisan を実行したときのログなど）も、既定の ACL（`default:user:apache:rwx`）で apache が書けます。作った後に権限を直す必要はありません。
- 設定が効いているかは、画面を開いて確かめます。sudo を使えるときは `sudo -u apache php artisan config:show database.default` でも確かめられ、`mariadb` と出れば apache が読めています。devapp で同じコマンドを実行しても、devapp は `.env` を読めるので確かめになりません。
- 手元の開発環境は `.env` を直接読むので、`config:cache` は要りません。

### サイトの名前（APP_NAME・SITE_NAME）

サイトの名前は、`.env` の2つの項目で決めます。

```
APP_NAME=memsys
SITE_NAME="デモサイト"
MAIL_FROM_NAME="${SITE_NAME}"
```

| 項目 | 中身 | 使われる所 |
|---|---|---|
| `APP_NAME` | 機械の名前。半角の英数字で書く | セッションの Cookie の名前（`memsys-session`）、キャッシュのキーの頭 |
| `SITE_NAME` | 画面やメールに出す名前。日本語で書いてよい | メニューの左上、ブラウザのタブの題名、メールの差出人名、エラーの通知と操作ログの報告の件名 |

- **`APP_NAME` を日本語にしてはいけません**。Laravel は、`APP_NAME` から英数字だけを残して、Cookie とキャッシュの名前を作ります。日本語だけだと全部が消えて、Cookie の名前が `-session` になります。同じドメインに置いたほかの Laravel のサイトと名前が重なり、片方にログインすると、もう片方がログアウトします。
- **`SITE_NAME` を書かなければ、名前は出ません**。空のままになり、`APP_NAME` を代わりに出すことはありません。`APP_NAME` は処理の中で使う識別名で、人に見せる名前ではないためです。サイトを設置するときに、必ず `SITE_NAME` を書きます。
- **画面では `config('app.site_name')` で出します**。レイアウト（`layouts/app.blade.php`・`admin.blade.php`・`company.blade.php`）が、メニューと題名に使っています。メンテナンス中の画面（`maintenance.blade.php`）だけは、設定に頼らない1枚の HTML なので、名前を直接書いてあります。
- **`.env` に書くのは、サーバーごとに違う値と、秘密の値だけです**。接続先・宛先・鍵などです。残す日数や回数のように、どのサーバーでも同じ値（サイトの決まりごと）は、`config/` のファイルに書きます。Git に記録されるので、いつ変えたかを追えて、サーバーごとに値がずれません。Laravel の標準の項目のうち、このサイトの `.env` に書いていないものは、控えとして `.env.unused` にあります。Laravel は、このファイルを読みません。
- **`.env` を変えたら `php artisan config:cache` をやり直します**。`APP_NAME` を変えると Cookie の名前が変わるので、ログイン中の人は、ログインし直しになります。

### サーバーの前にプロキシを置くとき（TRUSTED_PROXIES）

ロードバランサーや CDN をサーバーの前に置くと、サーバーに直接つないでくるのはそのプロキシになります。訪問者の IP アドレスは、プロキシが付ける `X-Forwarded-For` というヘッダーで届きます。

```
# .env（プロキシの IP アドレス。カンマ区切り。範囲は 192.168.0.0/24 の形）
TRUSTED_PROXIES=10.0.0.5,10.0.0.6
```

- ここに書いたプロキシから届いたヘッダーだけを、訪問者の IP アドレスとして使います（`AppServiceProvider`）。`https` かどうかやホスト名も、同じプロキシのヘッダーから取ります。
- **プロキシを置いたのに書かないと**、全員の IP アドレスがプロキシのものになります。ログインの試行制限が1人の失敗で全員に効く、回数の制限が全員で共有される、操作ログやエラーの通知の IP アドレスが全部同じになる、といったことが起きます。
- **プロキシの IP アドレスだけを、正確に書きます**。ここに書いた IP アドレスから接続した人は、IP アドレスを偽れるようになります。このヘッダーは送る側が自由に書けるためです。偽れると、試行制限を逃れたり、接続元の IP アドレスの制限をすり抜けたりできます。広い範囲や、プロキシではない機器の IP アドレスは書きません。プロキシを置いていなければ、空のままにします。
- 訪問者の側がプロキシや VPN を通してきたときは、そのプロキシの IP アドレスが残ります。本当の IP アドレスは、こちらからは分かりません。
- `.env` を変えたら `php artisan config:cache` をやり直します。
### 接続元の IP アドレスの制限（アクセス制限）

**ファイル**：`packages/neobit/core/src/Security/AccessRestriction.php`（冒頭のコメント）・`src/Http/Middleware/RestrictSiteAccess.php`・`RestrictAdminAccess.php`・`src/Http/Controllers/AccessRestrictionController.php`、画面は `packages/neobit/core/resources/views/maintenance.blade.php`

サイト（訪問者画面）と管理画面を開ける IP アドレスを、管理画面の「アクセス制限」で、それぞれ絞れます。運用する人が、サーバーに入らずに変えられます。2つの制限は、互いに影響しません。

| 項目 | チェックを入れたとき | 使うとき |
|---|---|---|
| サイト（訪問者画面） | サイトの IP アドレスのほかに、メンテナンス中の画面を 503 で返す。管理画面には掛からない | 公開前のデモの運用、メンテナンスの間 |
| 管理画面 | 管理画面の IP アドレスのほかに、ログイン画面も含めて 404 を返す。サイトには掛からない | 事務所などの決まった場所からしか管理画面を使わないサイト |

- **入力**：1行に1つか、カンマ区切りです。`192.168.0.0/24` のようなまとまりと、IPv6 も書けます。
- **チェックを入れたときだけ掛かる**：IP アドレスを書いただけでは、制限は掛かりません。
- **設定ファイルの分**：`.env` の `SITE_ALLOWED_IPS`・`ADMIN_ALLOWED_IPS` に書いた IP アドレスは、それぞれの一覧に足し合わせます。画面からは外せず、画面にはそれぞれの欄の上に「次のIPアドレスは、設定ファイルで常に許可されています。」と出ます。サーバーを管理する人の事務所などを書いておくと、画面でどう設定しても締め出されません。`.env` を変えたら `php artisan config:cache` をやり直します。
- **締め出さない**：管理画面の制限を掛けて保存するとき、今の自分の IP アドレスが管理画面の一覧に無ければ、保存させません。サイトの制限は管理画面に影響しないので、確かめません。それでも回線が変わるなどで締め出されたときは、サーバーで `php artisan app:access-denied-off` を実行します。両方の制限を外します。IP アドレスの一覧は残ります。
- **ルート**：案件が書きます。機能を使うかどうかを、案件ごとに決めるためです。管理者だけのグループの中に、「頭.index」（GET）と「頭.update」（PATCH）の組で名前を付けます。

    ```php
    // routes/web.php（管理者だけのグループの中）
    Route::get('/access-restriction', [AccessRestrictionController::class, 'index'])->name('access-restriction.index');
    Route::patch('/access-restriction', [AccessRestrictionController::class, 'update'])->name('access-restriction.update');
    ```

- **サイトの制限から管理画面を外す**：`bootstrap/app.php` の `RestrictSiteAccess::except()` に、管理画面の URL（見本のサイトでは `admin`・`admin/*`）を書きます。管理画面の URL の頭は案件が決めるので、案件の側で書きます。
- **設定の置き場所**：DB の `settings` テーブルです。毎回のアクセスで読むので、キャッシュに置き、保存のたびに作り直します。変えた内容は、操作ログに「アクセス制限の変更」として残ります。
- **IPブロック管理との関係**：2つの一覧にある IP アドレスは、IPブロック管理で止めません（22章）。
- **プロキシを前に置くときは、`TRUSTED_PROXIES` を先に書きます**。書かないと、全員がプロキシの IP アドレスに見えるので、全員が通るか、全員が止まります。
- **メンテナンス中の画面**は、レイアウトを継承しない1枚の HTML です。DB もセッションも使わないので、DB を止めている間も出せます。文言は、アプリの `resources/views/vendor/neobit/maintenance.blade.php` に同じ名前で置いて上書きします。デモの運用のときも、この画面が出ます。
- **503 で返す**のは、一時的に止めていることを検索エンジンに伝えて、登録を消されないようにするためです。
- **制限の間も通すパス**は、`bootstrap/app.php` の `RestrictSiteAccess::except()` に書きます。今は、死活監視（`/up`）と、お知らせメールの配信停止（`/mail/unsubscribe`）と、管理画面です。止めている間に届いたメールからも、停止できるようにするためです。
- **管理画面を 404 にする**のは、そこに管理画面があることを知らせないためです。止めたアクセスは、操作ログに残しません。
- **Apache が直接返すファイルは止まりません**。`public/build/` の中と、公開のアップロードファイル（`public/storage/`）は、Laravel を通らないためです。そこまで止めるときは、Apache の設定で絞ります。
- **コマンドは止まりません**。スケジューラー（19章）とキュー（21章）は、制限の間も動きます。メンテナンスの間に一斉メールを送りたくないときは、送信が終わってから止めます。
- Laravel の `php artisan down` は使いません。サーバーでコマンドを打つ必要があり、画面の設定とは別の仕組みになるためです。

### セキュリティのヘッダー（SecurityHeaders）

**ファイル**：`packages/neobit/core/src/Http/Middleware/SecurityHeaders.php`（冒頭のコメント）

ブラウザに守り方を伝えるヘッダーを、全部の応答に付けます。`bootstrap/app.php` で全体のミドルウェアに足しているので、コントローラーと画面に書くものはありません。

| ヘッダー | 値 | 働き |
|---|---|---|
| `X-Frame-Options` | `SAMEORIGIN` | ほかのサイトの `<iframe>` の中に、このサイトの画面を出させない |
| `X-Content-Type-Options` | `nosniff` | ファイルの種類を、ブラウザに中身から決めつけさせない |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | ほかのサイトへ移るときは、開いていた URL のうちドメインまでしか渡さない |
| `Strict-Transport-Security` | `max-age=31536000` | ブラウザが1年の間、このドメインを HTTPS だけで開く。HTTPS で届いたときだけ付く |

- **環境ごとの切り替えは要りません**。`Strict-Transport-Security` は、HTTPS で届いたリクエストにだけ返します。HTTP で動かす手元の開発環境では出ません。
- **HTTPS をやめる予定のあるドメインでは、先に外します**。一度受け取ったブラウザは、1年の間そのドメインを HTTP で開けなくなります。外すときは、`max-age=0` を返す期間を置いてから HTTPS をやめます。
- サブドメインは対象にしていません。同じドメインの下に HTTP で動かしているサイトがあっても、開けなくなることはありません。
- プロキシを前に置くときは、`TRUSTED_PROXIES` を書きます。書かないと、HTTPS で届いたことが分からず、`Strict-Transport-Security` が付きません。
- **Apache が直接返すファイルには付きません**。`public/build/` の中と、公開のアップロードファイル（`public/storage/`）は、Laravel を通らないためです。そちらにも付けるときは、Apache の設定に書きます。
- 読み込んでよい場所を決める `Content-Security-Policy` は、まだ付けていません。

### SEO（ページのタイトル・説明文・OGP）

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`（冒頭のコメント）・`SeoSubject.php`、画面は `packages/neobit/core/resources/views/_seo_head.blade.php`　**実例**：ニュースの詳細（`NewsController::show()`）・固定ページ（`PageController::show()`）

訪問者の側のページの `<head>` に、タイトル・説明文・OGP・X のカードを、サイト全体で揃った形で出します。何も入れなくても、記事の詳細では記事から、ほかの画面ではサイトの既定から値が決まります。運用する人は、記事ごとの SEO の欄と、管理画面の「SEO設定」で値を変えられます。

```blade
{{-- layouts/app.blade.php の <head> --}}
@include('neobit::_seo_head')
```

```php
// モデル。SEOの元になる値を返す
class News extends Model implements SeoSubject
{
    public function seoTitle(): string { return $this->title; }
    public function seoDescriptionSource(): ?string { return $this->body; }
    public function seoImageUrl(): ?string { return $this->list_image_url; }
}

// コントローラーの詳細の画面
Seo::for($news);
```

| 値 | 記事の詳細（`Seo::for()` を呼んだ画面） | ほかの画面 |
|---|---|---|
| タイトル | 記事の SEO の欄か記事のタイトルを、タイトルの形（`{title}｜{site}`）に当てはめる | 「SEO設定」のページのタイトルか、画面の `@section('title')` を当てはめる。無ければサイト名だけ |
| 説明文 | 記事の SEO の欄か、本文のタグを除いた先頭の120文字（長ければ「…」） | 「SEO設定」のページの説明文か、既定の説明文。空なら説明文のタグを出さない |
| シェアの画像 | 記事の SEO の欄か記事の画像。無ければ既定の画像 | 「SEO設定」のページの画像か、既定の画像。無ければ出さない |
| `og:type` | article | website |

- **サイトの既定**：管理画面の「SEO設定」の既定（タイトルの形・説明文・画像）で、空欄なら `config/neobit.php` の `seo`（`title_format`・`description`・`image`）です。本文から作る説明文の文字数は `description_length` です。既定の画像は、`public` の下のパス（例：`/images/ogp.png`）か URL で書きます。
- **Blade で値を使う**：ヘルパーの `seo()` で取り出せます。名前は `'title'`・`'description'`・`'image'`・`'url'`・`'canonical'`・`'type'`・`'site_name'`・`'robots'`・`'site_verification'` です。`_seo_head` も中でこれを使っているので、並べ方を変えたい案件は、部品を使わずにレイアウトで自分で書けます。

    ```blade
    <meta name="description" content="{{ seo('description') }}">
    ```
- **URL**：画像と `og:url` は `https://` から始まる絶対の URL で出します。`og:url` は `?` の後ろを除いた今の URL です。
- **説明文の作り方**：本文の `<script>`・`<style>` は中身ごと除き、段落や改行のタグは空白にしてから、残りのタグを除きます。
- 部品を置くのは、訪問者の側のレイアウトだけです。管理画面と企業会員の画面は、検索に出さない画面のためです。

#### 検索に出さないページ・canonical・サイトマップ・robots.txt

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`・`SitemapSource.php`・`src/Http/Controllers/SitemapController.php`・`RobotsController.php`

- **検索に出さない（noindex）**：次のどれかの画面には、`<meta name="robots" content="noindex">` が自動で付きます。
    - ルートに `auth` のミドルウェアが付いた画面（マイページなど、ログインしないと開けない画面）
    - コントローラーで `Seo::noindex()` を呼んだ画面。一覧の絞り込みはセッションに持っていて URL からは見分けられないので、絞り込みのある一覧で呼びます（見本はニュースの一覧）
    - 設定の `seo.noindex_routes` に並べたルートの名前。`*` が使えます（見本はお問い合わせの完了 `contact.thanks`）
    - 記事の SEO の欄で、検索に出さないにしたもの
    - 「SEO設定」のページごとの設定で、検索に出さないにしたもの
- **canonical**：全部の画面に `<link rel="canonical">` を出します。今の URL から `?` の後ろを除いたもので、ページ送りの `?page=` だけは残します（2ページ目以降も別のページとして検索に出すため）。
- **XML サイトマップ**（`/sitemap.xml`。フレームワークのルート `seo.sitemap`）：設定の `seo.sitemap_routes`（記事を持たないページのルートの名前）と、`seo.sitemap_models`（記事のモデル）から作ります。モデルは `SitemapSource` を実装し、訪問者に見せてよい記事のクエリ（`sitemapQuery()`）と記事の URL（`sitemapUrl()`）を返します。SEO の欄で検索に出さないにした記事と、「SEO設定」で検索に出さないにしたページは載せず、`updated_at` を更新日として出します。毎回その場で作るので、記事が数千件を超えるサイトでは、分割やキャッシュを考えます。

    ```php
    class News extends Model implements SeoSubject, SitemapSource
    {
        public static function sitemapQuery(): Builder { return static::query()->visibleTo(null); }
        public function sitemapUrl(): string { return route('news.show', $this); }
    }
    ```
- **robots.txt**（`/robots.txt`。フレームワークのルート `seo.robots`）：本番（`APP_ENV=production`）では、設定の `seo.robots_disallow` の場所を巡回させず、サイトマップの絶対の URL を伝えます。本番以外（開発サーバーなど）では、全部を巡回させません（`Disallow: /`）。`public/robots.txt` があると Apache がそちらを返すので、置きません。
- **設定を書くとき**：`seo` は配列のキーなので、アプリの `config/neobit.php` で書くときは、全部のキーを書きます（見本のサイトの `config/neobit.php` を参照）。

#### SEO 設定の画面（記事を持たないページとサイト全体の既定）

**ファイル**：`packages/neobit/core/src/Seo/SeoSettings.php`（冒頭のコメント）・`src/Http/Controllers/SeoSettingsController.php`、画面は `packages/neobit/core/resources/views/admin/seo_settings/index.blade.php`　**実例**：管理画面の「運用」の「SEO設定」

運用する人が管理画面から、サイト全体の既定（タイトルの形・説明文・シェアの画像・Google Search Console の確認タグ）と、記事を持たないページ（トップページ・一覧・お問い合わせなど）ごとのタイトル・説明文・画像・検索に出さないを変えられます。値は `settings` テーブルに持ちます。

```php
// routes/web.php。管理者だけが入れるグループの中に、「頭.index」「頭.update」の組で書く
Route::get('/seo-settings', [SeoSettingsController::class, 'index'])->name('seo-settings.index');
Route::patch('/seo-settings', [SeoSettingsController::class, 'update'])->name('seo-settings.update');

// config/neobit.php の seo。画面に出すページ。ルート名 => 画面に出す名前
'page_routes' => [
    'top' => 'トップページ',
    'news.index' => 'お知らせの一覧',
],
```

- **画面に出すページ**：設定の `seo.page_routes` に並べたルートです。引数の要らないルートだけを書きます。引数の要るルートを書くと、画面に URL を出しません。
- **値の優先**：記事の SEO の欄 > 記事の値 > 「SEO設定」のページごとの値 > 画面の `@section('title')` > 「SEO設定」の既定 > 設定ファイルの順です。「SEO設定」のページの値を `@section('title')` より先にするのは、運用する人が画面で入れた値だからです。記事のあるページでは記事の値が先に決まるので、ページごとの値は、記事を持たないページで効きます。
- **空欄**：既定を空欄にすると、設定ファイルの値を使います。入力欄の見本に、その設定ファイルの値を出します。ページの欄を全部空にすると、そのページの設定を持ちません。
- **タイトルの形**：`{title}` を必ず入れます。`{site}` は任意です。
- **運営者**：運営者の名前（空欄なら設定ファイルの `organization_name`、それも空ならサイト名）とロゴの URL を、構造化データで出します。
- **確認タグ**：Search Console が示す `<meta name="google-site-verification" content="…">` をそのまま貼っても、値だけを入れてもかまいません。content の値（英数字・`_`・`-`）だけを保存し、`_seo_head` が全部のページの `<head>` に出します。
- **検索に出さない**：チェックしたページには noindex を付け、サイトマップからも外します。
- **キャッシュ**：訪問者の全部のページで読むので、画面の設定をキャッシュに置き、保存のたびに作り直します。DB を直接書き換えたときは、キャッシュを消します。
- **操作ログ**：保存すると「SEO設定の変更」に、変えた項目の名前だけを残します。説明文などの値は残しません。何も変わっていなければ残しません。確認画面は挟みません。

#### パンくずリストと構造化データ（JSON-LD）

**ファイル**：`packages/neobit/core/src/Seo/Seo.php`・`SeoArticle.php`、画面は `packages/neobit/core/resources/views/_breadcrumbs.blade.php`・`_seo_head.blade.php`　**実例**：ニュースの詳細（`NewsController::show()`）・固定ページ（`PageController::show()`）

```blade
{{-- layouts/app.blade.php の本文の上 --}}
@include('neobit::_breadcrumbs')
```

```php
// コントローラー。途中の段を、浅い段から順に足す
Seo::for($news);
Seo::breadcrumb('お知らせ', route('news.index'));

// 段の数が決まっていないとき（親子のカテゴリーなど）は、親をたどって足す
foreach ($category->ancestorsAndSelf() as $node) {
    Seo::breadcrumb($node->name, route('products.category', $node));
}
```

- **パンくずの段**：先頭の「トップ」と、末尾の今のページ名は自動で付きます。ページ名は `<title>` と同じ順（記事の SEO の欄 → 記事 → 「SEO設定」のページ → `@section('title')`）で決まる、タイトルの形に当てはめる前の名前です。トップページと、ページ名の無い画面には出しません。段を足していない画面は「トップ > ページ名」です。
- **URL の無い段**：`Seo::breadcrumb('製品情報')` のように URL を省くと、リンクにせず文字だけを出します。
- **固定ページの入れ子**：見本の `PageController::show()` は、slug（`company/history`）を「/」で区切り、公開している親のページを段にします。親は1回の問い合わせでまとめて探し、非公開や無いページの段は飛ばします。slug の持ち方はコーナーごとに違うので、部品にはしていません。
- **構造化データ**：`_seo_head` が `<script type="application/ld+json">` を1つ出します。中身は今のページに当てはまるものだけです。
    - パンくず（`BreadcrumbList`）：パンくずのある画面。今のページの段には URL を付けません
    - 記事（`Article`）：`SeoArticle`（`seoPublishedAt()`・`seoModifiedAt()`）を実装したモデルの記事。題・画像・公開日・更新日・正規の URL と、書いた側・出した側（運営者）
    - サイトと運営者（`WebSite`・`Organization`）：トップページだけ。運営者の名前とロゴは「SEO設定」
- **出さないとき**：検索に出さないページでは、構造化データを出しません。パンくずの表示は出します。
- **エスケープ**：JSON の `<`・`>`・`&` は `\u003C` などに置き換えるので、記事の題に `</script>` があっても抜け出せません。

    ```php
    class News extends Model implements SeoArticle, SitemapSource
    {
        public function seoPublishedAt(): ?DateTimeInterface { return $this->article_date; }
        public function seoModifiedAt(): ?DateTimeInterface { return $this->updated_at; }
    }
    ```

#### slug（URL の名前）の検証のルール

**ファイル**：`packages/neobit/core/src/Rules/SlugRule.php`・`NotReservedPathRule.php`　**実例**：固定ページ（`Admin\PageController`）

slug はコーナーのテーブルの列に持ち、検証のルールだけを共通の部品にしています。重なりの検証は Laravel の `Rule::unique`、URL から探すのは `where()` で足ります。

```php
'slug' => [
    'required', 'string', 'max:100',
    new SlugRule(nested: true),                           // 形。nested: true なら「/」で区切ってつなげられる
    Rule::unique(Page::class, 'slug')->ignore($page?->id),
    new NotReservedPathRule(),                            // URL の直下（/{slug}）に置くコーナーだけ
],

// routes/web.php。検証と同じパターンを使う
Route::get('/{slug}', ...)->where('slug', SlugRule::NESTED_PATTERN);
```

- **形**：半角の英小文字・数字・ハイフン・アンダーバーで、先頭は英小文字か数字。`nested: true` なら「/」でつなげられ、先頭・末尾の「/」と「//」は通しません。エラーの文は、通常の `validation.regex` の文です。
- **ほかの画面と重ならない**：名前の最初の区切りが、ほかのルートの URL の最初の区切りと同じなら断ります。`/news/{slug}` のように頭の付いた URL に置くコーナーでは要りません。

#### 記事ごとの SEO の欄

**ファイル**：`packages/neobit/core/src/Seo/SeoFields.php`（コントローラー）・`HasSeoMeta.php`（モデル）・`src/Models/SeoMeta.php`、画面は `packages/neobit/core/resources/views/_seo_fields.blade.php`　**実例**：ニュースと固定ページの登録・編集（`Admin\NewsController`・`Admin\PageController`）

運用する人が、記事ごとに SEO のタイトル・説明文・シェアの画像の URL・検索に出さない（noindex）を、登録・編集の画面で決められます。値は共通のテーブル `seo_metas` に1記事1行で持つので、コーナーのテーブルに列を足す必要はありません。

```php
// モデル。SeoSubjectと一緒に使う。モデルの短い名前（enforceMorphMap()）にも登録しておく
class News extends Model implements SeoSubject
{
    use HasSeoMeta;
}

// コントローラー
use SeoFields;

] + $this->ajaxUploadRules() + $this->seoRules();
```

```blade
{{-- 入力欄（_fields.blade.php） --}}
@include('neobit::_seo_fields', ['input' => $input, 'readonly' => $readonly])
```

- **空欄のとき**：入れていない項目は、記事から自動で補います（タイトル・本文の先頭・記事の画像かサイトの既定の画像）。全部空なら、`seo_metas` の行を持ちません。
- **画像**：アップロードではなく URL を入れます。`/` から始まるサイトの中のパス（本文に挿入した画像の URL など）か、`http(s)://` から始まる URL です。その画像を後で消すと、シェアの画像は出なくなります。
- **検索に出さない**：入れると `<meta name="robots" content="noindex">` を出します。ヘルパーでは `seo('robots')` が `noindex` を返します。
- **画面**：入力の画面では、普段は閉じた「SEO の設定」の枠で、値かエラーがあれば開いた状態で出ます。確認・詳細の画面では、入れた値だけを並べ、全部空なら「（自動）」と出します。
- **FormFlow が呼ぶ**：値の組み立て・保存・削除は FormFlow が呼ぶので、入口の書き方は変わりません。部品を置いていない画面からの保存では、SEO の欄には触りません。
- CSV の書き出し・取り込みには含めません。SEO の欄の変更は、操作ログの「変わった列」には入りません。

### まだ無い機能（今後の予定）

自動テストは、認証の流れ（個人会員とスタッフのログイン、パスワードの変更と再設定、会員登録。`tests/Feature/Auth/`）と、企業会員（登録・ログイン・マイページ・招待・初回のログインでの登録と、管理画面の企業会員と担当者。`tests/Feature/Company/`）にあります。パスキーと、ほかの管理画面のコーナーは、まだです。そろってきたら、章を足します。

テストは `php artisan test` で動かします。テスト用の DB（`memsys_testing`。`phpunit.xml` に書いてあります）を、手元の DB のサーバーに作っておきます。テストのたびにテーブルを作り直すので、ふだんの DB（`memsys_local`）とは別にします。

```sql
CREATE DATABASE memsys_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON memsys_testing.* TO 'memsys'@'localhost';
```

## 1. 全体像

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

### 1-1. 動作条件

| | 最低バージョン | 根拠の代表例 |
|---|---|---|
| PHP | 8.3 | Laravel 13 本体が PHP 8.3 以上を要求する（`laravel/framework` の composer.json が `"php": "^8.3"`）。フレームワーク自身のコードで使っている一番新しい書き方は、列挙型の定数のキーに case の値を使う `self::Gender->value => [...]`（PHP 8.2 から。`StaffAcl::LABELS`） |
| Laravel | 13 | 見本のサイト（memsys）の composer.json が `"laravel/framework": "^13.17"` で、この版で開発・動作確認している。フレームワークのコードで使っている機能の一番新しいものは、モデルのスコープを属性で書く `#[Scope]`（Laravel 12 から。`News::visible()`） |

- PHP の最低バージョンは、フレームワーク自身の書き方ではなく、Laravel 13 の要求で決まっています。
- Laravel 12 でも動く可能性はありますが、動作を確かめていないので対象外とします。新しい案件は、その時点の最新の Laravel で始める前提です。
- このほか、パスキー（14章）を使う場合は、ブラウザで開くアドレスが HTTPS であること（開発時の `localhost` は例外）が必要です。
- 新しい機能を足して、ここに書いたより新しい PHP・Laravel の機能を使ったときは、この表を直します。

### 1-2. 部品の一覧

| 部品 | 場所 | 役割 | 章 |
|---|---|---|---|
| `SearchableList` | `packages/neobit/core/src/Crud/` | 一覧・検索（検索条件の検証と保存、完全一致・部分一致の自動判定、並び順、ページと検索条件の復元） | 3 |
| `FormFlow` | `packages/neobit/core/src/Crud/` | 入力 → 確認 → 保存、削除（トランザクション込み）、必須マーク | 5・6 |
| `InputNormalizer`・`NormalizeInput` | `packages/neobit/core/src/Crud/`・`packages/neobit/core/src/Http/Middleware/` | 入力の全角と半角の揺らぎをそろえる（画面の入力と CSV 取り込み。検証の前） | 5 |
| `SecurityHeaders` | `packages/neobit/core/src/Http/Middleware/` | ブラウザに守り方を伝えるヘッダーを、全部の応答に付ける | 0 |
| `AccessRestriction`・`RestrictSiteAccess`・`RestrictAdminAccess`・`AccessRestrictionController` | `packages/neobit/core/src/Security/`・`src/Http/Middleware/`・`src/Http/Controllers/` | アクセス制限（サイトはメンテナンス中の画面、管理画面は 404。それぞれ別に掛ける）。管理画面の「アクセス制限」で設定する | 0 |
| `AjaxFileUpload` | `packages/neobit/core/src/Upload/` | 画像・添付ファイルの Ajax アップロード、WYSIWYG の画像 | 7 |
| `UploadFilePath` | `packages/neobit/core/src/Upload/` | アップロードしたファイルの保存先と URL の規則（公開・非公開・一時ファイル） | 7 |
| `UploadedFileController` | `packages/neobit/core/src/Http/Controllers/` | ログインした人だけが見られるファイルと、一時ファイルを返す | 7 |
| `CodeTableController` | `packages/neobit/core/src/Http/Controllers/` | 管理画面の項目見出し一覧。DB で管理するコード表をまとめて書き換える | 8 |
| `PdfDownload` | `packages/neobit/core/src/Pdf/` | Blade のテンプレートから PDF を作って返す（mPDF、同梱の IPAex フォント） | 18 |
| `HtmlSanitizer`（`safe_html()`） | `packages/neobit/core/src/Upload/` | WYSIWYG の HTML の無害化 | 7 |
| `CodeTable`（`code_table()` など） | `packages/neobit/core/src/Code/`・`packages/neobit/core/src/helpers.php` | 区分表（列挙型・CSV・DB） | 8 |
| `CsvDownload`・`CsvColumnSet`・`CsvWriter` | `packages/neobit/core/src/Csv/` | CSV ダウンロードと、CSV の項目の定義、CSV の共通の書き出し方 | 9 |
| `CsvImport` ほか | `packages/neobit/core/src/Csv/` | CSV 取り込み（取り込み画面と保存の流れ） | 10 |
| `CsvReader` | `packages/neobit/core/src/Csv/` | CSV を読んで確かめる部分。自分の画面を持つ機能が、CSV の読み込みと検証だけを借りるときに使う | 10 |
| `MailTemplate`・`TemplatedMail` | `packages/neobit/core/src/Mail/`・`packages/neobit/core/src/Mail/` | テンプレートファイルによるメール送信 | 11 |
| `LoginThrottle` | `packages/neobit/core/src/Auth/` | 認証の失敗回数による試行制限 | 14 |
| `LoginAccount`・`IsLoginAccount`・`HasTwoFactor` | `packages/neobit/core/src/Auth/` | ログインするモデル（個人会員・企業の担当者・スタッフ）の共通の型と、名前の決まり、2段階認証にかかわるモデルの中身 | 14・23 |
| `LoginIdType` | `packages/neobit/core/src/Enums/` | ログインに使う値の種類（メールアドレス・ログインID・どちらでも）。モデルの定数で選ぶ | 23 |
| `TwoFactorLogin` | `packages/neobit/core/src/Auth/` | ログインの、パスワードが合った後の流れ（2段階目・信頼済みの端末・ログインの確定）と、ログアウト | 14 |
| `TwoFactorChallenge` | `packages/neobit/core/src/Auth/` | その人の2段階目の方式で、本人かどうかを確かめる | 14 |
| `TwoFactorManagement` | `packages/neobit/core/src/Auth/` | ログイン中の本人による、バックアップコードの発行と、認証アプリの登録の解除 | 14 |
| `PasskeySuggestion` | `packages/neobit/core/src/Auth/` | パスワードでログインした人に、パスキーの登録を1回だけ勧める | 14 |
| `LegacyPasswordUserProvider`・`LegacyPassword` | `packages/neobit/core/src/Auth/` | 既存のシステムから移した会員の、古い方式のパスワードの照合と、今の方式への置き換え | 14 |
| `LoginIdMemory` | `packages/neobit/core/src/Auth/` | ログイン画面の入力（企業ID と担当者ID）を、ブラウザに覚えさせる | 14 |
| `TeamInvitationManager`・`TeamInvitationOwner`・`TeamInvitationRelation` | `packages/neobit/core/src/Team/` | 集団への招待（リンクの発行・送り直し・取り消し・照合）。企業会員の担当者の招待に使う。集団のモデルに、インターフェースとトレイトを付ける | 23 |
| `LoginSession` | `packages/neobit/core/src/Auth/` | そのガードだけのログアウト（同じブラウザのほかのログインは残す） | 14 |
| `LoginRedirect` | `packages/neobit/core/src/Auth/` | ログインの後の移動先（開こうとしていた画面へ戻す。戻り先は、ログインの種類ごとに覚える） | 14 |
| `LoginVerificationCode` | `packages/neobit/core/src/Auth/` | メールで送る確認コード | 14 |
| `EmailChange` | `packages/neobit/core/src/Auth/` | 本人がメールアドレスを変えるときに、新しいアドレスへの確認コードの入力を挟む | 14 |
| `TrustedDeviceManager` | `packages/neobit/core/src/Auth/` | 2段階目を省略できる信頼済み端末 | 14 |
| `TwoFactorAuthenticator`・`BackupCodeGenerator` | `packages/neobit/core/src/Auth/` | 認証アプリ（TOTP）のコードと、バックアップコード | 14 |
| `PasskeyLogin`・`PasskeyManagement` | `packages/neobit/core/src/Auth/` | パスキーでのログインと、本人によるパスキーの登録・削除 | 14 |
| `PasswordChange` | `packages/neobit/core/src/Auth/` | パスワードを変えたときの後始末（信頼済み端末・パスキーの無効化、お知らせのメール） | 14 |
| `_confirm_hidden` | `packages/neobit/core/resources/views/` | 確認画面の hidden を `$input` から組み立てる | 5 |
| `_ajax_upload_block` | `packages/neobit/core/resources/views/` | アップロード欄（1つだけの欄と、子テーブルの中の欄） | 7 |
| `ChildTables`・`_repeatable_group` | `packages/neobit/core/src/Crud/`・`packages/neobit/core/resources/views/` | 1:多の子テーブルを、親と同じ画面で入力して保存する（画面の部品は `_repeatable_group`） | 5 |
| `admin/csv_import/` | `packages/neobit/core/resources/views/` | CSV 取り込みの画面（全コーナー共通） | 10 |
| `_passkeys` | `packages/neobit/core/resources/views/` | パスキーの一覧・本人確認・登録の画面の中身 | 14 |
| `TemporaryDataCleaner` | `packages/neobit/core/src/Operations/` | 一時データの後片付け（一時ファイル・期限の切れたキャッシュと信頼済み端末・古い操作ログ）。プロジェクトの片付けは `config/cleanup.php` に足す | 19 |
| `app:cleanup-temporary-data` | `packages/neobit/core/src/Console/` | 一時データの後片付けのコマンド（スケジューラーから1時間ごと） | 19 |
| `app:reset-two-factor` | `packages/neobit/core/src/Console/` | 2段階認証の登録を解除するコマンド（画面から解除できる人がいないときに使う） | 14 |
| `app:set-next-id` | `packages/neobit/core/src/Console/` | 新しく登録する行の id の始まりを決めるコマンド（サイトを開ける前に使う） | 16 |
| `ErrorNotifyHandler` | `packages/neobit/core/src/Operations/` | ログに書いたエラーを開発者にメールで知らせる（レベル・宛先は .env、間隔は `config/logging.php`） | 20 |
| `OperationRecorder` | `packages/neobit/core/src/Operations/` | 操作ログ（誰が・いつ・どこから・何に・何をしたか）を書く | 22 |
| `OperationLogStats`・`OperationLogReport` | `packages/neobit/core/src/Operations/` | 操作ログの件数の集計（一覧の上のグラフと表）と、1日ぶんの報告 | 22 |
| `app:report-operation-logs` | `packages/neobit/core/src/Console/` | 操作ログの報告のコマンド（スケジューラーから毎朝。気になる点があった日だけメールを送る） | 22 |
| `AdminRequestLimit` | `packages/neobit/core/src/Security/` | ログイン後の管理画面の全体に掛ける、スタッフごとの回数の制限 | 22 |
| `LoginProfileNotice` | `packages/neobit/core/src/Auth/` | 会員情報が変わったことを、本人へメールで知らせる | 22 |
| `SpamGuard`・`_spam_guard` | `packages/neobit/core/src/Security/`・`packages/neobit/core/resources/views/` | 訪問者向けフォームのスパム対策（ハニーポット・送信までの時間・Cloudflare Turnstile） | 12 |
| `global.js`・`global.css` | `packages/neobit/core/resources/js/`・`css/` | 全画面で使うスクリプトと CSS。フォームの補助（必須マークから required 属性、エラー表示）など | 17 |

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
    private function formValues(...): array { ... }
    // defaultValues()・prepareInput() は必要なときだけ

    // ---- 一覧・検索 ----
    public function index(...) { ... }
    private function srchRules(): array { ... }
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
- **設定の既定の値は、フレームワークが持つ**：`neobit`・`upload`・`cleanup` の設定は、`packages/neobit/core/config/` に既定の値があります。アプリの `config/` に同じ名前のファイルで書いたキーが、それに優先します。優先するのは、いちばん上の階層のキーごとです。配列の値（`upload.image_types`・`neobit.operation_report_thresholds` など）は、アプリが書くと丸ごと置き換わるので、変えない要素も全部書きます。アプリの設定ファイルでは、既定と同じ値は頭に `//` を付けて見本として残し、変える値だけ `//` を外して書き換えます。生きている行が、そのプロジェクトで変えた値です。`php artisan vendor:publish --tag=neobit-config` で、全部の値に `//` を付けた見本（`packages/neobit/core/stubs/config/`）を `config/` へ写せます。既定の値を変えたり足したりしたら、見本も合わせます。合っているかは、テスト（`ConfigStubTest`）が確かめます。
- **Blade のコメントは `{{-- --}}` で書く**：`<style>` や `<script>` の中でも同じです。`/* */`・`//`・`<!-- -->` は HTML のソースに出て、訪問者に見えるので使いません。
- **コメント**：今のコードが何をしていて、なぜそうなっているかだけを書きます。試行錯誤の経緯は書きません。書き方は 1-5 にまとめてあります。

### 1-5. コメントの書き方

コメントは、削りすぎずに「なぜ」を残します。コードを読めば、何をしているかは分かります。分からないのは、そうしている理由です。理由が書いていないと、後から触る人も AI も、不要に見える処理を消したり、よくある形に直したりして、元の狙いを壊します。

コメントの分だけ、ファイルは長くなります。それでも、理由を取り違えて作り直す手間の方がずっと大きいので、理由のコメントは惜しまずに書きます。

**残すコメント**

| 種類 | 例 | ある所 |
|---|---|---|
| そうしている理由 | メールアドレスが変わったときは、変わる前のアドレスにも送る。他人に書き換えられたときに本人が気付けるのは、変わる前のアドレスだけだから | `LoginProfileNotice` |
| あえてしていないこと | パスワードと同じ bcrypt は使わない。同じ値でも毎回違うハッシュ値になるので検索できず、記録の数だけログインが遅くなる | `TrustedDeviceManager` |
| 順番や場所に意味があること | `/members/csv` は `/members/{member}` より前に書く。後ろだと csv が会員の id として扱われる | `routes/web.php` |
| ほかの所とのつながり | 自分自身は削除できない。判断は `StaffPolicy` | `routes/web.php` |
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
- Blade では `{{-- --}}` で書きます（17章）。

**直すとき**

コードを直したら、そばのコメントも一緒に直します。理由が変わったのにコメントが元のままだと、無いよりも悪くなります。コメントに書いた理由がもう当てはまらないと気付いたら、コメントを消すか、今の理由に書き直します。

## 2. 新しいコーナーを作る手順

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

管理画面に「一覧・検索・登録・詳細・編集・削除」のあるコーナーを作る場合の手順です。一番近い実例（多くはニュースか会員）を写しながら進めます。

1. **テーブル**：マイグレーションを作る。業務で参照・削除するテーブルは `t_` を付ける（16章）。
2. **モデル**：`$table`・`$fillable`・`$casts`、リレーション、データ項目の仕様の定数（画像の横幅など）、表示用のアクセサ（ファイルの URL など）。
3. **コントローラー**：1-3 の並びで書く。
   - `use SearchableList;` と一覧の定数、`srchRules()`・`applyCustomSearch()`（3章）
   - `use FormFlow;` と `rules()`・`formValues()`・`saveValues()`（5章）
   - アップロードがあれば `use AjaxFileUpload;` と `UPLOAD_FILES`（7章）
   - 入口：`index`・`create`・`confirmForStore`・`backToCreate`・`store`・`show`・`edit`・`confirmForUpdate`・`backToEdit`・`update`・`destroy`
4. **ルート**：`routes/web.php` の管理画面のグループ（`auth:admin`・`auth.session` の内側）に足す。`/xxx/csv` のような固定の URL は、`/xxx/{id}` より前に書く。
5. **画面**：`resources/views/admin/<コーナー>/` に `index`・`_fields`・`create`・`edit`・`confirm`・`show`。
6. **入口のリンク**：管理画面の左のメニュー（`admin/_menu.blade.php`）に項目を足す。
7. **権限**：管理者だけの機能なら `acl.manager`、1件ごとに判断が要るなら Policy（13章）。
8. **確かめる**：一覧の検索と並び順、詳細から「一覧へ戻る」で検索条件とページが戻ること、登録・編集の確認画面と「戻る」、必須マーク、削除。

## 3. 一覧・検索（SearchableList）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Crud/SearchableList.php`　**実例**：`Admin\StaffController`・`Admin\MemberController`・`Admin\NewsController`・`NewsController`（訪問者向け）

### コントローラーに書くもの

```php
use SearchableList;

/** 一覧画面のルート名。セッションのキーにも、登録・更新・削除の後の戻り先にも使う */
private const INDEX_ROUTE = 'admin.members.index';
/** フリーワード（q）検索の対象カラム（使わなければ空配列） */
private const FREE_WORD_COLUMNS = ['name', 'kana'];
/** 1ページの件数 */
private const PER_PAGE = 20;
/** 並び順の選択肢（先頭が既定）。orderBy は [カラム, 方向] を書いた順に重ねる */
private const ORDER_OPTIONS = [
    'updated_desc' => ['label' => '更新日が新しい順', 'orderBy' => [['updated_at', 'desc'], ['id', 'desc']]],
    'updated_asc'  => ['label' => '更新日が古い順',   'orderBy' => [['updated_at', 'asc'],  ['id', 'asc']]],
];

public function index(Request $request): View|RedirectResponse
{
    $result = $this->listPageValues($request, Member::query());
    if ($result instanceof RedirectResponse) {
        return $result;   // ?back で戻ってきたとき
    }

    // 画面の変数の名前は、どのコーナーでも返ってきたキーと同じにする。画面を写して使い回せるようにするため。
    // まとめて渡さず1つずつ書くのは、画面で使える変数をここで見て分かるようにするため
    return view('admin.members.index', [
        'records' => $result['records'],
        'filters' => $result['filters'],
        'orderOptions' => $result['orderOptions'],
        'selectedOrder' => $result['selectedOrder'],
    ]);
}

/** 検索項目の検証ルール。キーはフォームの name で、そのまま検索するカラム名になる */
private function srchRules(): array
{
    return [
        'email' => ['nullable', 'string', 'max:255'],                       // 部分一致
        'prefecture' => ['nullable', 'array'],                               // 配列なら IN()
        'prefecture.*' => ['integer', Rule::in(code_keys('prefectures'))],  // 完全一致
    ];
}

/** カラムと直接比べられない項目（多対多、チェックボックスなど）。処理したら true */
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
- 削除済み（論理削除）の行は `<tr @class(['row-deleted' => $row->trashed()])>` で赤字になります（CSS はフレームワークの `global.css` にあります）。

ページ分けしない一覧（並び替えのためにすべて並べるカテゴリーなど）は、`SearchableList` を使わず `index()` で普通に取得します。

## 4. 詳細表示

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`Admin\MemberController::show()`・`Admin\NewsController::show()`

詳細画面も、入力欄と同じ `_fields.blade.php` を読み取り専用で表示します。値は `inputPageValues($record)` で作ります（モデルの今の値。アップロード項目も含む）。

```php
public function show(News $news): View
{
    $news->load('categories', 'attach');

    return view('admin.news.show', [
        'news' => $news,
        'input' => $this->inputPageValues($news),
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

**ファイル**：`packages/neobit/core/src/Crud/FormFlow.php`　**実例**：`Admin\NewsController`（確認画面あり）・`Admin\CategoryController`（確認画面なし）

### コントローラーに書くメソッド

| メソッド | 必須 | 中身 |
|---|---|---|
| `rules($record)` | ○ | 検証のルール。`$record` は新規なら null。使わなければ引数なしの `rules()` でよい |
| `saveValues($validated, $record)` | ○ | 保存する値（`列 => 値`）。ここで返した列だけを保存する。外した列は更新でもDBの値が残る。書き方は下の「保存する値」 |
| `formValues($record)` | 詳細・編集があれば○ | モデルの今の値から `$input` を作る（日付は `Y-m-d`、フラグは `'1'`/`'0'`、多対多は id の配列など、フォームの値の形に） |
| `defaultValues()` | | 新規登録の初期値 |
| `prepareInput($validated)` | | 検証の後、確認画面と保存の前の整形（例：`safe_html()`、郵便番号の整形） |
| `afterSave($record, $validated, $changedFields)` | | 保存の直後（例：多対多の `sync()`）。`$changedFields` は更新で値が変わった列で、`列の名前 => 変わる前の値`。使わなければ引数に書かなくてよい。新規登録と CSV 取り込みでは空 |
| `savedLogAction($created)` | | 操作ログ（22章）に残す操作の種類。登録・更新とは別の名前で残したいコーナーで書く（例：一斉メールの送信） |
| `beforeDelete($record)` | | 削除の直前（6章） |

アップロード項目は `saveValues()` に入れません（`commitUploads()` が保存します）。多対多は `afterSave()` で保存します。

### 保存する値

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

### 入口（確認画面あり）

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

### ルート

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

### 画面

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
- **`$input` には、`rules()` にある項目のキーが必ず入っています**。値の無い項目は null です。画面では `{{ $input['title'] }}` のように、`?? ''` を付けずに書けます。チェックの無いチェックボックスのように、送られてこない項目も同じです。一覧の検索条件の `$filters`（3章）も、検索の項目のキーが必ず入っています。`rules()` に無い項目（`password_confirmation` など）と、`FormFlow` を通さずに組み立てた `$input`（会員登録の `old()` など）には、`??` が要ります。
- **確認画面**：`_fields` を読み取り専用で表示し、「戻る」「登録する」の2つのフォームに `@include('neobit::_confirm_hidden', ['input' => $input])` で hidden を入れます。項目が増えても確認画面は直さずに済みます。
- **パスワード**：確認画面の「戻る」側は `'exclude' => ['password', 'password_confirmation']` で hidden から外します。`rules()` に無い `password_confirmation` は、確認画面の `$input` に足して持ち回ります。入力画面に戻したときは、`Arr::except(old(), [...])` で再表示しません（実例：スタッフ）。
- **必須マーク**：`requiredFields($record, ['password_confirmation'])` の第2引数で、`rules()` に `required` が無いが必須にしたい項目を足せます（`accepted` の同意チェックなど）。マークの HTML は `config/form.php` の `required_mark`（管理画面と訪問者向けで別）にあります。
- 入力画面では、必須マークの付いた欄に JavaScript が `required` 属性を付け、ブラウザの入力チェックの結果も同じエラー欄に出します（17章）。

### トランザクション

`saveData()` は、本体の保存・アップロードの確定・`afterSave()` の中の書き込みまでを1つのトランザクションで行います。`afterSave()` の中で自分でトランザクションを書く必要はありません。保存の処理だけを使いたいとき（CSV 取り込みなど）は、トランザクションの中で `saveValidated($record, $validated, $input)` を呼びます。

### 入力をそろえる（InputNormalizer）

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

### 子テーブル（ChildTables）

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
- **ファイル**：受け取りと一時ファイルの移し方はアップロードの部品（7章）のものを使います。今のファイルは、ファイル名が「その行の今の値」と一致するときだけ残すので、hidden を書き換えてほかの欄のファイルを指させることはできません。使わなくなったファイルは、トランザクションが確定した後に消えます。
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
- **CSV**：`csvColumns()` に組の横展開（9章。`'添付:*' => ['attach', 'group' => ['添付ファイル' => 'file_origin', '保存ファイル名' => 'file']]`）を書くと、書き出しにも取り込みにも使えます。書かなければ、CSV は親の項目だけで、子テーブルには触りません。
    - 取り込みは、CSV に組の列がある行だけ、子テーブルを CSV の内容で入れ直します。CSV には子テーブルの行の id が無いので、id を保つ組でも、取り込むと行の id が変わります。組の列が無い CSV では、子テーブルには触りません。
    - 組の項目の列が全部そろっているときだけ取り込み、足りなければ「取り込めない列なので、無視しました」の警告にします。ファイルの表示名の列だけは無くてよく、無ければ今のファイルの表示名を使います。
    - 「添付ファイル:2」「保存ファイル名:2」のような番号の組が全部空欄なら詰めます。行の数の上限と、組の項目の検証ルールは、画面と同じに効きます。
    - ファイル名は、1つだけの欄と同じく、今のファイルか、その記事の保存先にあるものだけ使えます。追加の行には書けません。
    - 確認画面の変更の内容には、組を1つの項目として「a.pdf（案内.pdf）、b.pdf」のように出します。ファイルの無い組は、項目の値を「／」でつなぎます。
- 組の子テーブルの変更は、操作ログの「変わった列」には入りません。

## 6. 削除

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`Admin\NewsController::destroy()`（物理削除）・`Admin\StaffController`（論理削除と取り消し）

```php
public function destroy(News $news): RedirectResponse
{
    $this->deleteData($news);   // beforeDelete() → アップロードファイルの削除 → 本体の削除（トランザクション）

    return redirect()->route(self::INDEX_ROUTE, ['back'])->with('status', 'ニュース記事を削除しました。');
}

/** 削除の直前に、関連テーブルを片付ける */
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

**ファイル**：`packages/neobit/core/src/Upload/AjaxFileUpload.php`・`UploadFilePath.php`　**実例**：`Admin\NewsController`（一覧用画像・添付ファイル・本文）、`ContactController`（添付ファイル1つ）

ファイルは選んだ時点で Ajax で一時ディレクトリ（`tmp/`）に送り、確認画面を経て保存するときに正式な保存先へ移します。コントローラー・画面の書き方は、アップロードの有無で変わらないようにしてあります。

### コントローラー

```php
use AjaxFileUpload;

/**
 * フィールド名 => ['type' => 種類, 'width' => 縮小する横幅]。
 * 「組の名前.*.列」は、子テーブル（5章）の中の欄
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

- **`UPLOAD_FILES` の書き方**（決まりは `UploadFieldDefinitions` にあり、誤りは最初に画面を開いたときに例外になります）

    | キー | 書き方 |
    |---|---|
    | `type` | 必ず書く。`UploadType::Image`（画像）か `UploadType::File`（添付ファイル） |
    | `width` | 画像なら必ず書く。この横幅を超えたら縮小する。添付ファイルには書かない |
    | `required` | 省略できる。`true` なら必須（下の「必須の欄」） |

    受け付ける拡張子は、種類ごとに `config/upload.php` の `image_types`・`attach_types` を使います。
- **必須の欄**：`'required' => true` の欄は、新しいファイル（一時ファイルが今ある）か、消していない今のファイルが無いと、確認画面へ進めず、保存もできません。今のファイルは、hidden の値をそのまま信じず、編集しているレコードの今の値と照らします。組の中の欄では、空でない行のどれにもファイルを求めます（今のファイルは、その行の今の値と照らします）。「少なくとも1つ」は、組の `'min' => 1` で決めます。
    - エラーの文は、通常の項目と同じ `validation.required` です。エラーは欄の名前（組の中の欄は `photos_image.0` のように行ごと）に付き、`_ajax_upload_block` が自分で `invalid-feedback` を出します。
    - 必須マークは `requiredFields()` が付けます。画面では `{!! $required['list_image'] ?? '' !!}` と書きます。
    - ルールは FormFlow の検証（`validatedInput()`）が足すので、コントローラーの `rules()` には書きません。CSV の取り込みには効きません。
    - 見本は、ニュースの一覧用画像です。
- **いくつでも足せるファイル**：子テーブル（5章）に、ファイルの項目だけの組を書き、`UPLOAD_FILES` に「組の名前.*.列」で書きます（例：`'attach.*.file'`。画面から送る名前は `attach_file`）。行の数の上限は組の `'max'` です。末尾が「.*」だけの書き方（`'attach.*'`）は、例外にして組の書き方を案内します。
- `$input` の組み立て（`inputPageValues()`・`confirmPageValues()`）と保存（`saveData()`）は FormFlow が自動でアップロードの処理を呼ぶので、入口の書き方は変わりません。
- ルートにアップロード先を1本足します：`Route::post('/news/ajax-upload', [AdminNewsController::class, 'uploadAjaxFile'])->name('news.ajaxUpload');`（訪問者向けは `throttle` を付ける）。
- **単数のフィールド**（`list_image`）：テーブルに `list_image`（保存ファイル名）と `list_image_origin`（元のファイル名）のカラムを作り、モデルの `$fillable` に入れます。
- **組の中のフィールド**（`attach.*.file`）：子テーブルに、列（`file`）と、列の名前に `_origin` を付けた表示名の列（`file_origin`。NULL可）を作り、組の名前と同じ HasMany リレーション（`News::attach()`）を用意します。
- 許可する拡張子・大きさの上限は、サイト全体の方針として `config/upload.php` で決めます。コーナーごとには変えません。画像の種類を変えたら、エディタでファイルを選ぶときの絞り込み（`wysiwyg_suneditor.js`・`wysiwyg_summernote.js` の `ACCEPT_IMAGE_TYPES`）も合わせます。CSV の取り込みの大きさの上限（`csv_max_kb`）も、ここにあります。
- **保存先**：モデルのクラス名と id から、保存先の規則が決めます（既定の例：`news/000/000012/xxxx.jpg`。規則の差し替えは下の「保存先の規則を変えるとき」）。1件分のファイルはフィールドに関係なく1つのディレクトリに入ります。表示の URL は `UploadFilePath::url(クラス, id, フィールド, ファイル名)` で作り、モデルのアクセサにしておきます（例：`News::list_image_url`、`NewsAttachment::url`）。

### 画面

```blade
{{-- 1つだけの欄 --}}
@include('neobit::_ajax_upload_block', [
    'model' => $model, 'input' => $input, 'field' => 'list_image',
    'readonly' => $readonly, 'uploadUrl' => $uploadUrl,
])
{{-- 組の中の欄は、組の行の部分ビューに、何行目か（$idx）を付けて置く（5章） --}}
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

### SunEditor

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

### エディタのスクリプトを変えるとき・選ぶとき

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

### 固定ページの URL

固定ページは、URL の名前（`t_pages.slug`）がそのままパスになります。`aboutus` なら `/aboutus` です。

- **ルートは、`routes/web.php` のいちばん最後に置きます**。ほかの全部の画面を先に当てて、どれにも当たらなかった1区切りの URL だけを受けるためです。画面を足すときは、このルートより上に書きます。
- **ほかの画面と同じ名前のページは作れません**。`login`・`news`・`admin` のような、ほかの画面の URL の最初の区切りと同じ名前は、検証で断ります（`NotReservedPathRule`）。ルートの一覧から調べるので、画面を足しても、直す所はありません。
- **名前の形**は、検証もルートも `SlugRule` のパターン（`SlugRule::NESTED_PATTERN`）を使います。食い違うと、登録できるのに開けないページができるためです。
- 後から足した画面の URL が、すでにあるページの名前と重なったときは、画面のほうが先に当たり、ページは開けなくなります。ページの名前を変えます。
- 表示名（元のファイル名）が空のときは、リンクの文字を「添付ファイル1」のようにします（`_ajax_upload_block` と訪問者向けのニュース詳細で実装済み）。

### ログインした人だけが見られるファイル（非公開）

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

### 保存先の規則を変えるとき

保存先のディレクトリは、`config/upload.php` の `directory_rule` に書いたクラスが決めます。既定は `DefaultUploadDirectory`（`news/000/000012`）です。プロジェクトで変えたいときは、`UploadDirectoryRule` を実装したクラスを書き、設定にその名前を書きます。メソッドの名前は `directory()` に決まっています。

```php
// app/Services/AppUploadDirectory.php：id をそのまま1階層にする例（news/12）
final class AppUploadDirectory implements UploadDirectoryRule
{
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        return Str::snake(class_basename($ownerClass)).'/'.(int) $ownerKey;
    }
}

// config/upload.php
'directory_rule' => App\Services\AppUploadDirectory::class,
```

- 受け取るのは、モデルのクラス名と id だけです。親の id だけが手元にある場面でも、レコードを読まずに使えるようにするためです。そのため、登録した日のようなレコードの中身は使えません。
- 1件ごとに専用のディレクトリにします。レコードを消すとき、フレームワークはディレクトリごと消すので、ほかの行のファイルが同じ所や下にあると、一緒に消えてしまいます。
- モデルごとに分けます。会員とニュースで同じ id があっても、ぶつからないようにするためです。
- `..` や先頭・末尾の `/` を含む値を返すと、`UploadFilePath` が例外にします。
- 運用を始めてから変えると、すでにあるファイルを読み出せなくなるので、変えません。どうしても変えるときは、ファイルを移すマイグレーションが要ります。

### 一時ファイル（tmp）

アップロードした直後のファイルは、公開・非公開に関係なく `"local"` ディスクの `tmp/` に置き、URL は `/uploads/tmp/{ファイル名}`（ルート `uploads.tmp`）です。アップロードしたときにセッションへファイル名を覚えておき、同じセッション（アップロードしたブラウザ）にだけ返します。保存するときに、持ち主のモデルのディスク（公開なら `"public"`）へ移します。保存されずに残った一時ファイルは、24時間を過ぎるとスケジューラーが消します（19章）。

### 消えるファイル

古いファイルは、保存の前後で「そのレコードがどこからも参照しなくなったファイル」だけを、トランザクションの確定後に消します。WYSIWYG の本文から外した画像も同じです（15章）。

## 8. 区分表・選択肢

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Code/CodeTable.php`・`packages/neobit/core/src/helpers.php`・`app/Enums/`・`code/*.csv`・`config/neobit.php` の `code_table_db`・`code_table_method`・`packages/neobit/core/src/Http/Controllers/CodeTableController.php`（項目見出し一覧）

区分表（値と名称の組）は、出どころに関係なく次のヘルパーで使います。

| ヘルパー | 使いどころ |
|---|---|
| `code_table('prefectures')` | 選択肢の一覧（値 => 名称）。画面のセレクト、CSV の一覧 |
| `code_keys('prefectures')` | 値の一覧。検証の `Rule::in(code_keys(...))` |
| `code_label('prefectures', $value, '（未設定）')` | 名称。画面の表示、メール。複数選ばれた値を配列で渡すと、名称を「、」でつなげる。区切りは4番目の引数に書く |
| `code_options('prefectures', $selected)` | プルダウンの `<option>` を並べた HTML。選ばれているものに `selected` が付く。2階層の配列を渡すと `<optgroup>` にまとめる |
| `hit($value, $key)` | 入力値が、選択肢の値に当たっているか。`@checked()`・`@selected()` に渡す |

出どころは4種類で、`CodeTable` がコード名から自動で探します（2つ以上にあればエラー）。

- **列挙型**（`App\Enums\<コード名の StudlyCase>`、`CodeTableEnum` を実装し `label()` を持つ）：プログラムが値によって動きを変えるもの（スタッフの権限 `StaffAcl` など）。モデルの `$casts` や判定では列挙型を直接使います。列挙型を探す名前空間は、`config/neobit.php` の `code_table_enum_namespaces` に書きます。既定は `App\Enums\` で、書いた順に探します。フレームワークの列挙型（`Neobit\Core\Enums\`）は、書かなくても最後に探します。
- **CSV**（`code/<コード名>.csv`、「値,名称」を1行1件、`#` はコメント）：選択肢として並べるだけのもの（都道府県など）。
- **DB**（`code_tables` テーブル。コード名を `config/neobit.php` の `code_table_db` に載せる）：CSVと同じ扱いのもの（性別・連絡方法など）を、管理画面の「項目見出し一覧」から書き換えたいとき。値・名称の扱い（数字だけの値は int、名称の `\n` は改行）も CSV と同じです。
- **メソッド**（コード名を `config/neobit.php` の `code_table_method` に、`[クラス, 静的メソッドの名前]` で載せる）：スタッフの一覧のように、ほかのテーブルから作るもの。メソッドが返した「値 => 名称」の配列を、そのまま使います。

DBのコード表を増やすときは、`config/neobit.php` の `code_table_db` に1行足し、必要なら `CodeSeeder` に初期データを書きます。

```php
// config/neobit.php
'code_table_db' => [
    'gender' => ['label' => '性別', 'fixed' => true],          // label：項目見出し一覧に出す名前
    'contact' => ['label' => '連絡方法', 'fixed' => false],
],
```

プログラムが特定のコード値を前提に処理するコード表は、`fixed` を true にします。そのコード表は、画面で並び替えと表示名の変更だけができ、コード値の変更・追加・削除はできません（サーバー側でも断ります）。

項目見出し一覧の画面は、フレームワークにあります（`CodeTableController`・`neobit::admin.code_tables.index`・`code_editor.js`）。ルートはアプリが書きます。管理者だけが入れるグループの中に、「頭.index」（GET）と「頭.update」（PATCH）の組で名前を付けます。コントローラーは、呼ばれたルートの名前から組のもう一方を作るので、頭（見本のサイトでは `admin.codes`）はアプリが決めてかまいません。

```php
// routes/web.php（管理者だけのグループの中）
Route::get('/codes', [CodeTableController::class, 'index'])->name('codes.index');
Route::patch('/codes', [CodeTableController::class, 'update'])->name('codes.update');
```

ほかのテーブルから作るコード表は、モデルに静的なメソッドを書き、`code_table_method` に載せます。画面やコントローラーで一覧を組み立てなくても、ほかのコード表と同じヘルパーで使えます。

```php
// config/neobit.php
'code_table_method' => [
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
- 操作ログの操作のコード表（`operation_log_action`）は、フレームワークが自分で持つメソッドのコード表です。`config/neobit.php` の `operation_log_actions` の列挙型を、書いた順に合わせて作ります。設定の `code_table_method` に書く必要はありません。

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

### 選択肢を画面に書く

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
               class="form-check-input" @checked(hit($input['hobby'], $k)){{ $disabled }}>
        <label for="hobby_{{ $k }}" class="form-check-label">{{ $name }}</label>
    </div>
@endforeach

{{-- 一覧や詳細に、選ばれたものの名前を並べる --}}
{{ code_label('hobby', $row->hobby) }}
```

- **`hit($value, $key)`** は、入力値が1つなら同じ値か、配列ならその中にあるかを返します。フォームから届いた文字の `'1'` と、コード表の数値の `1` は、同じ値として扱います。入力値が空なら、当たりません。型をそろえる `(int)` や `in_array()` を、画面に書かなくて済みます。
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
- 選択肢ごとに `data-` 属性を付けるなど、`<option>` に手を入れたいときは、`@foreach` と `@selected(hit(...))` で書きます。

Blade には `\App\Enums\...` を書かず、`code_table()` 系で書きます。画面の説明文のような文面は、列挙型に持たせず Blade に書きます。

## 9. CSV ダウンロード（CsvDownload）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Csv/CsvDownload.php`・`CsvColumnSet.php`（項目の定義の書き方）・`CsvWriter.php`（共通の書き出し方）　**実例**：`Admin\MemberController`・`Admin\NewsController`・`Admin\BulkMailController::failedCsv()`

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

/** 見出し => 値の場所。書いた順に列が並ぶ */
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

- 書き方の全体（複数の値の「、」区切り、連番の横展開 `'添付ファイル:*' => 'attach.*.file_origin'`、組の横展開 `'添付:*' => ['attach', 'group' => [...]]` など）は `CsvColumnSet.php` の冒頭にあります。
- `downloadCsv()` に渡したクエリのデータを出力します。`SearchableList` を使っていれば、一覧の検索条件と並び順が自動的に反映されます。
- ルート：`Route::get('/members/csv', ...)->name('members.csv')`（`/members/{member}` より前）。一覧画面に「CSVダウンロード」ボタン。
- ダウンロードのたびに `csv_download_logs` に記録します。

### モデルではないデータを書き出す（CsvWriter）

文字コード・BOM・数式の無害化・`fputcsv()` の書き方と、ダウンロードの記録は、CSV の共通の部品 `CsvWriter` が持っています。`downloadCsv()` も、モデルのクエリから行を作って、これを呼んでいます。モデルではないデータ（ジョブの中の宛先、集計の結果など）は、行の配列を作って直接呼びます。

```php
// ブラウザにダウンロードさせる。ダウンロードの記録と操作ログを残す
return CsvWriter::download(
    name: '一斉メールの送れなかった宛先',          // ファイル名「名前_年月日_時分.csv」・記録の名前
    rows: $rows,                                  // 1行 = 値の配列。ジェネレーターなら少しずつ書き出す
    encoding: CsvEncoding::Utf8Bom,               // Utf8Bom か Sjis
    headings: null,                               // 見出しの行。null なら出さない
    escapeFormula: true,                          // Excel の数式の無害化
    conditions: ['bulk_mail_id' => $bulkMail->id], // ダウンロードの記録に残す条件
);

// CSV の中身を文字列で受け取る。保存先やメールへの添付は、呼んだ側が決める
$csv = CsvWriter::fetch(rows: $rows, encoding: CsvEncoding::Utf8Bom, headings: ['氏名', 'メールアドレス'], escapeFormula: true);
Storage::disk('local')->put('exports/members.csv', $csv);
```

- `fetch()` は全件をメモリに組み立てるので、大きな CSV には向きません。人がダウンロードしたことにはならないので、ダウンロードの記録は残しません。個人情報を書き出すときは、記録を残すかを呼んだ側で決めます。
- 引数は、`downloadCsv()` と同じく既定の値を持たせず、名前付きで全部書きます。

## 10. CSV 取り込み（CsvImport）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Csv/CsvImport.php` ほか（冒頭のコメント）、設計の詳細は「CSV取り込みの設計」（`docs/csv-import-spec.md`）　**実例**：`Admin\MemberController`（更新だけ）・`Admin\NewsController`（アップロード項目と子テーブル（添付ファイル）あり。一覧用画像が必須なので、追加はできない）

項目の定義は、ダウンロードと同じ `csvColumns()` を使います。文字コードの判定は、取り込みの設定が `encoding: null` なら自動判別です。ダウンロードした CSV を直して、そのまま取り込めます。検証・保存は画面からの登録・更新（FormFlow）と同じ処理を通ります。

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
- セルの値は、画面からの入力と同じく、検証の前に全角と半角をそろえます。コントローラーの `RAW_INPUT_FIELDS` に書いた項目の列は、そろえません（5章の「入力をそろえる」）。
- `labelColumn` を指定すると、確認画面のエラー・警告と実行を中止したときのメッセージで、「2行目（山田太郎）」のように行の見分けになる値を添えます（10文字を超えたら「...」で縮める。空欄なら添えない）。
- **子テーブル**：`csvColumns()` に組の横展開を書けば、子テーブルも取り込めます（5章「子テーブル」の CSV）。
- **アップロードの欄の必須**：`UPLOAD_FILES` の `'required' => true` の欄は、取り込みでも確かめます。ファイル名の列があればファイル名が、無ければ今のファイルが要ります。追加の行にはファイル名を書けないので、必須の欄があるコーナーでは、CSV で追加できません。子テーブルの中の必須の欄は、組の列を取り込むときだけ、どの行にもファイルを求めます。
- **処理を組み込む**：`validateCsvRows()`（行をまたいだチェック）・`afterCsvImportRow()`（1行保存するたび）・`afterCsvImport()`（確定後の通知など）・`processCsvRows()`（処理だけのモード）。
- 取り込むたびに `csv_import_logs` に記録します。
- 自分の画面を持つ機能が CSV の読み込みと検証だけを使うときは、`CsvReader` を `use` します。項目の定義と行の検証ルールを引数で渡し、画面と流れは自分で作ります（設計 15）。

## 11. メール送信（MailTemplate・TemplatedMail）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Mail/MailTemplate.php`・`MailTemplateParser.php`・`packages/neobit/core/src/Mail/TemplatedMail.php`・`resources/mail-templates/`　**実例**：`ContactController::sendStaffNotification()`・`LoginVerificationCode`

送信元・宛先・件名・本文は、テンプレートファイルがすべて決めます。呼び出し側は変数と添付ファイルを渡すだけです。

テンプレートは、アプリの `resources/mail-templates/` に置きます。フレームワークが送るメール（確認コード・パスワードの変更・登録情報の変更・招待・エラーの通知・操作ログの報告）は、既定のテンプレートが `packages/neobit/core/resources/mail-templates/` にあるので、置かなくても送れます。文面を変えたいときは、アプリの `resources/mail-templates/` に同じ名前のファイルを置くと、そちらが使われます。`php artisan vendor:publish --tag=neobit-mail-templates` で、既定のテンプレートをアプリへ写せます。

ログインする人へのメールは、種類の名前（`member`・`staff` など）を頭に付けた名前で探し、無ければ付けない名前で探します。探す順は次のとおりです。

| 順 | 置き場所 | 名前 | 使いどころ |
|---|---|---|---|
| 1 | アプリ | `member_verification_code` | その種類だけの文面 |
| 2 | アプリ | `verification_code` | 全部の種類に共通の文面でよいとき |
| 3 | フレームワーク | `verification_code` | 既定の文面 |

`TemplatedMail` の名前に配列を渡すと、探す名前の一覧になります。先に書いた名前を優先します（例：`new TemplatedMail(['member_verification_code', 'verification_code'], $vars)`）。

テンプレートがどこにも無いときと、見出しの行の書き方が誤っているときは、例外（`MailTemplateException`）になります。メールを送る所は、SMTP につながらないなどの送信の失敗を受け止めてログに残し、処理を続けますが、この例外だけは受け止めずにそのまま投げ直します。画面はエラーになり、コマンドは止まります。開発中の誤りで、待っても直らないので、その場で気付けるようにするためです。メールを送る処理を足すときも、`catch (\Throwable $e)` の前に `catch (MailTemplateException $e) { throw $e; }` を書きます。

既定のテンプレートの件名は、頭に `【サイトの名前】` が付きます。`.env` の `SITE_NAME` が空のサイトでは `【】` になるので、アプリの側にテンプレートを置いて件名を書き換えます。

```text
{{-- resources/mail-templates/contact_staff.blade.php --}}
FROM: {!! $from_name !!} <{!! $from_mail !!}>
TO: {!! $staff_mail !!}
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

- 決まった見出し行は `FROM`・`TO`・`CC`・`BCC`・`REPLY_TO`・`SUBJECT` です。最初の空行の後が本文です。
    - `FROM` は、`名前 <アドレス>` か、アドレスだけを書きます。名前の符号化は、送るときに自動で行います。
    - `TO`・`CC`・`BCC`・`REPLY_TO` は、アドレスだけを書きます。カンマ区切りで複数書けます。名前は書けません。
- そのほかの見出し行は、メールのヘッダーとして付きます。名前は、`_` を `-` に変えて、語の頭だけを大文字にします。`LIST_UNSUBSCRIBE: <...>` と書くと、`List-Unsubscribe: <...>` になります（実例：`bulk_mail.blade.php`）。値が空の行は付けません。
    - `FROM_MAIL`・`TO_MAIL` のように決まった見出しと紛らわしい名前と、`CONTENT_TYPE` のようにメールの形を壊す名前は、例外にして送信を止めます。
    - テンプレートでは書けないヘッダーは、`TemplatedMail` の4つ目の引数に、ヘッダーの名前 => 値の配列で渡せます。
- 同じ名前に `_html` を付けたファイル（`contact_staff_html.blade.php`、本文だけ）を置くと、HTML とテキストのマルチパートになります。
- テキスト版は `{!! !!}`、HTML 版の利用者の入力値は `{{ }}` で埋め込みます。条件分岐や繰り返しは Blade の `@if`・`@foreach` が使えます。
- `Mail::to()` は使わず `Mail::send()` で送ります（宛先はテンプレートが持つため）。
- 保存と一緒に送るメールは、保存のトランザクションが確定した後に送り、送信に失敗しても保存は取り消さず、ログに残します（実例：お問い合わせ）。

### 届かなかったメールの戻り先（Return-Path）

送ったメールが「送信済み」になるのは、SMTP サーバー（レンタルサーバー）が受け取った時点です。相手に届いたかではありません。アカウントが無い（User unknown）などで届かなかったときは、数分〜数日後に、エラーのメール（バウンス）が戻り先へ届きます。

```dotenv
# .env。すべてのメールの Return-Path と、SMTP の封筒の送信元（MAIL FROM）になる
MAIL_RETURN_PATH=bounce@サイトのドメイン
```

- 空なら付けず、エラーのメールは送信元（`MAIL_FROM_ADDRESS`）に戻ります。
- 一斉メールだけでなく、サイトが送るすべてのメールに付きます（Laravel の `config/mail.php` の `return_path`）。テンプレートの見出しの行には `RETURN_PATH` を書けません。
- 送信元と同じドメインのアドレスにします。SPF は封筒の送信元のドメインで確かめるので、違うドメインにすると迷惑メールと判定されやすくなります。
- 戻ってきたエラーのメールは、今は人が見て、宛先を直します。サイトが自動で読み取る仕組みはありません。
- **実サーバーでの試し方**
  1. `.env` に `MAIL_RETURN_PATH` を書き、`php artisan config:cache` をやり直します。
  2. アカウントが無いアドレス（ドメインは正しく、`nouser-test-0001@gmail.com` のように無さそうなもの）1件の CSV で、一斉メールを送ります。
  3. 数分のうちに、エラーのメールが戻り先のメールボックスに届くかを見ます。
  4. 届かずに送信元のメールボックスに戻ってきたときは、レンタルサーバーが封筒の送信元を書き換えています。その SMTP では、戻り先を変えられません。

## 12. 訪問者向けのフォーム（お問い合わせ）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：`ContactController`・`resources/views/contact/`

管理画面の登録と同じく FormFlow で作ります。違いは次の4つです。

- **確認画面を経由した1回だけの送信**：確認画面を出すたびに使い捨ての合言葉（`confirm_token`）を発行してセッションと hidden に持たせ、送信のときに一致を確かめ、保存できたら消します。合言葉は `$input` に混ぜず、送信フォームにだけ埋めます。
- **連続送信の制限**：送信とアップロードのルートに `throttle:回数,分,識別名` を付けます。識別名はルートごとに重ならない名前にします（省略すると別のルートとカウンターを共有してしまいます）。
- **保存の後の処理**：保存（`saveData()`）の後に通知メールを送り、完了画面へリダイレクトします。
- **スパム対策**：下の「スパム対策（SpamGuard）」。

### スパム対策（SpamGuard）

**ファイル**：`packages/neobit/core/src/Security/SpamGuard.php`（冒頭のコメント）・`packages/neobit/core/resources/views/_spam_guard.blade.php`・`packages/neobit/core/src/Enums/SpamCheckResult.php`　**実例**：`ContactController::confirmForStore()`

人に手間をかけさせない3つの仕組みを組み合わせます。画像を選ばせる問題は出しません。

| 仕組み | 中身 | 引っかかったとき |
|---|---|---|
| ハニーポット | 人には見えない入力欄。入力があれば機械とみなす | `Bot` |
| 送信までの時間 | 入力画面を表示した時刻を暗号化して hidden に持たせ、送信までが短すぎれば機械とみなす | `Bot` |
| Cloudflare Turnstile | 入力画面の枠がブラウザの裏側で判定し、送られてきたトークンをサーバーから Cloudflare に問い合わせる | `Failed` |

```blade
{{-- 入力画面の <form> の中 --}}
@include('neobit::_spam_guard')
```

```php
/** コントローラー。入力画面から送信を受け取るところ（確認画面を表示する処理）で呼ぶ */
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
- **鍵**：`.env` の `TURNSTILE_SITE_KEY`・`TURNSTILE_SECRET_KEY`（`config/services.php`）。本番は Cloudflare のダッシュボードで、サイトのドメインごとに発行します。手元の開発では、Cloudflare が公開しているテスト用の鍵（必ず通る）を使います（`.env.example` に書いてあります）。サイト自体を Cloudflare に載せる必要はありません。
- **回数の制限**：確認画面へ進むたびに Cloudflare に問い合わせるので、そのルートにも `throttle` を付けます（`throttle:20,1,contact-confirm`）。
- **プライバシーポリシー**：Turnstile は、利用者のブラウザの情報を Cloudflare に送ります。外部送信規律（電気通信事業法）に合わせて、外部のサービスに情報を送っていることを、プライバシーポリシーなどで示しておきます。

必須マークは `config/form.php` の `public` が使われます。

## 13. 権限

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Policies/StaffPolicy.php`・`app/Http/Middleware/EnsureStaffIsManager.php`　**実例**：スタッフ

- **URL 単位で判断するもの**（一覧・新規登録など、管理者だけが使える機能）：ルートを `Route::middleware('acl.manager')` のグループに入れます。
- **1件ごとの判断**（本人か管理者なら編集できる、自分自身は削除できない、削除済みは編集できない、など）：`app/Policies/<モデル名>Policy.php` にメソッドを書くと、名前の対応で自動的に使われます。同じ判断を3か所から使います。
  - ルート：`->middleware('can:update,staff')`（false なら 403）
  - 画面：`@can('update', $staff)`（ボタンやリンクの出し分け）
  - コントローラー：`Auth::guard('admin')->user()->can('updateAcl', $staff ?? Staff::class)`
- 権限によって入力欄を出さない項目は、`rules()` で `'exclude'` にし、`saveValues()` の `$saveFieldNames` からも外します（外さないと NULL で上書きしてしまう。実例：スタッフの権限）。
- 管理者かどうかの判定は `Staff::isManager()` です。

## 14. ログイン認証

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**実例**：会員は `AuthSessionController`・`PasswordResetController`・`AuthPasswordController`・`AuthRegisteredMemberController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\StaffController`。パスワードが合った後の流れは、どのログインも `TwoFactorLogin` トレイト

認証の画面は遷移が独特なので、FormFlow には載せず、それぞれのコントローラーで書いています。新しく作るより、実例を写して直す方が安全です。

### ガードと画面の区分（サンプルサイトの例）

| | 個人会員 | 企業会員 | 管理画面 |
|---|---|---|---|
| ガード | `web`（`Member`、`t_members`） | `company`（`CompanyUser`、`t_company_users`） | `admin`（`Staff`、`t_staffs`） |
| ログインに使う値 | メールアドレス | 企業ID と、担当者ID またはメールアドレス | ログインID |
| ログインが要る画面 | `Route::middleware(['auth:web', 'auth.session'])` | `Route::middleware(['auth:company', 'auth.session', 'company.approved'])` | `Route::middleware(['auth:admin', 'auth.session'])` |
| ログイン前だけの画面 | `guest`（`guest:web` と同じ） | `guest:company` | `guest:admin` |
| 2段階目の方式 | メールの確認コード | 同じ | 認証アプリ（TOTP） |
| バックアップコード | 希望する人が発行 | 同じ | 同じ |
| 2段階目の省略 | 「この端末を信頼する」 | 同じ | 同じ |

企業会員の `company.approved` は、運営が承認済みの企業の担当者だけを通します。申請中・停止の企業の担当者は、ログイン画面へ戻します（23章）。

2段階目の方式は、モデルの定数で選びます（下の「2段階認証」）。表は、見本のサイトでの値です。省略の仕組み（`TrustedDeviceManager::for()`）は、どのログインでも同じです。

未ログインのときの行き先・ログイン済みでゲスト専用画面に来たときの行き先は、`bootstrap/app.php` でルート名（`admin.*`・`company.*` かどうか）から振り分けています。

個人会員と企業会員は、同じ共通部品を、会員のモデルを渡して使い分けます。企業会員だけの仕組み（企業と担当者、承認、招待、移したデータの初回のログイン）と、会員の種類の足し方・外し方は、23章にまとめてあります。

### ログインの流れ（会員・管理とも同じ形）

1. ID・パスワードを `Auth::guard(...)->validate()` で確かめる（まだログインはしない）。ここまではコントローラーに書き、合ったら `continueAfterPassword()` を呼ぶ。入力された値で、どの列から人を探すかは、モデルの定数 `LOGIN_ID_TYPE` で決まる（`loginIdCondition()`。23章）。
2. 2段階目の方式が「なし」なら、そのままログインを確定する。
3. 信頼済み端末なら、そのままログインを確定する。
4. そうでなければ「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ。2段階目の画面は `guest` にも `auth` にも入れず、トレイトがセッションで守る。
5. 2段階目が通ったら、ログインを確定する。
6. ログインが必要な画面から来た場合はその画面へ、そうでなければ既定の画面（マイページ・管理画面TOP）へ移す。移動先は `LoginRedirect::for(Member::class, route('mypage'))` で決める（2・3・5とパスキーで通ったとき）。2つ目の引数が、既定の画面。

2から5は `TwoFactorLogin` トレイトが受け持ちます。ログインの確定（`Auth::login()` と `session()->regenerate()`）は、どの道を通ってもトレイトの中の1か所で行います。

未ログインでログインの要る画面を開くと、ログインの後に、その画面へ戻します。Laravel 標準の `redirect()->intended()` は、戻り先を1つしか記憶できず、複数の種類のログイン画面を同じブラウザで使うと、正しい戻り先を判別できません。そこで `LoginRedirect` が、戻り先をログインの種類ごとに別々に記憶します。

開こうとした画面がどの種類の画面かは、そのルートの `auth` のミドルウェアから読みます。ログインの必要なルートには、`auth:web`・`auth:admin` のように、ガードの名前を省かずに書きます。

### 2段階認証（TwoFactorLogin・TwoFactorChallenge）

**ファイル**：`packages/neobit/core/src/Enums/TwoFactorMethod.php`、`packages/neobit/core/src/Auth/LoginAccount.php`・`HasTwoFactor.php`・`TwoFactorLogin.php`・`TwoFactorChallenge.php`・`TwoFactorManagement.php`・`TwoFactorAuthenticator.php`・`BackupCodeGenerator.php`・`TrustedDeviceManager.php`、`packages/neobit/core/resources/views/_two_factor.blade.php`・`_two_factor_manage.blade.php`・`_two_factor_backup_codes.blade.php`・`_two_factor_reset.blade.php`、設計の詳細は「2段階認証の設計」（`docs/two-factor-spec.md`）　**実例**：ログインは `AuthSessionController`・`Company\AuthSessionController`・`Admin\AuthSessionController`、本人による管理は `MypageController`・`Company\MypageController`・`Admin\StaffController`、運営による解除は `Admin\MemberController`・`Admin\CompanyUserController`・`Admin\StaffController`

ログインの2段階目の方式は、ログインするモデルの定数で選びます。部品は、相手が会員かスタッフかを知りません。

| 方式（`TwoFactorMethod`） | 2段階目 |
|---|---|
| `Email` | メールで送る確認コード（6桁、10分有効） |
| `Totp` | 認証アプリのコード。未登録の人は、パスワードの後の画面で QR コードを読み取って登録する |
| `None` | 求めない。パスワードが合ったら、そのままログインを確定する |

```php
// モデル。ログインする人の共通の型（LoginAccount）を実装する。会員もスタッフも同じ
class Staff extends Authenticatable implements LoginAccount, PasskeyUser
{
    use HasTwoFactor;

    /** ログインの2段階目の方式 */
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Totp;

    /** バックアップコードを使うか。使うなら、希望する人が自分の画面で発行する */
    public const BACKUP_CODES = true;
}

// ログインのコントローラー
use TwoFactorLogin;
private const LOGIN_ACCOUNT = Staff::class;                    // ガード・ルート・2段階目の方式は、ここから決まる
private const TWO_FACTOR_VIEW = 'admin.auth.login-verify';     // 2段階目の画面
private function loginRedirectUrl(): string                    // ログインが確定した後の移動先
{
    return LoginRedirect::for(self::LOGIN_ACCOUNT, route('admin.dashboard'));
}

// パスワードが合った後は、トレイトに任せる
return $this->continueAfterPassword($request, $staff, $request->boolean('remember'));
```

| アクション | ルート名（管理は `admin.`、企業会員は `company.` が付く） |
|---|---|
| `showVerification()`（GET） | `login.verify` |
| `verifyCode()`（POST） | `login.verify.confirm` |
| `resendCode()`（POST。メールの方式だけ。`throttle` を付ける） | `login.verify.resend` |

- **定数に既定の値はありません**。`TWO_FACTOR_METHOD` と `BACKUP_CODES` を書いていないモデルでログインすると、エラーになります。書き忘れで「なし」になるのを防ぐためです。
- **2段階目の画面のビューは、`@include('neobit::_two_factor')` するだけにします**。中身は方式と状態（コードの入力か、QR コードの登録か）で出し分けます。
- **認証アプリの登録は、共通のテーブル `two_factor_secrets` に持ちます**。バックアップコード（`two_factor_backup_codes`）・信頼済み端末（`trusted_devices`）と同じ扱いで、業務のテーブルには列を足しません。読み書きは、モデルの `hasTwoFactorConfirmed()`・`twoFactorConfirmedAt()`・`registerTwoFactor($secret)`・`resetTwoFactor()` で行います。
- **本人をもう一度確かめるときは `TwoFactorChallenge` を使います**。`send()` で準備（メールの方式なら確認コードを送る）、`verify()` で照合、`throttle()` で試行制限です。方式での場合分けは、部品の中にあります。
- **メールの方式のモデルは、メールアドレスを必須にします**。スタッフのメールアドレスは任意なので、スタッフを `Email` にするときは、検証のルールを必須に変えます。
- **稼働中のサイトで方式を変えたら、その種類の信頼済み端末の記録を消します**（`trusted_devices` の、`authenticatable_type` がその種類の行）。
- **スタッフ向けの確認コードのメールは `staff_verification_code` です**。スタッフを `Email` にしたときに使います。スタッフの登録・編集の画面では、方式が `Email` の間、メールアドレスが必須になります。

#### バックアップコード

バックアップコードは、2段階目を通れないときに、その代わりに1回ずつ使える緊急用のコードです。認証アプリの入った端末が手元に無いときと、確認コードのメールが届かないときに使います。

- **使うかどうかは、モデルの定数 `BACKUP_CODES` で選びます**。2段階目を通る手段が1つ増えるので、種類ごとに選べるようにしています。方式が `None` のモデルでは、値に関係なく使いません。
- **自動では発行しません**。希望する人が、ログイン後の自分の画面（下の「本人による管理」）で発行します。
- **発行の前に、その人の方式で本人を確かめます**。`Totp` は今の認証アプリのコード、`Email` はメールで送る確認コードです。
- **入力欄は、発行してある人にだけ出ます**。ログインの2段階目と、解除の確認の画面です。
- **`false` にしたモデルでは、2段階目を通れなくなった人は運営が解除します**。画面から解除できる人がいないときは、コマンドを使います（下の「サーバーからの解除」）。

#### 本人による管理（TwoFactorManagement）

ログイン中の本人が、バックアップコードを発行し、認証アプリの登録を解除する画面です。入口は、方式が `Totp` のときと、バックアップコードを使うときに出ます。

```php
// 本人の情報を扱うコントローラー
use TwoFactorManagement;
private const TWO_FACTOR_GUARD = 'web';                                         // ログイン中の本人を取るガード
private const TWO_FACTOR_MANAGE_VIEW = 'mypage.two-factor';                     // 管理の画面。_two_factor_manage を @include する
private const TWO_FACTOR_BACKUP_CODES_VIEW = 'mypage.two-factor-backup-codes';  // バックアップコードを1回だけ見せる画面
private function twoFactorManageUrl(LoginAccount $owner): string                // 操作の後の戻り先
{
    return route('mypage.twoFactor');
}
private function twoFactorHomeUrl(): string                                     // バックアップコードを見た後の移動先
{
    return route('mypage');
}
```

| アクション | ルート名（管理は `admin.`、企業会員は `company.` が付く） |
|---|---|
| `twoFactorIndex()`（GET。会員だけ。スタッフは自分の詳細の画面に出している） | `mypage.twoFactor` |
| `showNewBackupCodes()`（GET） | `twoFactor.backupCodes` |
| `sendBackupCodesCode()`（POST。メールの方式だけ。`throttle` を付ける） | `twoFactor.backupCodes.code` |
| `regenerateBackupCodes()`（POST） | `twoFactor.regenerateBackupCodes` |
| `selfReset()`（DELETE） | `twoFactor.selfReset` |

- **発行と解除の前に、本人をもう一度確かめます**。ログイン中の画面を他人に使われたときに、被害を広げないためです。
- **解除は、バックアップコードでもできます**。端末を無くした人が、発行してあったバックアップコードでログインした後に、別のバックアップコードで自分で解除できるようにするためです。解除は操作ログに残ります。
- **解除すると、バックアップコード・信頼済み端末・パスキーも消えます**（`$account->resetTwoFactor()`）。パスキーでのログインは2段階目を求めないので、残すと登録し直さないまま使えてしまうためです。
- **端末を無くし、バックアップコードも無い人は、運営が解除します**。管理画面の詳細に `@include('neobit::_two_factor_reset', ['twoFactorAccount' => $member, 'twoFactorResetUrl' => route(...)])` を置き、コントローラーで `$member->resetTwoFactor()` を呼びます。ボタンは、方式が `Totp` で登録が済んでいるときだけ出ます。

#### サーバーからの解除（app:reset-two-factor）

画面から解除できる人がいないときに、サーバーで実行します。スタッフが1人だけのサイトで、その人が端末を無くしたときなどです。

```
php artisan app:reset-two-factor staff yamada
```

1つ目は種類の名前（`staff`・`member`・`company_user`）、2つ目はログインに使う値です。企業の担当者は `企業ID/ログインID` と書きます。対象の人を出して、確かめてから解除します。消えるものは、画面からの解除と同じです。

#### テストで方式を差し替える

方式はモデルの定数なので、実行中には変えられません。テストでは、トレイト `Tests\Support\FakesTwoFactorMethod` を使って差し替えます。テストが終わると、元に戻ります。

```php
use FakesTwoFactorMethod;

$this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
$this->useBackupCodes(Staff::class, false);    // バックアップコードを使うかも、同じように差し替える
```

### ログインする人の共通の型（LoginAccount）

ログインするモデル（`Member`・`CompanyUser`・`Staff`）は、共通の型 `Neobit\Core\Auth\LoginAccount` を実装し、トレイト `IsLoginAccount` を付けます。認証の共通部品は、相手を `Member` や `Staff` の名指しではなく、この型で受け取ります。ガード・ルート・メールのテンプレート・信頼済み端末の Cookie の名前は、モデルの「種類の名前」（モデルの短い名前。`member`・`company_user`・`staff`）から、決まりのとおりに作ります。名前の決まりと、モデル・コントローラーに書くものは、23章にあります。

### 古い方式のパスワード（LegacyPasswordUserProvider）

**ファイル**：`packages/neobit/core/src/Auth/LegacyPasswordUserProvider.php`（冒頭のコメント）・`packages/neobit/core/src/Auth/LegacyPassword.php`・`packages/neobit/core/src/Auth/Legacy/`・`config/login.php`

既存のシステムから会員を移したサイトだけで使います。既存のシステムのパスワードは、MD5 や SHA-1 のような古い方式のハッシュ値で持っていることが多く、元のパスワードには戻せません。古いハッシュ値のまま移し、本人が次にログインしたときに、今の方式（bcrypt）に置き換えます。会員にパスワードを決め直してもらう必要はありません。

**移すとき**

古いハッシュ値を、今の方式でもう一度ハッシュ値にして、`legacy_password` の列に入れます。`password` の列は空にします。古い方式のハッシュ値は短い時間で破られるので、新しいシステムの DB には、そのままでは置きません。

```php
$member->forceFill([
    'password' => null,
    'legacy_password' => LegacyPasswordUserProvider::wrap($oldHash),   // $oldHash は既存のシステムのハッシュ値
])->save();
```

**設定**（`config/login.php`）

```php
'member' => [
    'legacy_passwords' => [
        Neobit\Core\Auth\Legacy\Md5Password::class,
        Neobit\Core\Auth\Legacy\Sha1Password::class,
    ],
],
```

- 書いた順に試します。途中で方式を変えたシステムのデータも、並べておけば通ったものを採ります。
- ソルトの無い MD5・SHA-1・SHA-256 は、`Neobit\Core\Auth\Legacy` に用意してあります。
- ソルトを付ける、何度も繰り返す、といったそのサイトだけの方式は、`LegacyPassword` を実装したクラスを書いて、設定に足します。メソッドは「入力されたパスワードと会員を受け取って、古い方式のハッシュ値を返す」の1つです。会員を受け取るので、会員ごとのソルトの列も読めます。
- **新しく始めるサイトは、空のままにします**。古い方式は、何も働きません。

**決まりごと**

- 照合は、Laravel がパスワードを確かめる所（`config/auth.php` の会員のプロバイダー。`driver` が `eloquent-legacy`）に入れてあります。ログインのコントローラーには、何も書きません。
- 通ったら、入力されたパスワードを今の方式で `password` に保存し、`legacy_password` を消します。次からは、標準の照合です。
- 今の方式のパスワードを持っている会員は、`legacy_password` に値があっても、今のパスワードだけで照合します。
- パスワードを変えるか再設定すると、`legacy_password` は消えます（`PasswordChange`）。
- `legacy_password` が残っている会員は、まだ一度もログインしていない会員です。件数は `Member::whereNotNull('legacy_password')->count()` で数えられます。
- `LegacyPasswordUserProvider::wrap()` は、1件ごとに時間がかかります（今の方式が、わざと時間をかける作りのため）。件数が多いときは、データの取り込みとは別に進めます。
- 包んでおくことは、既に漏れているパスワードには効きません。新しいシステムの DB が漏れたときに、古いハッシュ値から短い時間でパスワードを割り出されることを防ぐ、という備えです。

### ログアウト（LoginSession）

ログアウトは `LoginSession::logout($request, ガードの名前)` で行います。ログインのコントローラーは、`TwoFactorLogin` トレイトの `logoutAccount()` を呼びます。

- **そのガードのログインだけを終わらせます**。同じブラウザのほかのガードのログイン（会員と管理画面）は残ります。運営のスタッフが、会員の側の画面を確かめながら管理画面で操作する、といった使い方のためです。
- **ログアウトした人がセッションに残したものは、全部消えます**（検索条件、確認コードの仮置きなど）。残したほかのガードの側でも、検索条件などは消えます。
- `$request->session()->invalidate()` を直接呼ぶと、ほかのガードのログインも切れます。ログアウトの処理には使いません。

### 試行制限（LoginThrottle）

```php
/** コントローラーの定数。値はほかのコントローラーと重ならない名前にする */
private const THROTTLE_SCOPE = 'member-login';

$throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['email']);
if ($throttle->isBlocked()) {
    throw ValidationException::withMessages(['email' => $throttle->blockedMessage('ログイン')]);
}
// 失敗したら $throttle->hit()、成功したら $throttle->clear()
```

失敗した回数だけを、IP 単位とアカウント単位の2本で数えます。使われている scope は、コントローラーを `THROTTLE_SCOPE` で検索すると一覧できます。メール送信を伴うルート（確認コードの再送など）には、ルートの `throttle` を付けます。

### 確認コード（LoginVerificationCode）

ログインする人の種類は、作るときにモデルで渡します（`new LoginVerificationCode(Member::class)`）。会員にも、スタッフにも使います。メールのテンプレートとセッションのキーは、種類の名前から決まります。

用途（`PURPOSE_LOGIN`・`PURPOSE_PASSWORD_RESET`・`PURPOSE_MYPAGE_PASSWORD`・`PURPOSE_REGISTER`・`PURPOSE_PASSKEY`・`PURPOSE_BACKUP_CODES`・`PURPOSE_FIRST_LOGIN`・`PURPOSE_EMAIL_CHANGE`）ごとにセッションで管理します。`issue()` で発行とメール送信、`verify()` で照合します。会員登録のようにまだ会員がいない場合と、初回のログインでの登録やメールアドレスの変更のようにメールアドレスがまだ保存されていない場合は、`issueForAddress()`・`verifyForAddress()` です。

案件の側で決めた使い道（メルマガの登録など）にも使えます。使い道の文字列は案件の側で決め、メールの件名と本文に差し込む名前を `purposeLabel:` で渡します。使い道ごとにセッションのキーが分かれるので、ほかの画面のコードとは混ざりません。

```php
(new LoginVerificationCode(Member::class))->issueForAddress($request, $email, $name, 'newsletter', purposeLabel: 'メルマガの登録');
// 件名は「【サイト名】メルマガの登録のお手続き」。照合は verifyForAddress($request, 'newsletter', $code)
```

### メールアドレスの変更の確認（EmailChange）

**ファイル**：`packages/neobit/core/src/Auth/EmailChange.php`　**実例**：`MypageController`・`Company\ProfileController`、画面は `mypage/email-verify.blade.php`・`company/mypage/profile-email-verify.blade.php`

本人が自分の情報を保存するときに、メールアドレスが変わる場合だけ、確認コードの入力を挟みます。入力内容をセッションに仮置きして、新しいアドレスに確認コードを送り、コードが入力できた時点で、仮置きした内容をまとめて保存します。打ち間違えたアドレスに変えてログインできなくなることと、ログイン中の画面を使った他人が自分のアドレスに書き換えることを防ぎます。メールアドレスが変わらない保存は、今までどおりすぐ保存します。

```php
// 本人の情報を FormFlow で保存しているコントローラー
use EmailChange;
private const EMAIL_CHANGE_GUARD = 'web';                               // ログイン中の本人を取るガード
private const EMAIL_CHANGE_ROUTE = 'mypage.email';                      // 確認コードの入力画面のルート名
private const EMAIL_CHANGE_VIEW = 'mypage.email-verify';                // 確認コードの入力画面のビュー
private const EMAIL_CHANGE_EDIT_ROUTE = 'mypage.edit';                  // 入力画面のルート名
private const EMAIL_CHANGE_DONE_ROUTE = 'mypage';                       // 保存の後の移動先のルート名
private const EMAIL_CHANGE_DONE_MESSAGE = 'プロフィールを更新しました。';  // 保存の後に出すメッセージ
private const EMAIL_CHANGE_THROTTLE_SCOPE = 'member-email-change-code'; // 確認コードの試行制限

public function update(Request $request): RedirectResponse
{
    $member = Auth::user();

    // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む
    $toVerify = $this->holdForEmailChange($request, $member);

    if ($toVerify !== null) {
        return $toVerify;
    }

    $this->saveData($member, $request);
    // ...
}
```

| アクション | ルート名 |
|---|---|
| `emailChangeForm()`（GET）・`emailChangeConfirm()`（POST）・`emailChangeResend()`（POST）・`emailChangeBack()`（POST） | `EMAIL_CHANGE_ROUTE` と、その後ろに `.confirm`・`.resend`・`.back`。`.resend` には `throttle` を付ける |

- 保存は `FormFlow` の `saveValidated()` を通ります。操作ログと `afterSave()`（お知らせのメール）は、確認を挟まない保存と同じです。お知らせは、変わる前と後の両方のアドレスに届きます（22章）。
- 仮置きした内容は、保存の前にもう一度 `rules()` で検証します。コードの入力を待つ間に、同じアドレスが別の会員に使われたときは、入力画面へ戻します。
- 確認コードを送れる回数は、1人につき1時間に5回までです（`EmailChange` の定数）。新しいアドレスは自由に入力できるので、他人のアドレスに大量に送りつけられるのを防ぎます。
- 確認コードの入力画面の「入力内容を修正する」は、仮置きを消して、入力内容を持って入力画面へ戻します。
- 対象は、本人が自分で変える画面だけです。管理画面からスタッフが変えるときと、企業会員でほかの担当者が変えるとき（`Company\UserController`）は、変える人が新しいアドレスのメールを受け取れないので、確認を挟みません。

### パスワードを変えるとき

- `auth.session` が、パスワードを変えたときにほかの端末のログインと「ログイン状態を保持する」の Cookie を無効にします。
- パスワードの更新は、必ず「ログイン中のユーザーのインスタンス」に対して行います（別のインスタンスを更新すると本人までログアウトされます）。別のインスタンスを保存した場合は `Auth::guard(...)->setUser($record)` で差し替えます（実例：`StaffController::afterSave()`）。
- パスワードを変えたら、保存の直後に `PasswordChange::resetAndNotify($record, changedBy: 管理画面から変えたスタッフ)` を呼びます（トランザクションの中でよい）。信頼済み端末とパスキーをすべて無効にし、確定後に登録されているメールアドレスへお知らせを送ります（テンプレートは `member_password_changed`・`company_user_password_changed`・`staff_password_changed`）。スタッフの2段階認証（TOTP）は消しません。戻り値は削除したパスキーの件数で、画面のメッセージに使えます。お問い合わせの画面のように、サイトごとに違う案内の URL は、テンプレートの中に `{!! route('contact.create') !!}` のように書きます。

### 認証まわりのテーブル

信頼済み端末（`trusted_devices`）・認証アプリの登録（`two_factor_secrets`）・バックアップコード（`two_factor_backup_codes`）・パスキー（`passkeys`）のようなフレームワーク内部のテーブルは、会員・スタッフで共通の1つにし、`authenticatable_type`（`'member'`・`'company_user'`・`'staff'`）と `authenticatable_id` で区別します。新しい認証対象のモデルを足したら、`AppServiceProvider` の `Relation::enforceMorphMap()` にも足します。

### パスキー（PasskeyLogin・PasskeyManagement）

**ファイル**：`packages/neobit/core/src/Auth/PasskeyLogin.php`・`PasskeyManagement.php`・`PasskeyCeremony.php`・`HasPasskeys.php`・`UserAgentLabel.php`、`packages/neobit/core/src/Models/Passkey.php`、`packages/neobit/core/resources/views/_passkeys.blade.php`、`resources/js/passkeys.js`、設計の詳細は「パスキーの設計」（`docs/passkeys-spec.md`）　**実例**：会員は `AuthSessionController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\StaffController`

ログイン画面の「パスキーでログイン」で通ったときは、パスワードも2段階目も求めずにログインさせます。登録の前の本人確認は、その人のログインの2段階目の方式（メールの確認コードか、認証アプリのコード）で行います。2段階目が「なし」の人は、パスワードの再入力で確かめます。トレイトが `TwoFactorChallenge` に任せるので、方式での場合分けは要りません。

パッケージ（composer の `laravel/passkeys`、npm の `@laravel/passkeys`）・`passkeys` テーブル・モデルの `implements PasskeyUser` と `use HasPasskeys` は、標準構成として常に入れておきます。使うかどうかは、次のコントローラーの `use` とルートで切り替えます。画面の入口（ログイン画面のボタン、マイページ・スタッフ詳細のリンク）は `Route::has()` で出し分けているので、ルートを消せば消えます。

```php
// ログインのコントローラー
use PasskeyLogin;
private const PASSKEY_GUARD = 'web';                  // ログインさせるガード
private function passkeyRedirectUrl(): string         // ログイン後の移動先
{
    return LoginRedirect::for(Member::class, route('mypage'));
}

// 本人のパスキーを扱うコントローラー
use PasskeyManagement;
private const PASSKEY_GUARD = 'web';                           // ログイン中の本人を取るガード
private const PASSKEY_ROUTE = 'mypage.passkeys';               // 一覧画面のルート名
private const PASSKEY_VIEW = 'mypage.passkeys';                // 一覧画面のビュー
```

| トレイト | アクション | ルート名 |
|---|---|---|
| `PasskeyLogin` | `passkeyLoginOptions()`（GET）・`passkeyLogin()`（POST） | `login.passkey.options`・`login.passkey`（管理は `admin.` を付ける。ゲスト用のグループに入れ、`throttle` を付ける） |
| `PasskeyManagement` | `passkeyIndex()`（GET）・`passkeySendCode()`（POST。メールの方式だけ。`throttle` を付ける）・`passkeyConfirm()`（POST）・`passkeyRegistrationOptions()`（GET）・`passkeyStore()`（POST）・`passkeyDestroy()`（DELETE） | `PASSKEY_ROUTE` と、その後ろに `.code`・`.confirm`・`.options`・`.store`・`.destroy` |

- 一覧画面のビューは、`@push('head-extra')` で CSRF の `<meta>` と `vendor/neobit/core/resources/js/passkeys.js` を読み込み、`@include('neobit::_passkeys')` するだけにします。
- 本人確認の失敗は、ログインの2段階目と同じカウンターで数えます（`TwoFactorChallenge::throttle()`）。
- 認証アプリの方式の人は、認証アプリを登録してある間だけパスキーを持てます。未登録の間は、登録に進めません。
- パスキーは `APP_URL` のドメインに結び付きます。`APP_URL` をブラウザで開いているアドレスと完全に同じにし、HTTPS（開発時の `localhost` は例外）で使います。
- **登録の案内**：パスキーを持っていない人に、パスワードでログインした直後の画面の上で、登録を1回だけ勧めます（`PasskeySuggestion`・`_passkey_suggest.blade.php`）。パスキーの一覧画面のルートがあれば出て、無ければ出ません。見せたかどうかは、DB ではなくブラウザの Cookie で覚えます。パスワードを変えたときと、認証アプリを登録し直したときは、もう1回出ます。
- 退会と2段階認証の登録解除では、`$record->passkeys()->delete()` でパスキーも消します。パスワードを変えたときは `PasswordChange` が消します。

## 15. トランザクションとファイルの削除

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- DB への書き込みが2つ以上続く処理は `DB::transaction()` で囲みます。FormFlow の `saveData()`・`deleteData()`、CSV 取り込みの実行は、中で囲んでいます。
- ファイルの実物の削除は `DB::afterCommit()` に渡し、トランザクションが確定してから行います（取り消されたときに、元に戻った DB が参照しているファイルを消さないため）。トランザクションの外で呼ばれたときは、その場で消えます。
- メールの送信も、保存のトランザクションが確定した後に行います。
- 検証（`validate()`）はトランザクションの外で行います。

## 16. テーブル・モデルの決まり

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- **テーブル名**：業務のデータ（会員、ニュース、問い合わせなど）は `t_` を付けます。フレームワークの側のテーブルは `t_` を付けません。認証の保持や試行制限のためのものと、誰が何をしたかの記録（操作ログ、CSV のダウンロード・取り込みの記録）です。認証のためのテーブルは、会員・スタッフ共通の汎用のテーブルにします。
- **フレームワークのテーブルのマイグレーション**は、パッケージの `database/migrations/` にあり、`php artisan migrate` でアプリのものと一緒に流れます（コード表・操作ログ・CSV の記録・2段階認証・パスキー・招待）。アプリの `database/migrations/` には、業務のテーブルだけを置きます。
- DB の外部キー制約（`constrained()`）は付けていません。関連の片付けは `beforeDelete()` などでコードで行います。
- **モデル**：データ項目の仕様（画像の横幅など）は定数、表示用の値（ファイルの URL など）はアクセサにします。日付は `date`、フラグは `boolean`、列挙型は列挙型のクラスで `$casts` に書きます。
- 論理削除が要るものは `SoftDeletes`（`deleted_at`）を使います。

### 新しく登録する行の id の始まり（app:set-next-id）

**ファイル**：`packages/neobit/core/src/Console/SetNextId.php`（冒頭のコメント）

会員などの id は、DB が自動で振る連番です。サイトを開ける前に、新しく登録する行の id を、区切りのよい番号から始めたいときは、このコマンドで決めます。既存のシステムのデータを元の id のまま取り込んだ後、新しい登録を別の範囲の番号にすれば、番号で見分けられます。

```bash
# 今の状態（いちばん大きい id と、次に振る id）を見る
php artisan app:set-next-id t_members

# 次に登録する会員の id を、100001 にする
php artisan app:set-next-id t_members 100001
```

- **今のいちばん大きい id より大きい番号にします**。小さい番号を DB に直接指定すると、DB は黙って「いちばん大きい id の次」から振るので、効いたように見えて効きません。このコマンドは、小さい番号を断ります。設定した後は、実際に効いたかを DB から読み直して確かめます。
- **設定し直せるのは、行が登録されるまでです**。設定した番号で行が登録されると、その番号が「いちばん大きい id」になり、それより下の番号には設定できなくなります。サイトを開ける前に決めます。実行のときに確かめの質問が出ます。`--force` を付けると、質問を出さずに設定します。
- **サーバーごとに実行します**。DB が覚える値なので、開発サーバーで設定しても、本番には引き継がれません。本番のデータを取り込んだ後に、本番で実行します。
- MariaDB と MySQL だけで使えます。

## 17. 画面（Blade）の決まり

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

- **レイアウト**：`layouts/` ディレクトリに置かれたテンプレートが、ページ全体の外枠になります。処理結果のメッセージは `->with('status', '...')`（緑）・`->with('error', '...')`（赤）で渡すと、レイアウトが表示します。
- **画面ごとのスクリプト**：その画面だけで使うもの（アップロード、WYSIWYG、並び替えなど）を head タグ内に差し込むときは、`@push('head-extra')` に書きます。body の終わりに差し込むときは、`@push('scripts')` に書きます。全画面で使うものは、フレームワークの `global.js` にあり、3つのレイアウトが読み込んでいます。
- **全体で使う CSS**：フレームワークの `global.css` にあります。3つのレイアウトが読み込んでいるので、どの画面にも効きます。サイトだけの全体の CSS を足すときは、アプリに `resources/css/app.css` を作り、レイアウトで `global.css` の後に読み込みます。画面の CSS は Bootstrap を使い、このファイルには、Bootstrap で足りない分だけを書きます。今あるのは、見本の文字の濃さ、エラー欄の表示、読み取り専用の表示、WYSIWYG の本文の表示、削除済みの行の色です。変えたら、`npm run build` をやり直します。その画面だけで使う CSS は、その画面の `@push('head-extra')` に書きます。
- **入力欄の見本の文字（placeholder）**：濃さは、`global.css` で全体を `opacity: 0.5` にそろえています。入力した値と見分けやすくするためです。確認コード（6桁）の入力欄には、`placeholder="000000"` を付けます。バックアップコードの入力欄は `0000-0000` です。
- **フォームの補助（global.js）**：必須マーク（`.required-mark`）の付いたラベルの `for` の欄に `required` 属性を付ける、エラー欄（`.invalid-feedback[data-item]`）に文言があれば同じ名前の欄を赤くする、ブラウザの入力チェックの結果を同じエラー欄に出す、入力し直したらエラー表示を消す、エラーのあるモーダルを開く、クラス名 `ajax_submit` の付いたフォームを画面を読み直さずに送る、フォームを送っている間は二重の送信を止めて処理中の表示を出す。画面ごとの設定は要りません。ラベルの `for` と入力欄の `id` を必ず対応させてください。
- **フォームの送信中の表示（global.js）**：どのフォームも、送信した瞬間から2回目の送信を止めます。0.3秒経っても画面が切り替わらなければ、画面全体を覆って処理中であることを見せます。目印は要りません。確認コードのメールが2通届く、登録が2件できる、といったことを防ぐためです。すぐ切り替わる画面では、表示は出ません。リンクでの移動には出しません。読み込みを Esc キーで止めたときと、ブラウザの「戻る」で画面が復元されたときは、送信できる状態に戻ります。
- **ファイルを返すものは、フォームにせずリンクにします（global.js）**。フォームにするときは `target` を付けます。ファイルが返ってくると画面が切り替わらないので、処理中の表示が覆ったままになるためです。`target` の付いたフォームには、送信中の表示の仕組みは働きません。CSV のダウンロードと PDF は、どれもリンクにしてあります。
- **モーダルの中のフォーム（global.js）**：入力欄のあるフォームには `<form class="ajax_submit" ...>` と書きます。`global.js` が画面を読み直さずに送り、エラーは同じフォームのエラー欄（`.invalid-feedback[data-item]`）に出します。うまくいったときだけ、コントローラーが返した移動先へ移ります。送っている間は、画面全体を覆って処理中であることを見せます。エラーのたびに画面を読み直すと、モーダルが閉じて開き直すことになるためです。
    - コントローラーは、ふつうの送信と同じくリダイレクトを返します。JSON への読み替えは、ミドルウェア `AjaxFormResponse` が行います。エラーは `redirect()->withErrors([...])` でも `validate()` でもかまいません。
    - 削除の確認のように入力欄の無いモーダルには、付けなくてかまいません。エラーが出ないためです。
    - JavaScript が動かなかったときは、ふつうの送信になります。そのときは、エラーのあるモーダルを `global.js` が自動で開きます。
    - モーダルを使う画面には、Bootstrap の JavaScript が要ります。管理画面のレイアウトは読み込んでいますが、訪問者向けと企業会員のレイアウトは読み込んでいないので、その画面の `@push('head-extra')` で読み込みます（例：`mypage/two-factor.blade.php`）。
- **クラス名と `data-` の使い分け**：JavaScript に「この要素はこの機能で動かす」と伝えるのは、クラス名です（`ajax_upload_block`・`wysiwyg`・`ajax_submit`）。その機能に値を渡すときに、`data-` の属性を使います（`data-upload-url`・`data-item`・`data-confirm`）。
- **読み取り専用の表示**：管理画面の確認・詳細画面は、入力画面と同じ `_fields` を共有して表示します。`$readonly = ' readonly'`・`$disabled = ' disabled'` を入力欄に展開して、入力できない表示にします。項目が増減したときに、それぞれの画面を書き直さずに済みます。見た目は、全体で使う CSS（フレームワークの `global.css`）で整えています。訪問者向けや企業会員の画面でも、同じ仕組みをそのまま使えます。
- **一覧**：削除済みの行は `row-deleted`、ページ送りは `pagination::bootstrap-5`（Bootstrap v5 のデザインで表示）。
- ページ送りで、今のページの左右に展開するページの数は、3つずつです。左右4つずつに増やしたいときは、`onEachSide()` を付けます。

    ```blade
    {{ $records->onEachSide(4)->links('pagination::bootstrap-5') }}
    ```
- **戻り先**：一覧へ戻るリンクは `route('admin.xxx.index', ['back'])`。
- **共通の部分ビュー**（`_confirm_hidden`・`_ajax_upload_block` など）は、フレームワークの `packages/neobit/core/resources/views/` に置き、モデル名やコントローラー名を書きません。コーナー専用のテンプレート（`admin/news/_fields` など）は、アプリの `resources/views/` に置き、そのコーナーのモデルの定数を参照してかまいません。フレームワークの画面は、`neobit::` を付けた名前で `@include` します（例：`@include('neobit::_confirm_hidden')`）。見た目や文言を変えたいときは、アプリの `resources/views/vendor/neobit/` に同じ名前（例：`_confirm_hidden.blade.php`）で置くと、そちらが使われます。
- **フレームワークの画面が継承するレイアウト**は、`config/neobit.php` の `layouts`（`admin`・`app`）で、アプリのレイアウトに差し替えます。既定は、Bootstrap だけの `neobit::layouts.plain` です。レイアウトには、セクション `title`・`content`（必須）・`content-width` と、スタック `head-extra`・`scripts` を用意します。

### スクリプトと CSS のビルド（vite.config.js）

フレームワークのスクリプトと CSS は、`packages/neobit/core/resources/js/`・`css/` にあります。アプリからは、Composer が置いた `vendor/neobit/core/` の下のパスで読み込みます。

```js
// vite.config.js
const NEOBIT = 'vendor/neobit/core/resources';

input: [`${NEOBIT}/css/global.css`, `${NEOBIT}/js/global.js`, `${NEOBIT}/js/passkeys.js`, ...],
resolve: {
    preserveSymlinks: true,
    alias: { '@neobit': fileURLToPath(new URL(`./${NEOBIT}/js`, import.meta.url)) },
},
```

```blade
@vite(['vendor/neobit/core/resources/js/passkeys.js'])
```

- **`preserveSymlinks: true`**：`vendor/neobit/core` がリンクのとき（memsys の手元とサーバー）に要ります。無いと、リンクの先の実体のパスでマニフェストに載り、画面の `@vite()` が見つけられずにエラーになります。リンクでない案件に書いておいても、害はありません。
- **`@neobit`**：フレームワークのスクリプトの場所の短い名前です。エディタのスクリプトをアプリへコピーしても読み込みが切れないよう、フレームワークのスクリプトは `@neobit/upload_request.js` のように読み込みます（7章）。
- **ビルドした URL にフレームワークの名前は出ません**。URL は `/build/assets/global-<ハッシュ>.js` のように、ファイルの名前から作られます。`npm run dev` を使っている間だけは、手元の画面で元のパスのまま出ます。
- **フレームワークを更新したら、`npm run build` をやり直します**。スクリプトや CSS が変わっていることがあるためです。

フレームワークのスクリプトは、npm のパッケージを読み込みます。Composer は npm のパッケージを入れないので、アプリの `package.json` に書きます。

| npm のパッケージ | 読み込むスクリプト | 要るとき |
|---|---|---|
| `@laravel/passkeys` | `passkeys.js` | パスキーを使うとき |
| `suneditor` | `wysiwyg_suneditor.js`・`wysiwyg_suneditor_content.css` | SunEditor を使うとき |

## 18. PDF 出力（PdfDownload）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Pdf/PdfDownload.php`（冒頭のコメント）・`resources/fonts/ipaex/`（IPAex フォントとライセンス）・`resources/views/pdf/`　**実例**：履歴書（`Admin\MemberController::resume()`・`MypageController::resume()`・`resources/views/pdf/resume.blade.php`）

PDF の見た目は、普通の画面と同じく Blade のテンプレート（HTML と CSS）で書き、mPDF（composer の `mpdf/mpdf`）で PDF にします。

### コントローラーに書くもの

```php
use PdfDownload;

public function resume(Member $member): Response
{
    return $this->downloadPdf(
        view: 'pdf.resume',                       // resources/views/pdf/resume.blade.php
        data: ['member' => $member],              // テンプレートに渡す変数
        name: '履歴書_'.$member->name,            // ファイル名「履歴書_名前_年月日_時分.pdf」
        images: [
            'photo' => [
                'path' => $member->photo_path,
                'aspect' => Member::PHOTO_ASPECT,
            ],
        ],
        paper: 'A4',                              // 用紙（mPDF の format）
        orientation: 'P',                         // P：縦、L：横
        inline: true,                             // true：ブラウザの中で開く、false：ダウンロード
    );
}
```

- `images` の `aspect`（`[横, 縦]`）を書くと、画像の真ん中をその比で切り抜いてから埋め込みます。mPDF は CSS の `object-fit` に対応していないので、決まった大きさの枠に写真をゆがめずに収めるためです。`path` が null か、ファイルが無いときは埋め込みません。
- ルート：`Route::get('/members/{member}/resume', ...)->name('members.resume')`。画面のボタンやリンクは、今の画面が切り替わらないように `target="_blank"` で開きます。
- 返す PDF には、ブラウザやプロキシに残させないヘッダー（`Cache-Control: private, no-store`）を付けています。

### テンプレート

- 埋め込める画像は `$images` に入っています。`<img>` の `src` に、そのまま書ける値です。`@if (isset($images['photo'])) <img src="{{ $images['photo'] }}" style="width: 30mm; height: 40mm;"> @endif` のように書きます。画像はファイルのパスや URL ではなく mPDF の「`var:名前`」で渡すので、非公開のファイルも埋め込めます。
- フォントは、同梱の IPAex ゴシック（`ipaexg`、既定）と IPAex 明朝（`ipaexm`）を `font-family` で指定します。使った文字だけが PDF に埋め込まれるので、どの環境でも同じ見た目になります。
- **フォントは、アプリの `resources/fonts/ipaex/` に置きます**。フレームワークには入っていません。PDF を使わないサイトに、大きなファイル（14MB）を持たせないためです。無いときは、PDF を作るときに、置き場所を書いた例外になります。
- 余白は `@page { margin: ... }`、大きさは mm で書きます。
- mPDF が解釈できる CSS はブラウザより少なく、flex や grid は使えません。枠や罫線は `<table>` で組みます。

### 定型紙に文字を載せる（downloadPdfOnSheet）

履歴書や申込書のような、決まった様式の PDF（定型紙）を下敷きにして、上に文字を載せた PDF を出します。**実例**：`Admin\MemberController::resumeOnSheet()`・`resources/views/pdf/resume_on_sheet.blade.php`（厚生労働省の履歴書様式例の A4・2ページ）。

```php
return $this->downloadPdfOnSheet(
    sheet: resource_path('pdf-sheets/resume_a4.pdf'),   // 定型紙。アプリの resources/pdf-sheets/ に置く
    sheetPaging: SheetPaging::Once,                     // 定型紙のページの使い方
    view: 'pdf.resume_on_sheet',                        // 上に載せる文字のテンプレート
    data: ['member' => $member],
    name: '履歴書_'.$member->name,
    images: [...],                                      // downloadPdf() と同じ
    inline: true,
);
```

- `paper`・`orientation` は渡しません。ページの大きさは定型紙の1ページ目に合わせ、余白は0にします。
- テンプレートでは、文字を `position: absolute` の `<div>` で置きます。`left`・`top` は、定型紙の左上からの mm です。画像も、位置を決めた `<div>` の中に `<img>` を入れます。`<img>` に直接 `position: absolute` を付けても効かず、ページの左上に元の大きさで出てしまいます。

    ```blade
    <div style="position: absolute; left: 34mm; top: 40mm; width: 115mm; font-size: 20pt;">{{ $member->name }}</div>
    ```

- **折り返しとはみ出し**：`width` を決めると、幅を超えた文字は次の行へ折り返します。`height` と一緒に `overflow: hidden` を書くと、はみ出した分を切ります。`overflow: auto` を書くと、欄に収まるよう文字を縮めます（長い住所や名前に向く）。
- **ページ**：`<pagebreak />` で次のページへ進みます。どのページに定型紙のどのページを敷くかは、`sheetPaging` で決めます。テンプレートのページ数が定型紙と同じか少なければ、どれも1ページ目から順に敷きます。違うのは、定型紙のページ数がテンプレートより少ないときです。

    | `sheetPaging` | 定型紙のページ数がテンプレートより少ないとき | 2ページの定型紙での例 |
    |---|---|---|
    | `SheetPaging::Once` | 例外にする。くり返しの要らない様式で、`<pagebreak />` の入れすぎに気付ける | 1・2 |
    | `SheetPaging::Sequential` | 最後のページをくり返し敷く。続きのページを何枚でも足す様式 | 1・2・2・2… |
    | `SheetPaging::Cycle` | 1ページ目から順にくり返し敷く。表・裏の様式を人数分まとめて出す | 1・2・1・2… |

    ほかの使い方が要るときは、`SheetPagingRule` を実装した列挙型をアプリに作って渡します（名前は `App` で始める）。`sheetPage($page, $sheetPageCount)` が、出来上がる PDF の `$page` ページ目に敷く、定型紙のページを返します。

    ```php
    enum AppSheetPaging implements SheetPagingRule
    {
        case CoverThenSecond;   // 1ページ目は表紙、2ページ目から先は定型紙の2ページ目

        public function sheetPage(int $page, int $sheetPageCount): int
        {
            return $page === 1 ? 1 : 2;
        }
    }
    ```

- **あふれ**：1ページ分の中身があふれて、mPDF が自動でページを足すと、足されたページには定型紙が敷かれません。文字は `position: absolute` で置くので、ふつうはあふれません。
- **表の行**：学歴や資格のように行の高さが決まった欄は、`@foreach` の中で `top` を行の高さずつずらします。見本の履歴書では、会員の経歴を学歴・職歴の欄に載せ、1ページ目の欄に入りきらない分を2ページ目の欄に続けています。
- **位置の決め方**：定型紙を印刷して測るか、罫線の位置を PDF から読み取って決めます。試しに出して、ずれを直します。
- **読めない PDF**：定型紙を読むのは、mPDF に入っている FPDI（無料版）です。古い作り方の PDF などで読めないときは、例外になります。そのときは、PDF の形式を変えてから置きます（「PDF 1.4 として保存し直す」など）。見本の定型紙（PDF 1.7）は、そのまま読めました。
- **使えない CSS**：flex や grid は使えません。枠の中で縦の真ん中にそろえる指定（`vertical-align`）も効かないので、`padding-top` で合わせます。

### 決まりごと

- mPDF の作業用のディレクトリは `storage/framework/mpdf`（Git の対象外）です。最初の1回はフォントを解析するので、少し時間がかかります。
- フォントを足すときは、`resources/fonts/` にファイルとライセンスを置き、`PdfDownload` の `PDF_FONTS` に足します。

## 19. スケジューラー（定期的な処理）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`routes/console.php`（スケジュールの一覧）・`packages/neobit/core/src/Console/`（コマンド）　**実例**：一時データの後片付け（`packages/neobit/core/src/Operations/TemporaryDataCleaner.php`・`packages/neobit/core/src/Console/CleanupTemporaryData.php`）

「決まった時刻に何かを実行する」処理は、Artisan のコマンドとして作り、`routes/console.php` でいつ動かすかを決めます。表示するたびに今の時刻と比べれば済むもの（ニュースの掲載期間など）は、スケジューラーを使いません。

### コマンドとスケジュール

```php
// packages/neobit/core/src/Console/CleanupTemporaryData.php（php artisan make:command で作る）
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

- コマンドからしか呼ばない処理は、`app/Console/Commands/` のコマンドのクラスの中に書きます。画面からも呼ぶ処理は、`app/Services/` のクラスに置いて、コマンドは呼ぶだけにします。同じ処理を2か所に書かないためです。フレームワークの部品は `packages/neobit/core/src/Operations/` にあり、上の例の `TemporaryDataCleaner` は、アップロードのついでの片付けからも呼んでいます。
- `withoutOverlapping()` で、前の回が終わっていなければ重ねて動かしません。
- 登録した一覧と次に動く時刻は `php artisan schedule:list`、すぐに動かすときは `php artisan app:cleanup-temporary-data` のようにコマンドを直接実行します。

### 一時データの後片付け（TemporaryDataCleaner）

**ファイル**：`packages/neobit/core/src/Operations/TemporaryDataCleaner.php`（冒頭のコメント）・`config/cleanup.php`（残す日数と、プロジェクトの片付け）

フレームワークが片付けるものは、次のとおりです。

| 一時データ | 置き場所 | 消すもの |
|---|---|---|
| アップロード直後の一時ファイル | `storage/app/private/tmp` | 24時間（`MAX_AGE_HOURS`）より古いファイル |
| CSV 取り込みの作業用ファイル | `storage/app/private/csv_import` | 24時間より古いファイル |
| 試行制限の回数などのキャッシュ | `cache`・`cache_locks` テーブル | 期限の切れた行（キャッシュを database に置いているときだけ） |
| 「この端末を信頼する」の記録 | `trusted_devices` テーブル | 期限（`expires_at`）の切れた行 |
| 操作ログ（22章） | `operation_logs` テーブル | `config/cleanup.php` の `operation_log_days`（365日）より古い行 |
| 送れなかったキューのジョブ（21章） | `failed_jobs` テーブル | `config/cleanup.php` の `failed_job_days`（7日）より古い行 |
| 終わったジョブのバッチ（21章） | `job_batches` テーブル | 終わってから `failed_job_days`（7日）を過ぎた行。送り直せる間だけ残す |
| 招待（23章） | `team_invitations` テーブル | 期限（`expires_at`）の切れた行（`TeamInvitationManager::deleteExpired()`） |

業務のデータの片付けは、`TemporaryDataCleaner` には書かず、`config/cleanup.php` の `tasks` に足します。フレームワークの片付けの後に、同じ1時間ごとの片付けの中で呼ばれます。サンプルサイトでは、お問い合わせと一斉メールの記録の片付けを足しています。

| 片付け | 置き場所 | 消すもの |
|---|---|---|
| お問い合わせ（12章） | `t_inquiries` テーブルと添付ファイル | `tasks` の `days` に書いた日数より古い行と、その添付ファイル。`null` なら消さない（`App\Services\InquiryCleanup`） |
| 一斉メールの記録（21章） | `t_bulk_mails` テーブルと添付ファイル | 送り終えてから `tasks` の `days` に書いた日数を過ぎた記録と、その添付ファイル・バッチの行。`null` なら消さない。日数は送信の履歴の画面にも出る（`App\Services\BulkMailCleanup`） |

```php
// config/cleanup.php
'tasks' => [
    '保存期間を過ぎたお問い合わせ' => [
        'run' => [InquiryCleanup::class, 'deleteOld'],                // 呼ぶメソッド。消した件数を返す
        'days' => null,                                               // 残す日数。メソッドに引数で渡す。行ごとに期限を持つものは、days を書かない
    ],
],
```

- 一時ファイルは、アップロードと CSV 取り込みのたびにも同じ処理で消します。サーバーの cron が動いていなくても溜まり続けないようにするための控えです。
- **お問い合わせは、日数を決めたサイトだけ消します**。`config/cleanup.php` の `tasks` の `days` に残す日数を書くと、過ぎた行と添付ファイルを消します。`null` なら、消さずに残し続けます。個人情報を、要らなくなった後も持ち続けないようにするためです。日数は、サイトの運用で決めます。1件ずつは操作ログに残さず、消した件数だけをログ（`laravel.log`）に残します。スタッフに届いたお問い合わせのメールは、消えません。
- 何かを消したときだけログに残し、何もしなかった回は残しません。同じ行が1時間ごとに並ばないようにするためです。
- 新しい片付けを足すときは、消した件数を返すメソッドを `app/Services/` のクラスなどに書き、`config/cleanup.php` の `tasks` に1つ足します。残す日数があるものは `days` に書くと、メソッドに引数で渡されます。名前は、片付けのコマンドの表とログに出ます。

### サーバーの設定（cron）

スケジューラーは、サーバーの cron で毎分 `php artisan schedule:run` を動かしたときに働きます。cron が無いと、`routes/console.php` に書いた処理も、キューのワーカー（21章）も何も動きません。cron を設定できないサーバーは、下の「cron を動かせないサーバー」のとおりにします。

サイトごとに `/etc/cron.d/` へファイルを1つ置きます。例は、開発用のサイトの `/etc/cron.d/memsys_dev` です。

```cron
* * * * * apache cd /var/www/memsys_dev && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

- `/etc/cron.d/` のファイルは、時刻の後に実行するユーザーを書きます。**PHP-FPM のそのサイトのプールと同じユーザー**にします（プールの設定の `user`）。root で動かすと、ログ（`storage/logs`）などのファイルが root の持ち物になり、画面からの処理が書き込めなくなります。apache のようにログインできないユーザーでも動きます。
- cron から動かすコマンドも、`bootstrap/cache/config.php` から設定を読みます。`schedule:run` が SQLite のエラーで止まるときは、`config:cache` をやり直してください（0章「サーバーへ送った後に実行するコマンド」）。
- cron の PATH は最小限なので、`php` は絶対パスで書きます。場所は `which php` で確かめます。サイトのパスもサーバーに合わせます。
- `/etc/cron.d/` に置くファイルは、root の持ち物で 644 にしてください。そうでないと、cron が読み飛ばします。最後の行の後ろには改行を入れます。無いと、最後の行が読まれないことがあります。ファイル名に `.` を入れると、読み飛ばされます。
- 開発用と本番が同じサーバーにあるときは、`/etc/cron.d/` に置くファイルを `memsys_dev`・`memsys` のように分け、中に書くサイトのパスと実行するユーザーを、それぞれに合わせます。
- 動いているかは、1〜2分待ってから `php artisan schedule:list` で次に動く時刻を見るか、`storage/logs` に一時データの片付けのログが出るかで確かめます。

### cron を動かせないサーバー

`.env` に1行書くと、cron の代わりに、サイトへのアクセスを契機にスケジューラーの予定を動かします（`Neobit\Core\Operations\RequestScheduler`）。

```dotenv
CRON_ENABLED=false
```

- **予定はそのまま**：`routes/console.php` に書いた予定（一時データの片付け、操作ログの報告、キューのワーカー）を読み、時刻を過ぎていれば動かします。予定を足すときも、cron があるときと同じく `routes/console.php` に書くだけです。
- **アクセスの後に動く**：全体のミドルウェア（`RunScheduleOnRequest`。`bootstrap/app.php` で足してある）が、応答を返した後に動かすので、アクセスした人を待たせません。予定を見るのは多くても1分に1回で、同時に動くのは1本だけです。
- **飛ばされない**：予定ごとに前に動いた時刻を覚え、それより後に予定の時刻が1回でもあれば動かします。例えば毎朝8時の報告は、8時を過ぎて最初のアクセスで送ります。アクセスの無い間にたまった回数分ではなく、1回だけ動かします。アクセスが無い間は、何も動きません。
- **初めての予定**：前に動いた記録の無い予定は、動かさずに今の時刻を覚えるだけにします。サーバーへ送った直後の最初のアクセスで、毎朝の報告などが時刻に関係なく動かないようにするためです。
- **同じプロセスで動かす**：`Schedule::command()` の予定は、別のプロセスを起動せず、`Artisan::call()` で動かします。そのため、`Schedule::exec()`（シェルのコマンド）の予定は動かさず、警告のログを残して飛ばします。`runInBackground()` も効きません。`when()`・`environments()`・`withoutOverlapping()` は、cron のときと同じように効きます。
- **キュー**：キューのワーカー（`queue:work --stop-when-empty --max-time=50 --sleep=0`）も予定の1つなので、一斉メールなどは、アクセスがあるたびに少しずつ送られます。速さの制限、試し直し、中止、進み具合は、cron のときと同じように働きます。
- **時間の上限**：予定は応答を返した後に動くので、PHP の時間の上限（`max_execution_time`）は外して動かします。PHP-FPM の `request_terminate_timeout` を決めているサーバーでは、そちらで切られます。キューのワーカーの `--max-time` は、その秒数より短くしてください。途中で切られた一斉メールのジョブは、DB のキューの仕組みで後からまた送られます。
- cron が動くサーバーでは、`CRON_ENABLED` は書かない（true のまま）にします。true なら、アクセスを契機には何も動きません。

**cron と同じように動かすには、外から毎分アクセスさせる**

アクセスが毎分1回以上あれば、どの予定も、予定の時刻から1分ほどの遅れで動き、cron とほぼ同じになります。アクセスの少ないサイトでは、外の監視サービスなどから、毎分 `/up` を開かせます。

- `/up` は Laravel の死活監視のルートで、DB も画面も使わないので、アクセスそのものの負荷がほとんどありません。全体のミドルウェアを通るので、予定は動きます。
- サイトのアクセス制限を掛けている間も `/up` は通すので、制限の間も止まりません。

cron と違うところは、次のとおりです。

- **時刻が少し後ろにずれる**：cron は毎分0秒ちょうどに予定を見ますが、こちらは予定の時刻を過ぎて最初のアクセスで見ます。予定を見るのは前に見てから60秒たった後なので、毎分1回のアクセスなら、遅れは多くて1分ほどです。
- **予定を1本の流れで順に動かす**：cron は毎分別のプロセスで動くので、前の分のキューのワーカー（最大50秒）が動いていても、次の分の予定はそれと並んで動きます。こちらは、予定を動かしている間に来たアクセスでは予定を見ません。例えば、一斉メールのワーカーが動いている間に毎朝の報告の時刻が来ると、報告はワーカーが終わった後のアクセスで動きます。飛ばされはせず、少し遅れるだけです。
- **1分より短い間隔の予定**（`everyTenSeconds()` など）は、1分に1回しか動きません。

## 20. エラーの通知（ErrorNotifyHandler）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Operations/ErrorNotifyHandler.php`・`config/logging.php`（`error_notify` のチャンネル）・`packages/neobit/core/resources/mail-templates/error_notify.blade.php`

本番で起きたエラーを、ログを見に行かなくても気付けるよう、ログに書くのと一緒に開発者へメールで知らせます。ログのチャンネルとして働くので、例外も `Log::error()` も同じ仕組みで届き、コントローラーや部品のコードに通知のための処理は書きません。独自に知らせたい事象があるときも、`ERROR_NOTIFY_LEVEL` 以上のレベルでログに書けば、メールで届きます。

```php
// 例：ERROR_NOTIFY_LEVEL=error のとき
Log::error('想定外の値が検出されました。', ['status' => $status]);
```

### .env の設定

```
ERROR_NOTIFY_LEVEL=error
ERROR_NOTIFY_TO=dev1@example.com,dev2@example.com
```

| 項目 | 意味 |
|---|---|
| `ERROR_NOTIFY_LEVEL` | 知らせる最低のレベル。空なら知らせない。手元の開発は空にする |
| `ERROR_NOTIFY_TO` | 宛先。カンマで区切って複数書ける |
| （`config/logging.php` の `error_notify` の `intervalMinutes`） | 同じ内容のものを送る間隔（分）。間隔の中の2回目からは送らずに数え、次の通知に件数を添える。0なら間引かない。値は10 |

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
- 同じ内容を省くときの判断材料は、例外なら種類と原因の場所、それ以外ならレベルと文言です。原因の場所は、スタックトレースの中で最初に出てくる自分たちのコードの行です（`vendor/` の下と、入口の `public/index.php`・`artisan` は除く）。SQL のエラーは、どの画面で起きてもフレームワークの同じ行から投げられるので、投げられた行ではなく原因の行で見分けます。メールの「例外」の欄にも、この行を載せます。間引きの記録はファイルのキャッシュに置くので、DB が落ちていても間引けます。
- SMTP が落ちているなどで通知が送れないときは、そのことをログに残すだけにします。通知が送れないこと自体はメールでは分からないので、サーバーのログも時々見ます。
- 通知が働くのは、`LOG_CHANNEL` が `stack`（既定）のときだけです。`stack` 以外にすると、ログは書かれますが、メールは届きません。通知の係（`error_notify`）は、`config/logging.php` の `stack` の中に組み込んであるためです。ログのファイルの分け方は、`LOG_CHANNEL` ではなく `LOG_STACK` で変えます（次の節）。

### ログのファイル

ログは `storage/logs` に書きます。ファイルの分け方は `.env` の `LOG_STACK` で決めます。

| `LOG_STACK` | ファイル | 古いログ | 使うところ |
|---|---|---|---|
| `single` | `laravel.log` の1つ | 消さない。書き足し続ける | 手元の開発 |
| `daily` | `laravel-2026-10-04.log` のように1日1ファイル | `config/logging.php` の `daily` の `max_files`（90日）を過ぎたものを自動で消す | サーバー |

- サーバーは `LOG_STACK=daily` を推奨します。
- 残す日数は、障害に気付いてから見に行くまでの間を考えて、長めにします。週ごとやサイズでの区切りはありません。区切れるのは1日ごとなので、後から見られるかどうかは日数で決まります。
- 独自の区切りでローテーションさせたい場合は、`LOG_STACK=single` にして、logrotate で独自に設定してください。
- `.env` を変えたら `php artisan config:cache` をやり直します（0章）。
- `daily` のときに今日のログを見るには、`tail -n 80 storage/logs/laravel-$(date +%F).log` とします。
- ログの中の `[previous exception]` は、その上に書かれた例外の、元になった例外です。起きたことは1件で、同じ1件を外側と内側の2段で書いています。SQL のエラーなら、上が Laravel の包み直した例外（実行した SQL が載っている）、下が DB の機能が最初に投げた例外です。`[stacktrace]` の中の、パス `app/` を含む行が自分たちの書いたコードです。
- エラーの通知の間引きは、メールだけのものです。ログには、起きたエラーが1件ずつ全部残ります。

## 21. キュー（一斉メール）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Jobs/`（ジョブ）・`routes/console.php`（ワーカーのスケジュール）　**実例**：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`）

画面の中で終わらない重い処理や、外のサーバーへの送信をたくさん行う処理は、キューに積んで裏で動かします。どのクラスを動かすかは、積む側が積むときに決めます。`app/Jobs/` のジョブのクラスを作って `dispatch()` で積むと、ワーカーが、そのクラスの `handle()` を呼びます。キューは DB（`QUEUE_CONNECTION=database`）を使い、積んだジョブは `jobs` テーブルに入って、終われば消えます。

### ワーカー

```php
// routes/console.php。毎分動かし、積まれているものが無くなったら止まる
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--sleep=0'])->everyMinute()->withoutOverlapping();
```

- スケジューラー（19章）の cron のままで動き、Supervisor などの常駐の仕組みは要りません。毎分の cron の実行のタイミングで処理されるので、積んでから送り始めるまでに、最大1分ほど掛かります。
- cron を動かせないサーバー（`CRON_ENABLED=false`）では、ワーカーの予定も、サイトへのアクセスを契機に動きます（19章「cron を動かせないサーバー」）。
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

- **送る速さ**：`AppServiceProvider` の `RateLimiter::for('bulk-mail', ...)` で1分の上限を決め、ジョブの `RateLimited` で守ります。上限は `.env` の `MAIL_BULK_PER_MINUTE` で、SMTP の送信の上限を超えない値にします。見本の値は5です。5通が等間隔で送られるわけではなく、一度に5通を送って、次の1分が来るまで待ちます。
  さくらのレンタルサーバ（ライト・スタンダード・プレミアム）は15分に100通ほどが目安（[メール送信数の制限について](https://help.sakura.ad.jp/rs/2251/)）なので、1分に5通（15分に75通）にして、お問い合わせの通知などほかのメールの分を残しています。1,000通を送るのに3時間20分ほどかかります。
- **失敗**：試し直しても送れなかったジョブは `failed_jobs` に残り、エラーの通知（20章）にも届きます。放置しても、7日を過ぎると `TemporaryDataCleaner` が消します（19章）。送り直したいときは、その前に `php artisan queue:retry all` を実行してください。
- **宛先のドメインの確認**：宛先の CSV を読むときに、メールアドレスのドメインに、メールを受け取る設定（MX）かサーバーの住所（A・AAAA）があるかを DNS で確かめ、無ければ確認画面でエラーの行にします（`App\Rules\MailDomainRule`）。`gmial.con` のようなドメインの打ち間違いを、送る前に見つけるためです。同じドメインは1回だけ引きます。アカウントが無いこと（User unknown）は送ってみないと分からないので、ここでは見つかりません。`exmaple.com` のように、打ち間違いを狙って登録されているドメインも、あることになるので止まりません。DNS を引けないサーバーと、外のネットワークに頼らないテストでは、`.env` の `MAIL_BULK_CHECK_DOMAIN=false` で切ります（`config/mail.php` の `bulk_check_domain`）。
- **送れなかった宛先**：送信の詳細の画面の下に、送れなかった宛先（氏名・メールアドレス・送れなかった理由・日時）を枠で囲って並べます。宛先は送信の記録に保存しないので、`failed_jobs` に残ったジョブの中から読み出します。そのため、見られるのは上の日数の間だけで、日数は枠の中にも出します。画面に並べるのは先頭の100件で、全件は「CSVダウンロード（全件）」で落とせます。CSV は「氏名, メールアドレス, 送れなかった理由」の3列で、見出しの行はありません。3列目を消せば、そのまま宛先の CSV として読み込めます。ほかの CSV のダウンロードと同じく、ダウンロードの記録と操作ログを残します（`Admin\BulkMailController::failedCsv()`・`BulkMail::failedRecipients()`）。
- **バッチ**：たくさんのジョブを、1つずつ積まずに `Bus::batch()` で1つにまとめて積むと、Laravel がそのまとまりの件数を `job_batches` に数えます。進み具合（済み・失敗・残り）を、そこから読めます。`finally()` で、全部が終わったときの処理を書けます。一斉メールは、宛先の数だけのジョブを1つのバッチにして積み、送信の詳細の画面に進み具合を出しています（`Admin\BulkMailController` の `dispatchSendJobs()`・`sendCounts()`）。
- **トランザクション**：ジョブは、トランザクションが終わった後で積んでください。トランザクションの中で保存した行は、終わるまでワーカーからは見えません。中で積むと、ワーカーが先に動いて、行が見つからずに失敗することがあります。中で積むしかないときは、`dispatch()` に `->afterCommit()` を付けると、終わるまで待ってから積まれます。一斉メールは、送信の記録を保存し終えてから積んでいます（`Admin\BulkMailController::store()`）。

    ```php
    $bulkMail = DB::transaction(function () {
        return BulkMail::create([...]);
    });                                          // ここで確定。ワーカーにも見える

    SendBulkMail::dispatch($bulkMail->id);       // トランザクションの外で積む
    ```

### 一斉メール

- **文面の管理**：タイトル（管理用）・件名・本文を登録しておきます。
- **送信**：入力（文面を選ぶと欄に入るので、必要な箇所を書き換える。添付ファイルは1つ）→ 確認 → 送信 → 状況の画面。読み込ませる宛先の CSV は、「氏名,メールアドレス」の見出し無しの形です。文字コードは自動判別します。`CsvReader`（10章）で、読み込みと検証だけを借ります。
- **氏名の差し込み**：件名と本文の `{{$name}}` を宛先の氏名に置き換えます。Blade では展開せず、文字列を置き換えるだけです。Blade で展開すると、画面から Blade のコードを入力されたときに、それがサーバーで実行されてしまうためです。ほかの `{{…}}` は入力エラーにします。件名と CSV の氏名は、メールの見出しを崩さないよう改行を許しません。
- **宛先は保存しない**：送るたびに送信の記録（`t_bulk_mails`）を1件作りますが、持つのは件名・本文・添付ファイル・件数だけです。宛先はジョブの中にだけあり、送り終えれば消えます。送り終えたら送れた件数と失敗した件数を記録に写します。
- **二重の送信**：送信中の一斉メールがあれば、入力画面を開いても状況の画面に回します。送信の実行はロックの中で確かめるので、2つの画面から同時に押しても1つしか送りません。
- 使えるのは管理者だけです。

### お知らせメールの配信停止（MailUnsubscribe）

**ファイル**：`app/Services/MailUnsubscribe.php`（冒頭のコメント）・`app/Enums/NoticeMail.php`　**実例**：`MailUnsubscribeController`、画面は `mail_unsubscribe/show.blade.php`・`done.blade.php`

広告のメールには、配信停止の方法を載せる決まりがあります（特定電子メール法）。Gmail なども、大量に送る相手に、ワンクリックで解除できることを求めています。個人会員は、お知らせメール（一斉メール）を受け取るかどうかを持ちます。

| 場所 | できること |
|---|---|
| 会員登録 | 「受け取る」を選んだ状態で出す |
| マイページのプロフィール編集 | 本人が変える |
| 管理画面の会員 | 編集で変える。一覧を「受け取る・受け取らない」で絞る。CSV のダウンロードと取り込みにも「お知らせメール」の列がある |
| メールの中の配信停止の URL | ログインを必要とせずに「受け取らない」に変える |
| メルマガの申し込み（`/newsletter`） | 会員のアドレスなら、確認コードが通った後に「受け取る」に変える（下の「メルマガだけの申し込み」） |

- **宛先の CSV の作り方**：一斉メールの入力の画面の「メルマガ希望者のCSVをダウンロード」で、お知らせメールを受け取る会員と、メルマガだけの人の全員を、宛先の CSV の形で落とせます。そのまま宛先として選べます。
- **配信を停止しているアドレスには送れません**：宛先の CSV に、「受け取らない」の会員のアドレスか、会員ではない人で配信を停止したアドレスがあると、確認画面でその行がエラーになり、送信のボタンが出ません。抽出した後に停止した人や、古い CSV の使い回しに備えるためです。
- **配信停止の URL は、自動で付きます**：本文の末尾に、宛先ごとの URL が付きます。メールソフトの「登録解除」のボタン用のヘッダー（`List-Unsubscribe`・`List-Unsubscribe-Post`）も付きます。付ける文とヘッダーは、どちらもテンプレート（`resources/mail-templates/bulk_mail.blade.php`）にあります。
- **URL は署名付きです**：宛先のメールアドレスを入れて、署名を付けます。アドレスを他人のものに書き換えた URL では、停止できません。期限はありません。`APP_KEY` を変えると、それまでに送ったメールの URL は使えなくなります。
- **URL のドメインは `APP_URL` です**：メールはキューのワーカーが送るので、`.env` の `APP_URL` が、訪問者の開く URL（`https://` から）になっている必要があります。
- **開いただけでは停止しません**：URL を開くと「配信を停止する」のボタンの画面が出て、押すと停止します。受信側のウイルス検査やプレビューが、メールの中の URL を自動で開くことがあるためです。
- **画面の表示は、いつも同じです**：そのアドレスの会員がいてもいなくても、URL が正しくなくても、同じ画面を出します。そのアドレスの会員がいるかどうかを、外から確かめられないようにするためです。
- **停止の操作は、全部を操作ログに残します**（22章）：会員がいたときは、対象を会員の種類と id で残します。会員がいないときと URL が正しくないときは、どのアドレスへの操作かが分かるよう、メールアドレスを補足に残します。会員ではない宛先の停止は、メルマガの行（下の「メルマガだけの申し込み」）にも「受け取らない」で残るので、次の一斉メールでは自動でエラーになります。
- **CSRF トークンは確かめません**：メールソフトの「登録解除」のボタンから、トークンの無い POST が届くためです（`bootstrap/app.php`）。本人のメールから来たことは、URL の署名で確かめます。
- 停止したことを知らせるメールは、送りません。本人は、マイページからいつでも「受け取る」に戻せます。

### メルマガだけの申し込み（NewsletterSubscription）

**ファイル**：`app/Services/NewsletterSubscription.php`（冒頭のコメント）・`app/Models/NewsletterSubscriber.php`　**実例**：`NewsletterController`（訪問者）・`Admin\NewsletterSubscriberController`（管理画面）、画面は `newsletter/`・`admin/newsletter_subscribers/`　**設計**：`docs/newsletter-subscription-spec.md`

会員登録をしなくても、お知らせメール（メルマガ）だけを受け取れるようにします。

- **持つ場所は1か所**：会員の「受け取るか」は会員のテーブル（`t_members.notice_mail`）、会員ではない人は `t_newsletter_subscribers` で持ちます。同じアドレスについて、どの時点でも1か所だけで持つので、ずれません。
- **申し込み**（`/newsletter`）：氏名（ニックネームでも可）とメールアドレスを入れると、会員登録と同じ確認コード（14章）が届きます。コードが通って初めて「受け取る」にします。他人のアドレスを勝手に登録されないようにし、受け取ると決めた日時と IP アドレスを、同意の記録として残すためです。スパム対策（Turnstile）と、送信の回数の制限を付けています。
- **会員のアドレスで申し込んだとき**：メルマガの行は作らず、会員の側を「受け取る」にします。完了の画面は同じ文で、そのアドレスの会員がいるかを外から確かめられないようにしています。
- **連動するのは2つの場面だけ**
  - 会員登録：同じアドレスのメルマガの行を消します。受け取るかは、登録の画面で本人が選んだものにします。
  - 退会：「受け取る」だった会員は、「受け取らない」の行を残します。会員の情報が消えた後に、同じアドレスが宛先に入っても送らないためです。
- **管理画面**：「運用」の「メルマガ希望者」で、会員ではない人の一覧・検索と、手での停止ができます。管理者だけが使えます。
- 対象は個人会員だけです。企業会員の担当者には、この項目はありません。
- 確認コードやパスワードの変更のお知らせのように、本人の操作に応えて送るメールは、この項目に関係なく送ります。

配信停止のヘッダーは、テンプレートの見出しの行に書いてあります（11章）。呼ぶ側は、配信停止の URL を変数で渡すだけです。

```text
{{-- resources/mail-templates/bulk_mail.blade.php --}}
FROM: {!! $from_name !!} <{!! $from_mail !!}>
TO: {!! $email !!}
SUBJECT: {!! $subject !!}
LIST_UNSUBSCRIBE: <{!! $unsubscribe_url !!}>
LIST_UNSUBSCRIBE_POST: List-Unsubscribe=One-Click
```

```php
Mail::send(new TemplatedMail('bulk_mail', [
    // ...
    'unsubscribe_url' => MailUnsubscribe::url($email),
], $attachments));
```

## 22. 操作ログ（OperationRecorder）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Operations/OperationRecorder.php`（冒頭のコメント）・`packages/neobit/core/src/Models/OperationLog.php`・`packages/neobit/core/src/Enums/OperationLogAction.php`・`packages/neobit/core/src/Http/Controllers/OperationLogController.php`（一覧）・`app/Enums/AppOperationLogModel.php`　**実例**：`Admin\MemberController::show()`（詳細の閲覧）

誰が・いつ・どこから・何に・何をしたかを、`operation_logs` に1行ずつ残します。情報が漏れたときや、「変えていないのに変わっている」という問い合わせがあったときに、後からたどれるようにするためです。

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
- **種類の名前**は、`AppServiceProvider` の `Relation::enforceMorphMap()` に書いた名前です。画面やメールでは、`config/neobit.php` の `operation_log_models` に書いた列挙型（見本のサイトでは `App\Enums\AppOperationLogModel`）から、「会員」のような名前にします。操作の種類（`operation_log_actions`）と同じく、フレームワークがコード表 `operation_log_model` を作ります。列挙型に無い種類は、種類の名前のまま出ます。操作ログに残すモデルを増やしたら、`enforceMorphMap()` と列挙型の両方に足します。

### 記録するところ

| 操作 | 記録するところ | コーナーに書くもの |
|---|---|---|
| 登録・更新・削除 | `FormFlow` の `saveData()`・`deleteData()` | 無い（別の名前で残すときだけ `savedLogAction()`） |
| ログイン・ログアウト | `AppServiceProvider`（Laravel のログイン・ログアウトのイベント） | 無い |
| ログインの失敗 | ログインのコントローラー（試行制限の回数を足すところ） | 無い |
| パスワードの変更 | `PasswordChange::resetAndNotify()` | 無い |
| パスキーの登録・削除 | `PasskeyManagement` | 無い |
| 担当者の招待の送信・取り消し | `TeamInvitationManager` | 無い |
| お知らせメールの配信停止 | `MailUnsubscribe`（メールの中の URL からの停止。21章） | 無い |
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

// 状態だけを変える操作。変わった列の名前と、変わった後の状態の名前を残す（Admin\CompanyController）
OperationRecorder::record(OperationLogAction::Update, $company, ['status'], ['status' => $to->label()]);
```

### 決まりごと

- **操作した人**：渡さなければ、その画面のガードでログインしている人です。管理画面ならスタッフ、マイページなら会員になります。2段階目の途中や、ログアウトした後のように、ログインはしていないが誰か分かっている操作は、`operator:` で渡します。
- **訪問者の操作も残します**：お問い合わせの送信やログインの失敗のように、誰もログインしていない操作は、操作した人を空にして残します。IP アドレスは残るので、回数の制限をすり抜けるような大量の操作があったときに、後から調べられます。
- **ログインの失敗は、対象にログインの種類を残します**：パスワードやパスキーの照合に失敗したときは、誰の失敗かが分からないので、対象の欄に種類だけ（個人会員・企業の担当者・スタッフ）を残します。どのログイン画面が狙われたかを、一覧の「対象」の検索で絞れます。種類だけを残すときは、`OperationRecorder::record()` に `targetClass:` でモデルのクラスを渡します。
- **登録・更新とは別の名前で残す**：コントローラーに `savedLogAction()` を書きます。一斉メールは、送信の記録を1件作るのが送信の始まりなので、「登録」ではなく「一斉メールの送信」として残しています。
- **一覧の表示と検索は記録しません**：1ページに出る件数が限られているためです。機械的に続けて取る動きは、下の「管理画面の回数の制限」で止めて記録します。
- **CSV 取り込みは、取り込み全体で1行です**：1件ずつの更新は記録しません。件数の内訳は取り込みの記録（`csv_import_logs`）が持ち、`detail` の id でつながります。CSV のダウンロードも同じです。
- **変わった列に数えない列**：`created_at`・`updated_at` などの保存のたびに変わる列と、アップロードの元のファイル名の列（`〇〇_origin`）は数えません。最後に更新したスタッフの id のように、入力とは関係なく変わる列は、モデルの定数 `OPERATION_LOG_IGNORE` に書きます（例：`Member`）。
- **変わった列は、本体のテーブルの列だけです**：多対多のような関連のテーブルの変更は、列の名前には出ません。「更新した」ことは残ります。
- **変わった項目は、列の名前のまま出します**（`phone`・`disp_flg` など）。日本語の名前の対応表は持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。
- **`detail` に個人情報と入力値を入れません**。ログインの失敗のログイン ID は、どのアカウントが狙われたかを調べるために残しています。お知らせメールの配信停止は、そのアドレスの会員がいないときだけ、どのアドレスへの操作かが分かるようにメールアドレスを残しています（21章）。例外は、この2つだけです。
- **残す期間**：`config/cleanup.php` の `operation_log_days`（365日）。過ぎた行は、一時データの後片付け（19章）が消します。日数は、操作ログの一覧の上にも出します。
- **取らないとき**：`operation_log_days` を `0` にすると、操作ログを取りません。操作ログを求められない案件のためです。それまでに溜まった行は、片付けでは消さずに残ります。日々の報告のメールも、気になる点が出ないので届きません。IPブロック管理が自動で止めたことは、`warning` のログとエラーの通知のメール（20章）で分かります。

### 管理画面の回数の制限（AdminRequestLimit）

**ファイル**：`packages/neobit/core/src/Security/AdminRequestLimit.php`（冒頭のコメント）・`routes/web.php`（管理画面のグループの `throttle:admin-screen`）

ログイン後の管理画面の全体に、スタッフごとの回数の制限を掛けています。一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止めるためです。

- **回数**：`config/neobit.php` の `admin_requests_per_minute`（1分に120回）。人が普通に使って届かない数にします。画面の表示のほか、アップロードや、送信中の画面の読み直しも1回に数えます。`0` にすると、制限を掛けません。
- **数える単位**：操作しているスタッフの id です。同じ事務所から複数の人が使っても、互いに影響しません。
- **超えたとき**：その回は処理せず、少し待つよう案内する画面（`admin/too_many_requests.blade.php`）を返します。案内の文は、`config/neobit.php` の `admin_requests_exceeded_message` に書きます。アップロードのような JavaScript からの送信には、同じ文を JSON で返します。操作ログに「回数の制限」を残し、`warning` のログも書きます。`ERROR_NOTIFY_LEVEL` が `warning` なら、エラーの通知のメール（20章）が届きます。件名は `【要確認：サイト名】WARNING：管理画面の操作が上限を超えました（スタッフID:3）` です。
- **記録は、スタッフごとに1分に1回まで**：続けて取り出そうとすると、超えた回数だけ記録が増えてしまうためです。
- **管理画面のルートを足すとき**：`auth:admin` のグループの内側に書けば、この制限が掛かります。足すものはありません。
- **止められないもの**：上限より遅く、時間をかけて取る動きは止まりません。そちらは、詳細の閲覧や CSV のダウンロードの記録から調べます。

### IPブロック管理（IpBlocker）

**ファイル**：`packages/neobit/core/src/Security/IpBlocker.php`（冒頭のコメント）・`src/Http/Middleware/RejectBlockedIp.php`・`src/Http/Controllers/IpBlockController.php`・`bootstrap/app.php`・`routes/web.php`

不正な URL を続けて叩く BOT を、IP アドレスで止めます。Cloudflare などの WAF や fail2ban を入れられない環境で、PHP の側で身を守るためのものです。止めた IP アドレスからのアクセスには、どのページも 404 を返します。

- **止める場所**：全体のミドルウェア `RejectBlockedIp` です。ルートの無い URL（`/.env`・`/wp-login.php` など）も、ルートを探す前にここを通ります。止めている一覧はキャッシュに置き、毎回 DB は読みません。403 ではなく 404 にし、画面もルートの無い URL を開いたときと同じ 404 の画面にします。止められていることを、相手に気付かせないためです。Apache が PHP を通さずに返すファイル（画像・CSS など）は止まりません。
- **自動で止める**：応答が 404・405 になったアクセスを、IP アドレスごとに数えます。`config/neobit.php` の `ip_block_watch_minutes`（10分）の中に `ip_block_watch_count`（30回）を超えたら、`ip_block_hours`（24時間）止めます。`ip_block_auto` を false にすると数えず、管理画面からの登録だけになります。IPv6 は `/64` のまとまりで数えて止めます。
- **止めない IP アドレス**：アクセス制限（0章）のサイトと管理画面の IP アドレスは、`.env` の分も画面の分も、自動でも手動でも止めません。管理画面からは、今の自分の IP アドレスを含むものも登録できません。管理者が自分を止めないようにするためです。
- **管理画面**：止めている IP アドレスの一覧と、手動の登録・期限の変更・解除です。IP アドレスは1つでも、`203.0.113.0/24` のようなまとまりでも登録できます。一覧と登録の欄には、実際に止まる範囲（`203.0.113.0 ～ 203.0.113.255`）を出します。まとまりは、広すぎると関係の無い人まで止めるので、IPv4 は `/16`、IPv6 は `/32` より狭いものだけにします。
- **ルート**：案件が書きます。機能を使うかどうかを、案件ごとに決めるためです。管理者だけのグループの中に、「頭.index」「頭.store」「頭.update」「頭.destroy」の組で名前を付けます。update・destroy の URL には `{ipBlock}` を入れます。

    ```php
    // routes/web.php（管理者だけのグループの中）
    Route::get('/ip-blocks', [IpBlockController::class, 'index'])->name('ip-blocks.index');
    Route::post('/ip-blocks', [IpBlockController::class, 'store'])->name('ip-blocks.store');
    Route::patch('/ip-blocks/{ipBlock}', [IpBlockController::class, 'update'])->name('ip-blocks.update');
    Route::delete('/ip-blocks/{ipBlock}', [IpBlockController::class, 'destroy'])->name('ip-blocks.destroy');
    ```

    ミドルウェアは、ルートを書かなくても動きます。管理画面を出さない案件でも、自動で止める仕組みは働きます。`bootstrap/app.php` の全体のミドルウェアに `RejectBlockedIp` を足してあることを確かめてください。
- **記録**：止めたこと・解除したこと・期限を変えたことを、操作ログに残します。自動で止めたものは、操作した人が空です。自動で止めたときは `warning` のログも書き、毎朝の操作ログの報告の「気になる点」にも載せます。期限の過ぎた行は、毎時の片付けが消します。
- **気を付けること**：手前にプロキシがある環境で `TRUSTED_PROXIES` が正しくないと、全員が同じ IP アドレスに見えます。その IP アドレスを止めると、サイト全体が止まります。IP アドレスを次々に変えて来る攻撃は止めきれません。それは WAF の領域です。

### 画面

- **一覧と検索**（`/admin/operation-logs`）：日付の範囲・操作・操作した人・対象・IP アドレス・補足で絞れます。補足は、中の語を部分一致で探します。空白で区切ると、全部の語を含む行に絞ります。CSV でも出せます。見るだけの画面で、登録・編集・削除はありません。
- **どちらの画面も、管理者だけが開けます**：記録を取っていることを、スタッフの全員に見せる必要は無いためです。管理画面のトップのリンクも、管理者にだけ出ます。
- **会員ごとの操作ログ**（`/admin/members/{id}/operation-logs`。会員の詳細画面の「操作ログ」のボタン）：その会員を対象にした操作と、その会員が行った操作だけを出します。本人が変えたのか、スタッフが変えたのか、誰が詳細を開いたのかが、時刻の順に並びます。問い合わせに答えるのに使います。こちらも管理者だけが開け、ボタンも管理者にだけ出ます。
- **一覧の画面はフレームワークにあります**（`OperationLogController`・`neobit::admin.operation_logs.index`）。ルートは案件が書きます。管理者だけのグループの中に、「頭.index」（GET）・「頭.search」（POST）・「頭.csv」（GET）・「頭.day」（POST）の組で名前を付けます。日々の報告のメールに画面の URL を載せるなら、`config/neobit.php` の `operation_log_route` に「頭.index」を書きます。会員ごとの操作ログの画面は案件の画面で、表は同じ部分ビュー（`neobit::admin.operation_logs._table`）を使います。

    ```php
    // routes/web.php（管理者だけのグループの中）
    Route::get('/operation-logs', [OperationLogController::class, 'index'])->name('operation-logs.index');
    Route::post('/operation-logs', [OperationLogController::class, 'storeSearchCondition'])->name('operation-logs.search');
    Route::get('/operation-logs/csv', [OperationLogController::class, 'csv'])->name('operation-logs.csv');
    Route::post('/operation-logs/day', [OperationLogController::class, 'searchDay'])->name('operation-logs.day');
    ```
- **CSV のダウンロードと取り込みは、対象の欄に CSV の名前を出します**（会員一覧など）。何に対する操作かを表すためです。
- **CSV と一斉メールの送信は、補足の欄に件数を出します**：`23件`、`120行（追加 0・更新 15・変更なし 105）`、`宛先 300件（送信済み 298・失敗 2）` の形です。件数は操作ログには持たせず、CSV の記録（`csv_download_logs`・`csv_import_logs`）と送信の記録（`t_bulk_mails`）から、その都度読みます（`OperationLog::relatedRecords()`）。
- **件数を押すと、内訳のモーダルが開きます**：CSV のダウンロードなら検索条件、取り込みならファイル名と件数の内訳、一斉メールなら件名と送信の結果です。中身は、記録の側が返します（`OperationLogRelatedRecord` の `operationLogDetails()`。項目の名前 => 値）。詳しい画面があれば、`operationLogUrl()` の URL へのボタンも出します（一斉メールの送信の状況の画面）。画面は記録の種類を見ないので、案件の記録もフレームワークの画面に同じ形で出せます。
- 操作ログを CSV で出したときも、「補足」の列に同じ件数が入ります。内訳は入りません。

### 件数の集計（OperationLogStats）

**ファイル**：`packages/neobit/core/src/Operations/OperationLogStats.php`　**実例**：`OperationLogController::index()`・`neobit::admin.operation_logs.index`

操作ログの一覧の上に、件数の棒グラフと、操作の多い人の表を出します。出すのは1ページ目だけです。2ページ目から先は、一覧を読み進めているので出しません。件数の多い時間帯や、ふだんと違う使われ方に気付けるようにするためです。

| 集計 | 期間 |
|---|---|
| 1時間ごとの棒 | 直近の24時間。検索で1日だけに絞っているときは、その日の0時から24時間 |
| 1日ごとの棒 | 30日ぶん（`STATS_DAYS`）。検索に開始日があればその日から、終了日だけならその日まで、どちらも無ければ今日まで。今日より先の日は出さない。棒を押すと、今の検索条件のまま、その1日に絞る |
| 操作の多い人 | 1時間ごとの棒と同じ期間。スタッフの上位3人・会員の上位3人・訪問者（`OperationLogStats::TOP_COUNT`） |

- **今の検索条件で数えます**：操作を「詳細の閲覧」に絞れば閲覧だけの棒に、スタッフで絞ればその人だけの棒になります。日付の条件だけは外し、期間は上の表のとおりに決めます。
- **棒は HTML と CSS だけで書いています**。グラフの部品は使いません。棒の高さは、いちばん多い棒を100%とした割合です。見た目は、同じ画面の `<style>` にあります。
- `OperationLogStats` のメソッドは、対象を絞った操作ログのクエリを受け取って数えます。ほかの画面で同じ集計を出すときも、クエリを渡すだけです。

### 1日1回の報告のメール（OperationLogReport）

**ファイル**：`packages/neobit/core/src/Operations/OperationLogReport.php`（冒頭のコメント）・`packages/neobit/core/src/Console/ReportOperationLogs.php`・`packages/neobit/core/resources/mail-templates/operation_log_report.blade.php`

毎朝8時に、前の日の操作ログの報告を管理者へ送ります（`routes/console.php`）。**気になる点があった日だけ**送ります。毎日届くと読まれなくなり、肝心の日に埋もれるためです。

| 気になる点 | 目安（`config/neobit.php` の `operation_report_thresholds`） |
|---|---|
| 1人が、1時間に詳細を開いた件数 | 50件以上（`views_per_hour`） |
| 1人が、1日に CSV をダウンロードした回数 | 5回以上（`csv_downloads_per_day`） |
| 1日のログインの失敗 | 10件以上（`login_failures_per_day`） |
| 管理画面の回数の制限に達した人 | 1人でもいれば |

- **宛先**：`.env` の `OPERATION_REPORT_TO`（カンマ区切りで複数書けます）。空なら送りません。エラーの通知（`ERROR_NOTIFY_TO`）は開発者、こちらは管理者と、読む人が違うので分けています。
- **件名**：`【要確認：サイト名】操作ログの報告（日付）`。頭は、エラーの通知と同じです。
- **載せるもの**：気になる点、操作の種類ごとの件数、操作の多い人です。操作ログと同じく、氏名のほかの個人情報は載せません。
- **手で動かす**：`php artisan app:report-operation-logs --date=2026-10-04 --always`。`--date` を書かなければ前の日、`--always` を付けると気になる点が無くても送ります。文面や宛先を確かめるときに使います。
- 目安の数は、サイトの規模（スタッフや会員の数、1日の操作の量）に合わせて、`config/neobit.php` の `operation_report_thresholds` で決めます。0 にすると、その項目は見ません。

### 会員情報が変わったときのお知らせメール（LoginProfileNotice）

会員がマイページで自分の情報を変えると、本人へメールで知らせます（`resources/mail-templates/member_profile_changed.blade.php`）。本人が変えたのなら記録が本人の手元にも残り、他人がログインして変えたのなら本人が気付けます。

```php
/** マイページのコントローラー */
private function afterSave(Member $member, array $validated, array $changedFields): void
{
    LoginProfileNotice::send($member, $changedFields);
}
```

- メールには、変わったことだけを書きます。どの項目が変わったかと、その値は載せません。
- 何も変わっていないときと、パスワードだけが変わったときは送りません。パスワードの変更は、`PasswordChange` が別のメールで知らせます。
- **管理画面からスタッフが変えたときは送りません**。本人から頼まれて変えることがほとんどで、知らせる必要が無いためです。誰が変えたかは、操作ログに残ります。パスワードをスタッフが変えたときのお知らせ（`PasswordChange`）は、今までどおり送ります。
- メールアドレスが変わったときは、変わる前と後の両方のアドレスに送ります。他人にアドレスを書き換えられたときに、本人が気付けるのは変わる前のアドレスだけだからです。変わる前のアドレスは、`$changedFields['email']` で受け取ります。
- 本人がメールアドレスを変えるときは、保存の前に確認コードの入力を挟みます（`EmailChange`。14章）。お知らせは、コードが入力できて保存された後に届きます。

## 23. 個人会員と企業会員（LoginAccount）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Auth/LoginAccount.php`・`IsLoginAccount.php`・`TwoFactorLogin.php`・`TeamInvitationManager.php`・`TeamInvitationOwner.php`・`TeamInvitationRelation.php`・`LoginIdMemory.php`、`config/login.php`、設計の詳細は「個人会員と企業会員を共存させる設計」（`docs/member-types-spec.md`）　**実例**：個人会員は `AuthSessionController` ほか、企業会員は `app/Http/Controllers/Company/` の下と `Admin\CompanyController`・`Admin\CompanyUserController`

訪問者側でログインする人は、個人会員と、企業の担当者の2種類です。ログインの仕組み（14章）は同じ共通部品を使い、画面・URL・コントローラーは種類ごとに分けて書きます。企業会員を使わないサイトは、企業会員のファイルを消します。

### 名前の決まり

ログインするモデルは、共通の型 `LoginAccount` を実装します。ガード・ルート・メールのテンプレート・Cookie の名前は、モデルの「種類の名前」から決まりで作り、設定には書きません。共通部品は、渡されたモデルに名前を聞くだけで、個人か企業かの場合分けを持ちません。

| もの | 決まり | 個人会員（`Member`） | 企業の担当者（`CompanyUser`） |
|---|---|---|---|
| 種類の名前 | モデルの短い名前。`AppServiceProvider` の `enforceMorphMap()` に書いた名前。操作ログなどが DB に持つ名前と同じ | `member` | `company_user` |
| ガード | 種類の名前。画面の名前に合わせるときは、定数 `LOGIN_GUARD` | `web` | `company` |
| ルートの名前 | 頭に「種類の名前.」。画面の名前に合わせるときは、定数 `LOGIN_ROUTE_PREFIX` | `login`・`mypage` | `company.login`・`company.mypage` |
| メールのテンプレート | 頭に「種類の名前_」。無ければ、頭を付けない名前、フレームワークの既定の順に探す（11章） | `member_verification_code` | `company_user_verification_code` |
| 信頼済み端末の Cookie | 「種類の名前_trusted_device」 | `member_trusted_device` | `company_user_trusted_device` |

ガードとルートは、画面の側の名前なので、種類の名前とは別に決められます。個人会員は、URL とルートの名前を短いままにするために、ガードを `web`、ルートの頭を無しにしています。企業の担当者は、企業会員の画面の URL（`/company/…`）に合わせて、ガードを `company`、ルートの頭を `company.` にしています。モデルの定数 `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX` で、決まりと違う名前にしています。

### モデルに書くもの

```php
class CompanyUser extends Authenticatable implements LoginAccount, PasskeyUser
{
    use HasPasskeys;        // パスキー（14章）
    use HasTwoFactor;       // 2段階認証・バックアップコード・信頼済み端末（14章）
    use IsLoginAccount;     // 名前の決まり

    // 種類の名前（company_user）は、モデルの短い名前から決まるので、書かない。
    // ガードとルートの頭は、企業会員の画面の名前（/company/…）に合わせている
    public const LOGIN_GUARD = 'company';
    public const LOGIN_ROUTE_PREFIX = 'company.';

    public const LOGIN_ID_TYPE = LoginIdType::Either;            // ログインに使う値。書かなければメールアドレス
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Email;     // 2段階目の方式
    public const BACKUP_CODES = true;                            // バックアップコードを使うか

    /** 画面やメールに出す名前 */
    public function displayName(): string { ... }

    /** お知らせや確認コードを送るメールアドレス。未登録なら null */
    public function notificationEmail(): ?string { ... }

    /** ログインに使う値。メールアドレスでログインする会員は、書かなくてよい */
    public function loginId(): string { ... }
}
```

### コントローラーに書くもの

共通部品には、会員のモデルを渡します。

```php
// ログインのコントローラー（14章の「2段階認証」）
use TwoFactorLogin;
private const LOGIN_ACCOUNT = CompanyUser::class;               // ガード・ルート・2段階目の方式は、ここから決まる
private const TWO_FACTOR_VIEW = 'company.auth.login-verify';    // 2段階目の画面

// パスワードが合った後は、トレイトに任せる（2段階目が要るか、信頼済みの端末か）
return $this->continueAfterPassword($request, $user, $request->boolean('remember'));
```

| したいこと | 書き方 |
|---|---|
| ログイン中の人を取る | `Auth::guard(CompanyUser::loginGuard())->user()` |
| その種類のルートへ移す | `route(CompanyUser::loginRoute('mypage'))` |
| 確認コード | `new LoginVerificationCode(CompanyUser::class)` |
| 信頼済み端末 | `TrustedDeviceManager::for($user)` |
| ログインの後の移動先 | `LoginRedirect::for(CompanyUser::class, route('company.mypage'))` |
| パスワードを変えた後始末 | `PasswordChange::resetAndNotify($user)` |
| 情報が変わったお知らせ | `LoginProfileNotice::send($user, $changedFields)` |
| メールアドレスの変更の確認 | トレイトの `holdForEmailChange($request, $user)`（`EmailChange`） |
| ログアウト | トレイトの `logoutAccount($request)` |

### ルート

```php
Route::prefix('company')->name('company.')->group(function () {
    Route::middleware('guest:company')->group(function () {
        /* 登録・ログイン・パスワードの再設定 */

        // 招待された人の登録。ログイン中の人が開いてもマイページへ移さないよう、guest:companyを外す
        Route::middleware('throttle:20,1,company-invitation')->withoutMiddleware('guest:company')->group(function () { /* ... */ });
    });

    // 2段階目と、初回のログインでの登録。1段階目の後の中間の状態なので、ミドルウェアでは守らない
    Route::get('/login/verify', ...);

    Route::middleware(['auth:company', 'auth.session', 'company.approved'])->group(function () { /* マイページ */ });
});
```

- `company.approved`（`EnsureCompanyIsApproved`）は、企業が承認済みかを、画面を開くたびに確かめます。運営が企業を止めると、ログイン中の担当者も次の操作でログアウトになります。
- ログインしていないときの移動先は、`bootstrap/app.php` で、ルートの名前の頭（`company.`）から決めています。

### 企業会員の画面

| 画面 | コントローラー | 中身 |
|---|---|---|
| 登録 | `Company\RegistrationController` | 企業と最初の担当者を「申請中」で作る。確認コードでメールアドレスを確かめ、運営へメールで知らせる |
| ログイン | `Company\AuthSessionController` | 企業ID・担当者ID・パスワード。担当者ID の欄には、メールアドレスを入れてもよい（`CompanyUser::LOGIN_ID_TYPE`）。「企業ID と担当者ID を記憶する」（`LoginIdMemory`） |
| 初回のログインでの登録 | `Company\FirstLoginSetupController` | 既存のシステムから移した企業だけ。企業の情報を1つ照合して、氏名・メールアドレス・担当者ID・パスワードを決める |
| マイページ・企業の情報の変更 | `Company\MypageController` | どの担当者も変えられる。パスキーの管理も持つ |
| 自分の情報の変更 | `Company\ProfileController` | 氏名とメールアドレス。メールアドレスが変わるときは、新しいアドレスへの確認コードの入力を挟む（`EmailChange`） |
| パスワードの変更・再設定 | `Company\AuthPasswordController`・`Company\PasswordResetController` | 再設定は、企業ID とメールアドレスの2つが合う人に確認コードを送る。担当者ID を忘れた人も、再設定できる |
| 担当者の管理 | `Company\UserController` | 一覧・招待・ほかの担当者の編集と削除 |
| 招待された人の登録 | `Company\InvitationController` | 招待のメールのリンクから、担当者ID・氏名・パスワードを決める |
| 管理画面：企業会員 | `Admin\CompanyController` | 一覧・登録・詳細・編集・CSV、承認・却下・停止・再開・削除、招待のメールの送信 |
| 管理画面：担当者 | `Admin\CompanyUserController` | 確認・編集・削除 |

### 企業会員の決まりごと

- **企業と担当者に分かれます**。企業（`Company`）は情報を持つだけで、ログインするのは担当者（`CompanyUser`）です。担当者に権限の区別は無く、どの担当者も同じことができます。誰が行ったかは、操作ログ（22章）で追います。
- **企業ID**（`t_companies.code`）は、新しく登録した企業では id と同じ番号が自動で入ります。始まりの番号は `app:set-next-id`（16章）で決めます。既存のシステムから移した企業には、今までのログインID を入れます。
- **担当者ID**（`t_company_users.login_id`）は、企業の中でだけ重ならなければよい値です。使える文字は `CompanyUser::LOGIN_ID_PATTERN` です。**メールアドレスも、同じ企業の担当者どうしで重ならないようにします**。確認コードとパスワードの再設定のメールが、本人だけに届くようにするためです。画面からの登録・変更では検証のルール（`CompanyUser::uniqueEmailInCompany()`）で確かめ、DB にも同じ内容の制約があります。別の企業の担当者と同じアドレスは、使えます。既存のシステムのデータに、同じ企業で同じアドレスの担当者がいるときは、持ち込む前に直します。1人を残して、ほかの人の分を空にしておけば、その人たちは初回のログインで自分のアドレスを登録します。
- **状態**（`CompanyStatus`）は、申請中・承認済み・停止の3つです。ログインできるのは、承認済みの企業の担当者だけです。企業の側が登録した企業は申請中、運営が管理画面で登録した企業は承認済みで作られます。
- **企業の側に、退会の画面はありません**。退会の連絡を受けたら、運営が管理画面で停止してから削除します。削除できるのは、停止の企業だけです。承認済みの企業を、押し間違いで消さないようにするためです。削除すると、企業・担当者・招待の行と、担当者の信頼済み端末・パスキーが消えます。
- **担当者は、招待のメールで足します**（`TeamInvitationManager`）。招待する側が担当者ID やパスワードを決めて渡すことはしません。パスワードを本人のほかに知っている人を作らないためです。運営が企業を登録するときも、最初の担当者へ招待を送ります。
- **招待のリンク**は7日間有効で、1回使うと消えます。DB には、リンクに入れた値のハッシュ値だけを持ちます（`team_invitations`）。期限の切れた招待は、後片付け（19章）が消します。
- **誰が招待したかを、招待の行に残します**（`inviter_type`・`inviter_id`）。担当者のことも、管理画面のスタッフのこともあります。招待中の一覧に「招待した人」として出します。スタッフが送った招待は、メールの文面と企業の側の画面では「運営」と出し、スタッフの氏名は出しません。管理画面では、スタッフの氏名も出します。
- **送り直せるのは、招待した本人だけです**。送り直しは、同じ番号の招待で、メールを送り直す操作です。ほかの担当者やスタッフが同じ宛先を招きたいときは、新しく招待します。前の招待は自動で消えて、新しい番号の招待になり、新しく送った人が、招待した人になります。**取り消しは、本人でなくてもできます**。宛先を間違えた招待を、送った人がいないときにも止められるようにするためです。
- **操作ログに、招待の番号を残します**。招待の送信・取り消しと、招待からの登録の記録の補足に、`招待ID:12` の形で残します。宛先のメールアドレスは残しません。操作ログの補足の検索（22章）で番号を入れると、誰が送った招待から誰が登録したかを、たどれます。
- **招待のリンクを開いたとき、ほかの担当者のログインが残っていれば、終わらせます**。同じパソコンを複数の人で使っていて、前の人がログアウトしていなかった場合です。何も知らせずに、企業会員のログインだけを終わらせて、登録の画面を出します。管理画面や個人会員のログインは残します。終わらせるのは、使える招待のリンクのときだけです。期限切れや、でたらめな値のリンクでは、終わらせません（`Company\InvitationController`）。
- **招待の部品は、企業に限りません**。`TeamInvitationManager` は、招待する集団を共通の型（`TeamInvitationOwner`）で受け取ります。集団のモデルに、インターフェースとトレイトを付け、`invitedAccountClass()` を書きます。招待のメールのテンプレート（`種類の名前_invitation`）と、登録の画面のルート（`種類の名前.invitation.show`）は、そのモデルの種類の名前から決まります。メールには、サイトの名前（`site_name`）と、招待した人の氏名（`inviter_name`）を渡します。招待した人は、招待を作ったときに、その画面でログインしていた人です。管理画面のスタッフが招待したときは、氏名を出さず、空で渡します。送り直しを本人だけにする確かめは、呼ぶ側のコントローラーが `$invitation->isSentBy()` で行います。リンクから登録する画面は、項目が集団ごとに違うので、プロジェクトのコントローラーに書きます（実例：`Company\InvitationController`）。

    ```php
    class Company extends Model implements TeamInvitationOwner
    {
        use TeamInvitationRelation;                    // invitations() の関連

        public static function invitedAccountClass(): string
        {
            return CompanyUser::class;                 // 招待から登録される人のモデル
        }
    }

    TeamInvitationManager::invite($company, $email);   // 招待を作って、メールを送る
    ```
- **お知らせのメール**は、次のときに送ります。運営が管理画面から氏名やメールアドレスを変えたときは、送りません。

| メール | テンプレート | 送るとき |
|---|---|---|
| 確認コード | `company_user_verification_code` | 登録・ログイン・パスワードの変更と再設定・パスキーの登録・初回のログインでの登録・自分のメールアドレスの変更 |
| 申請の通知（運営宛） | `company_registration_staff` | 企業の側が登録したとき。宛先は `.env` の `COMPANY_REGISTRATION_STAFF_EMAIL` |
| 承認・却下 | `company_approved`・`company_rejected` | 運営が承認・却下したとき。停止と再開では送らない |
| 招待 | `company_user_invitation` | 担当者か運営が招待したとき、送り直したとき |
| パスワードの変更 | `company_user_password_changed` | 本人か運営がパスワードを変えたとき |
| 担当者の情報の変更 | `company_user_profile_changed` | 本人か、同じ企業のほかの担当者が、氏名やメールアドレスを変えたとき |

### 既存のシステムから会員を移すサイト

既存のシステムからデータを移すサイトだけで働く機能が、2つあります。新しく始めるサイトでは、対象の会員がいないので、何も起きません。初回のログインでの登録は、コントローラーとルートを消しても構いません。

| 機能 | 設定（`config/login.php`） | 働く条件 |
|---|---|---|
| 古い方式のパスワードの置き換え（14章） | `legacy_passwords` | `legacy_password` に値がある会員 |
| 初回のログインでの登録 | `identity_check_column` | メールアドレスが空の担当者 |

- 移した企業は、企業1件にログインID とパスワードが1組であることが多いので、企業ごとに「最初の担当者」を1人だけ作ります。担当者ID は全部の企業で同じ初期値にし、氏名とメールアドレスは空にします。
- 最初の担当者がログインすると、2段階目の代わりに、初回のログインでの登録へ回ります。企業のデータにある値を1つ（`identity_check_column`。見本のサイトでは電話番号）照合してから、入力されたメールアドレスに確認コードを送ります。既存の ID とパスワードは漏れている前提で扱い、それだけを知っている他人に登録させないためです。
- 照合する列を変えたら、登録の画面（`company/auth/login-setup.blade.php`）の入力欄の見出しも合わせて直します。
- 照合する列の値が空の企業は、本人では登録できません。スタッフが、管理画面の担当者の編集からメールアドレスを入れます。
- 手元で試すための見本（企業ID `legacy`）は、`CompanySeeder` にあります。

### 企業会員を使わないサイト

個人会員だけのサイトでは、次を消します。パスキーを使わないサイトで、該当のルートを消すのと同じ扱いです。

| 場所 | 消すもの |
|---|---|
| `app/Http/Controllers/` | `Company/` の下の全部、`Admin/CompanyController.php`・`Admin/CompanyUserController.php` |
| `app/Models/`・`app/Enums/` | `Company`・`CompanyUser`、`CompanyStatus` |
| `app/Http/Middleware/` | `EnsureCompanyIsApproved` |
| `resources/` | `views/company/`・`views/layouts/company.blade.php`・`views/admin/companies/`・`views/admin/company_users/`、`mail-templates/company_*.blade.php`、管理画面の左のメニュー（`admin/_menu.blade.php`）の「企業会員」 |
| `routes/web.php` | 企業会員のグループと、管理画面の企業会員管理のルート |
| `config/` | `auth.php` の `company` のガードとプロバイダー、`login.php` の `company_user` |
| `bootstrap/app.php` | 移動先の `company.*` の行、`company.approved` の短い名前 |
| `database/` | 企業（`t_companies`）と担当者（`t_company_users`）のテーブルのマイグレーション、`CompanySeeder` と、`DatabaseSeeder` の呼び出し。招待のテーブル（`team_invitations`）は、共通のテーブルなので残す |
| `tests/Feature/Company/` | 全部 |

`packages/neobit/core/` の共通部品は、消しません。`LoginIdMemory`（ログイン画面の入力の記憶）や、招待の部品（`TeamInvitationManager` ほか）は、今は企業会員だけが使っていますが、企業に限らない部品です。

共通部品の中にも、企業会員の名前を書いた行があります。残しておいても動きますが、消すなら次の所です。

- `AppServiceProvider` の `enforceMorphMap()` の `company`・`company_user`
- `AppOperationLogModel` の `Company`・`CompanyUser`
- `OperationLogAction` の `InvitationSend`・`InvitationCancel`

### 個人会員をログインID でログインさせるサイト

ログインに使う値は、モデルの定数 `LOGIN_ID_TYPE`（`Neobit\Core\Enums\LoginIdType`）で選びます。見本のサイトでは、個人会員はメールアドレス（`Email`）、企業の担当者は、担当者ID でもメールアドレスでも入れる形（`Either`）です。ログインのコントローラーは、`Member::loginIdCondition($入力された値)` で、探す条件（`[列の名前 => 値]`）をモデルに聞きます。定数を変えれば、探す列が切り替わります。個人会員をログインID でログインさせるには、定数のほかに、ログインID の列と、入力させる画面が要ります。直す所は、次のとおりです。

| 直す所 | 中身 | 写す元 |
|---|---|---|
| `t_members` | `login_id` の列を足し、重ならない制約を付ける。メールアドレスの重ならない制約は、そのまま残す | `t_company_users` |
| `Member` | 定数 `LOGIN_ID_TYPE` を、`LoginIdType::LoginId`（ログインID だけ）か `LoginIdType::Either`（ログインID でもメールアドレスでも）にする。`loginId()` を書いて `login_id` を返す | `CompanyUser` |
| ログイン | 入力欄の名前と検証を、ログインID に合わせる。会員を探す列は、`Member::loginIdCondition()` が定数から決めるので、書き換えない | `Company\AuthSessionController::store()` |
| パスワードの再設定 | メールアドレスだけで本人が決まるので、今の画面のままでよい。ログインID も入力させる形にするなら、写す元を見る | `Company\PasswordResetController` |
| 会員登録 | ログインID を入力させ、重なりを確かめる。メールアドレスの重なりも、今までどおり確かめる | `Company\RegistrationController` |
| マイページ・管理画面の会員 | ログインID を変えさせるかを決める。メールアドレスの重なりの検証は、そのまま残す | `Company\ProfileController` |
| 初回のログインでの登録 | メールアドレスが空の会員を移すサイトだけ。会員のデータにある値を1つ（生年月日や電話番号）照合する | `Company\FirstLoginSetupController` |

- ログインID は、個人会員の中で重ならないようにします。**メールアドレスも、今までどおり重ならないようにします**。確認コードとパスワードの再設定のメールが、本人だけに届くようにするためです。既存のシステムのデータに、同じアドレスの会員がいるとき（家族で1つのアドレスを使っている、など）は、持ち込む前に直します。1人を残して、ほかの人の分を空にしておけば、その人たちは初回のログインで自分のアドレスを登録します。
- 「メールアドレスでログインする人」と「ログインID でログインする人」が混ざるデータは、ログインID の列にそろえます。メールアドレスでログインしていた人には、メールアドレスをログインID として入れ、入力欄は「ログインID またはメールアドレス」の1つにします。
- **`LoginIdType::Either` は、入力に `@` があればメールアドレス、無ければログインID として探します**。2つの列を「または」でつないで探すことは、しません。ある人のログインID と、別の人のメールアドレスが同じ文字列だったときに、どちらの人かを決められないためです。そのため、ログインID には `@` を使わせません（企業の担当者ID は `CompanyUser::LOGIN_ID_PATTERN`）。

### 会員の種類をもう1つ足すとき

代理店のような3つ目が要るようになったら、企業会員のモデル・コントローラー・画面・ルートを写して作ります。共通部品は、種類の名前をモデルから聞いて動くので、次の所に名前を足すだけです。

1. モデルに `LoginAccount`・`IsLoginAccount` を書く。種類の名前は、3で `enforceMorphMap()` に書く、モデルの短い名前になる。ガードやルートの頭を、種類の名前と違う名前にするときは、定数 `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX` を書く。
2. `config/auth.php` に、種類の名前のガードとプロバイダーを足す。古い方式のパスワードを使うなら、`driver` を `eloquent-legacy` にする。
3. `AppServiceProvider` の `enforceMorphMap()` と、`AppOperationLogModel` に足す。操作ログに氏名を出すなら、モデルに `OperationLogNamedModel`（`operationLogNames()`）を付ける。
4. `bootstrap/app.php` の、ログインしていないときの移動先に足す。
5. その種類だけの文面にしたいメールは、テンプレートを、種類の名前を頭に付けて用意する（`種類の名前_verification_code`・`_password_changed`・`_profile_changed`・`_invitation`）。用意しなければ、頭を付けない名前のテンプレートか、フレームワークの既定が使われる（11章）。
6. `config/login.php` に、種類の名前のキーを足す。

## 付録. フレームワーク外で考慮すべきセキュリティ対策

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

フレームワークのプログラムだけでは守れず、サイトごとに、サーバー・DNS・運用の側で決めておくことをまとめます。サイトを公開する前に、4つとも確かめます。

| 対策 | 決める場所 | 放っておくと |
|---|---|---|
| パッケージの脆弱性の確認 | デプロイの手順・GitHub | 既知の穴が残ったまま動き続ける |
| 送信元の認証（SPF・DKIM・DMARC） | 送信に使うドメインの DNS | メールが迷惑メールに入る、届かない |
| バックアップと、戻す手順 | サーバー・運用 | 壊れたときに戻せない |
| 外部へ送っている情報の表示 | プライバシーポリシー | 外部送信規律に合わない |

### パッケージの脆弱性の確認

フレームワークは、Laravel をはじめ多くのパッケージの上で動いています。パッケージに脆弱性が見つかると、こちらのプログラムに誤りが無くても、攻撃の入口になります。

- **デプロイの前に調べる**：手元で次の2つを実行し、報告があれば版を上げます。

  ```bash
  composer audit
  npm audit
  ```

- **見つかったときに知らせてもらう**：GitHub のリポジトリの設定で、Dependabot の通知（Dependabot alerts）を有効にします。脆弱性が公表されると、メールで届きます。
- **版を上げるとき**：`composer update`・`npm update` は、動作を確かめてからサーバーへ送ります。サーバーの上では `composer install --no-dev`・`npm ci` だけを実行し、版を上げる操作はしません（0章）。

### 送信元の認証（SPF・DKIM・DMARC）

メールを受け取る側は、「そのドメインから送ってよいサーバーか」を DNS で確かめます。設定が無いと、確認コードやお知らせのメール、一斉メール（21章）が、迷惑メールに入ったり、届かなかったりします。大量に送る相手に、この設定を必須にしているメールのサービスもあります。

| 設定 | 中身 |
|---|---|
| SPF | そのドメインのメールを送ってよいサーバーの一覧 |
| DKIM | メールに付ける署名。途中で書き換えられていないことを示す |
| DMARC | SPF と DKIM に通らなかったメールを、受け取る側にどう扱ってほしいか |

- 確かめるのは、`.env` の `MAIL_FROM_ADDRESS` に書いたアドレスのドメインです。`MAIL_RETURN_PATH`（11章）を書いたときは、SPF はそのアドレスのドメインで確かめられるので、同じドメインにします。
- レンタルサーバーの SMTP から送るときは、そのサーバーの管理画面で SPF と DKIM を有効にします。DMARC は、DNS に自分で1行足します。
- サーバーの sendmail（Postfix）から直接送ると、送信元がサーバーの IP アドレスになり、SPF に通らないことがあります。レンタルサーバーの SMTP へ転送する設定にします。
- 設定の後、Gmail などに1通送り、メールのヘッダーに `spf=pass`・`dkim=pass`・`dmarc=pass` と出ることを確かめます。

### バックアップと、戻す手順

ランサムウェア、操作の誤り、サーバーの故障に備えます。取るだけでなく、戻せることを確かめておきます。

- **取るもの**
  - DB の全部
  - アップロードしたファイル（`storage/app`）
  - `.env`。特に `APP_KEY` が無いと、暗号化して保存した列（2段階認証の秘密鍵など）を戻しても読めません。DB のバックアップとは別の場所に控えます
- **置き場所**：サーバーの外に置きます。同じサーバーの中だけに置くと、サーバーごと失われます。
- **残す世代**：何日分を残すかを決めます。壊れたことに気付くのが遅れても、壊れる前まで戻れる長さにします。
- **戻す手順**：手順を書いて、一度は実際に戻してみます。戻した先で、ログインできること、アップロードしたファイルが開けることまで確かめます。
- **個人情報の扱い**：バックアップにも個人情報が入っています。置き場所に入れる人を絞り、残す期間を過ぎたものは消します。

### 外部へ送っている情報の表示

訪問者のブラウザから、こちらのサーバーのほかにも、情報が送られています。外部送信規律（電気通信事業法）は、どこへ・何を・何のために送っているかを、訪問者に示すことを求めています。

フレームワークの部品が外部へ送るものは、次のとおりです。使っていない部品の分は、載せなくて構いません。

| 送り先 | 送るもの | 使っている所 | 目的 |
|---|---|---|---|
| jsDelivr（CDN） | IP アドレス、ブラウザの情報 | 全部の画面（Bootstrap・jQuery の読み込み） | 画面の表示 |
| Cloudflare（Turnstile） | IP アドレス、ブラウザの情報 | お問い合わせなどの訪問者向けフォーム（12章） | 機械による送信の判定 |
| zipcloud | 入力した郵便番号、IP アドレス | お問い合わせの住所の入力 | 郵便番号からの住所の検索 |

- この一覧を、プライバシーポリシーか、そこからたどれるページに載せます。
- 外部のサービスを足したとき（アクセス解析、地図、広告など）は、この一覧にも足します。
