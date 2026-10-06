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
- [23. 個人会員と企業会員（MemberAccount）](#23.%20個人会員と企業会員（MemberAccount）)
- [付録. フレームワーク外で考慮すべきセキュリティ対策](#付録.%20フレームワーク外で考慮すべきセキュリティ対策)

## 版の履歴

| 版 | 日付 | 内容 |
|---|---|---|
| 第1版 | 2026-09-29 | 最初の版（一覧・検索、詳細、登録・更新、削除、アップロード・WYSIWYG、区分表、CSVダウンロード・取り込み、メール、お問い合わせ、権限、ログイン認証） |
| 第2版 | 2026-10-05 | ネオビットフレームワークの基本機能を実装 |
| 第2.1版 | 2026-10-05 | 新しく登録する行の id の始まりを決めるコマンド（`app:set-next-id`）を追加（16章） |
| 第2.2版 | 2026-10-05 | 自動テストを、認証の流れから書き始めた。テスト用の DB の作り方を追加（0章） |
| 第2.3版 | 2026-10-05 | 会員の共通の型（`MemberAccount`）と、ログインの流れのトレイト（`MemberLogin`）を追加。ログアウトを、そのガードの分だけにした（`LoginSession`）。ログインの必要なルートには、ガードの名前を省かずに書く（14章） |
| 第2.4版 | 2026-10-05 | 既存のシステムから移した会員の、古い方式のパスワードの置き換え（`LegacyPasswordUserProvider`・`config/members.php`）を追加（14章） |
| 第2.5版 | 2026-10-05 | 企業会員の土台（企業と担当者、企業ID・担当者ID・パスワードでのログイン）を追加。確認コードは、会員のモデルを渡して作る（`new MemberVerificationCode(Member::class)`）。章は、企業会員がそろってから書く（14章） |
| 第2.6版 | 2026-10-05 | 企業会員のマイページ（企業の情報の変更、自分の情報の変更、パスワードの変更と再設定、パスキーの管理）を追加。共通部品は変えていない（14章） |
| 第2.7版 | 2026-10-05 | 企業会員の登録（確認コード、申請中での作成）と、管理画面の企業会員の管理（一覧・詳細・編集・承認・却下・停止・再開）を追加。共通部品は変えていない（14章） |
| 第2.8版 | 2026-10-05 | 管理画面の、企業会員の担当者の確認・編集・削除を追加。共通部品は変えていない（14章） |
| 第2.9版 | 2026-10-05 | 企業会員の担当者の招待（`CompanyInvitationManager`）を追加。企業の側の担当者の管理（一覧・招待・編集・削除）、招待された人の登録、管理画面からの企業の登録と招待。期限の切れた招待を、後片付けの対象に足した（14章・19章・22章） |
| 第2.10版 | 2026-10-05 | コメントの書き方（残すコメント・書かないコメント・置く場所）を追加（1章の 1-5） |
| 第2.11版 | 2026-10-05 | 既存のシステムから移した企業の、初回のログインでの登録（`Company\FirstLoginSetupController`）を追加。確認コードの使い道に、初回のログインでの登録を足した。照合する列は `config/members.php` の `identity_check_column`（14章） |
| 第2.12版 | 2026-10-05 | 個人会員と企業会員の章（23章）を追加。名前の決まり、モデルとコントローラーに書くもの、企業会員の画面と決まりごと、企業会員を使わないサイトで消すもの、個人会員をログインID でログインさせる手順、会員の種類の足し方。14章の区分の表に企業会員を足し、企業会員の説明を23章へ移した |
| 第2.13版 | 2026-10-05 | 管理画面に、停止した企業会員の削除を追加。企業の側の退会の画面は作らず、運営が停止してから削除する（23章） |
| 第2.14版 | 2026-10-05 | 入力の全角と半角の揺らぎをそろえる処理（`InputNormalizer`・`NormalizeInput`）を追加。画面の入力と CSV 取り込みに、検証の前に掛かる。そろえない項目は、モデルの `RAW_INPUT_FIELDS` に書く。フリガナの検証ルール（`KatakanaRule`・`HiraganaRule`）を追加（5章・10章） |
| 第2.15版 | 2026-10-06 | メールアドレスの変更に、確認コードの入力を挟むトレイト（`EmailChange`）を追加。個人会員のマイページと、企業会員の担当者の「自分の情報の変更」で使う。確認コードの使い道に、メールアドレスの変更を足した（14章・22章・23章） |
| 第2.16版 | 2026-10-07 | ブラウザに守り方を伝えるヘッダーを、全部の応答に付けるミドルウェア（`SecurityHeaders`）を追加。HTTPS だけで開かせる指定は、HTTPS で届いたときだけ付く（0章） |
| 第2.17版 | 2026-10-07 | お知らせメール（一斉メール）の配信停止（`MailUnsubscribe`）を追加。個人会員に「受け取る・受け取らない」（`NoticeMail`）を足し、会員登録・マイページ・管理画面・CSV で変えられる。一斉メールの末尾とヘッダーに配信停止の URL を付け、「受け取らない」の会員が宛先にあれば送らせない。`TemplatedMail` にヘッダーを足せるようにした（21章・22章） |
| 第2.18版 | 2026-10-07 | 接続元の IP アドレスの制限を追加。サイト全体（`SITE_ALLOWED_IPS`。メンテナンス中の画面を出す）と、管理画面（`ADMIN_ALLOWED_IPS`。404 を返す）（0章） |
| 第2.19版 | 2026-10-07 | お問い合わせと添付ファイルを、残す日数（`INQUIRY_KEEP_DAYS`）を過ぎたら自動で消すようにした（19章） |

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
| 管理画面：企業会員（`Admin\CompanyController`） | 一覧・検索、詳細・編集・確認画面、CSVダウンロード、列挙型の状態をボタンで変える操作（承認・却下・停止・再開）と、その操作ログ、お知らせのメール |
| マイページ（`MypageController`） | 確認画面なしの編集（FormFlow）、ログインした人だけが見られるアップロード（顔写真）、PDF（履歴書）、退会、パスキー |
| 管理画面：スタッフ（`Admin\StaffController`） | 一覧・検索、登録・編集、論理削除と取り消し、権限（Policy）、列挙型、パスワード |
| 管理画面：カテゴリー（`Admin\CategoryController`） | 確認画面なしの登録・編集、並び替え、ページ分けしない一覧 |
| 管理画面：項目見出し一覧（`Admin\CodeController`） | DBで管理する区分表の編集（複数行をまとめて保存、行の追加・削除・並び替え） |
| お問い合わせ（`ContactController`） | 訪問者向けの入力・確認・送信、添付ファイル、メール送信、二重送信防止、スパム対策 |
| 訪問者向けニュース（`NewsController`） | ログイン不要の一覧・検索・詳細、会員限定の記事（ログイン中の会員にだけ見せる）、掲載期間（掲載開始日時・掲載終了日時。表示するたびに今の時刻と比べるので、スケジューラーは使わない）。条件は `News::visibleTo()`・`isVisibleTo()` にまとめてある |
| 企業会員（`app/Http/Controllers/Company/` の下） | 企業と担当者、企業ID・担当者ID でのログイン、登録と運営の承認、担当者の招待、既存のシステムから移したデータの初回のログイン（23章） |
| 会員の認証まわり（`AuthSessionController` ほか） | ログイン、確認コード、パスワード再設定・変更、会員登録、退会、パスキー |
| 管理画面のログイン（`Admin\AuthSessionController` ほか） | TOTP・バックアップコード、信頼済み端末、パスキー |

### 改版について

- 共通部品を足したり、使い方が変わったりしたら、このガイドの該当する章を直し、冒頭の版の表に1行足します。
- 章を足したら、冒頭の目次にも1行足します。新しい章は、付録より前に置きます。目次のリンク先は、章の見出しの文言そのままで、空白だけを `%20` に変えたものです（`## 9. CSV ダウンロード（CsvDownload）` なら `#9.%20CSV%20ダウンロード（CsvDownload）`）。Obsidian で飛べる形です。章の見出しを変えたら、目次のリンクも同じ文言に直します。章の見出しのすぐ下には、右寄せの「目次へ戻る」のリンクを置きます。Markdown には右寄せの書き方が無いので、ほかの章と同じ HTML の1行（`<p align="right"><a href="#目次" ...>目次へ戻る</a></p>`）を写します。
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
- **プロキシの IP アドレスだけを、正確に書きます**。ここに書いた IP アドレスから接続した人は、IP アドレスを偽れるようになります。このヘッダーは送る側が自由に書けるためです。偽れると、試行制限を逃れたり、接続元の IP アドレスの制限をすり抜けたりできます。広い範囲や、プロキシではない機器の IP アドレスは書きません。プロキシを置いていなければ、空のままにします。
- 訪問者の側がプロキシや VPN を通してきたときは、そのプロキシの IP アドレスが残ります。本当の IP アドレスは、こちらからは分かりません。
- `.env` を変えたら `php artisan config:cache` をやり直します。
### 接続元の IP アドレスの制限（SITE_ALLOWED_IPS・ADMIN_ALLOWED_IPS）

**ファイル**：`app/Http/Middleware/RestrictSiteAccess.php`・`RestrictAdminAccess.php`（冒頭のコメント）、画面は `resources/views/maintenance.blade.php`

サイトを開ける IP アドレスを、`.env` で絞れます。どちらも、空なら制限しません。

```
# .env（カンマ区切り。範囲は 192.168.0.0/24 の形。IPv6 も書ける）
SITE_ALLOWED_IPS=203.0.113.10
ADMIN_ALLOWED_IPS=203.0.113.10,203.0.113.0/28
```

| 設定 | 範囲 | 入ってよい IP アドレスのほかから開かれたとき | 使うとき |
|---|---|---|---|
| `SITE_ALLOWED_IPS` | サイトの全部 | メンテナンス中の画面を 503 で返す | 公開前のデモの運用、メンテナンスの間 |
| `ADMIN_ALLOWED_IPS` | `/admin` の下の全部 | ログイン画面も含めて 404 を返す | 事務所などの決まった場所からしか管理画面を使わないサイト |

- **`.env` を変えたら `php artisan config:cache` をやり直します**。やり直したときに効きます。
- **書き間違えると、自分も入れなくなります**。今つないでいる IP アドレスを確かめてから書きます。入れなくなったら、サーバーで `.env` を直して `config:cache` をやり直します。画面からは直せません。
- **プロキシを前に置くときは、`TRUSTED_PROXIES` を先に書きます**。書かないと、全員がプロキシの IP アドレスに見えるので、全員が通るか、全員が止まります。
- **両方を書いたとき**：管理画面は、両方で入ってよい IP アドレスだけが開けます。
- **メンテナンス中の画面**は、レイアウトを継承しない1枚の HTML です。DB もセッションも使わないので、DB を止めている間も出せます。文言は `maintenance.blade.php` を直接書き換えます。デモの運用のときも、この画面が出ます。
- **503 で返す**のは、一時的に止めていることを検索エンジンに伝えて、登録を消されないようにするためです。
- **制限の間も通すパス**は、`bootstrap/app.php` の `RestrictSiteAccess::except()` に書きます。今は、死活監視（`/up`）と、お知らせメールの配信停止（`/mail/unsubscribe`）です。止めている間に届いたメールからも、停止できるようにするためです。
- **管理画面を 404 にする**のは、そこに管理画面があることを知らせないためです。止めたアクセスは、操作ログに残しません。
- **Apache が直接返すファイルは止まりません**。`public/build/` の中と、公開のアップロードファイル（`public/storage/`）は、Laravel を通らないためです。そこまで止めるときは、Apache の設定で絞ります。
- **コマンドは止まりません**。スケジューラー（19章）とキュー（21章）は、制限の間も動きます。メンテナンスの間に一斉メールを送りたくないときは、送信が終わってから止めます。
- Laravel の `php artisan down` は使いません。サーバーでコマンドを打つ必要があり、`.env` の IP アドレスとは別の仕組みになるためです。

### セキュリティのヘッダー（SecurityHeaders）

**ファイル**：`app/Http/Middleware/SecurityHeaders.php`（冒頭のコメント）

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
| `InputNormalizer`・`NormalizeInput` | `app/Support/`・`app/Http/Middleware/` | 入力の全角と半角の揺らぎをそろえる（画面の入力と CSV 取り込み。検証の前） | 5 |
| `SecurityHeaders` | `app/Http/Middleware/` | ブラウザに守り方を伝えるヘッダーを、全部の応答に付ける | 0 |
| `RestrictSiteAccess`・`RestrictAdminAccess`・`AllowedIps` | `app/Http/Middleware/`・`app/Support/` | 接続元の IP アドレスの制限（サイト全体はメンテナンス中の画面、管理画面は 404） | 0 |
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
| `MemberAccount`・`IsMemberAccount` | `app/Support/` | 訪問者側でログインするモデルの共通の型と、名前の決まり | 14 |
| `MemberLogin` | `app/Support/` | 会員のログインの、パスワードが合った後の流れ（確認コード・記憶済みの端末）と、ログアウト | 14 |
| `LegacyPasswordUserProvider`・`LegacyPassword` | `app/Support/` | 既存のシステムから移した会員の、古い方式のパスワードの照合と、今の方式への置き換え | 14 |
| `LoginIdMemory` | `app/Support/` | ログイン画面の入力（企業ID と担当者ID）を、ブラウザに覚えさせる | 14 |
| `CompanyInvitationManager` | `app/Support/` | 企業会員の担当者の招待（リンクの発行・送り直し・取り消し・照合） | 14 |
| `LoginSession` | `app/Support/` | そのガードだけのログアウト（同じブラウザのほかのログインは残す） | 14 |
| `LoginRedirect` | `app/Support/` | ログインの後の移動先（開こうとしていた画面へ戻す。会員と管理画面で入れ違わない） | 14 |
| `MemberVerificationCode` | `app/Support/` | メールで送る確認コード | 14 |
| `EmailChange` | `app/Support/` | 本人がメールアドレスを変えるときに、新しいアドレスへの確認コードの入力を挟む | 14 |
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
| `app:set-next-id` | `app/Console/Commands/` | 新しく登録する行の id の始まりを決めるコマンド（サイトを開ける前に使う） | 16 |
| `ErrorNotifyHandler` | `app/Support/` | ログに書いたエラーを開発者にメールで知らせる（レベル・宛先・間隔は .env） | 20 |
| `SendBulkMail` | `app/Jobs/` | 一斉メールを1通送るキューのジョブ（送る速さの制限・試し直し・バッチ） | 21 |
| `MailUnsubscribe` | `app/Support/` | お知らせメール（一斉メール）の配信停止。メールに載せる URL を作り、その URL からの停止を受け付ける | 21 |
| `OperationRecorder` | `app/Support/` | 操作ログ（誰が・いつ・どこから・何に・何をしたか）を書く | 22 |
| `OperationLogStats`・`OperationLogReport` | `app/Support/` | 操作ログの件数の集計（一覧の上のグラフと表）と、1日ぶんの報告 | 22 |
| `app:report-operation-logs` | `app/Console/Commands/` | 操作ログの報告のコマンド（スケジューラーから毎朝。気になる点があった日だけメールを送る） | 22 |
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
- **コメント**：今のコードが何をしていて、なぜそうなっているかだけを書きます。試行錯誤の経緯は書きません。書き方は 1-5 にまとめてあります。

### 1-5. コメントの書き方

コメントは、削りすぎずに「なぜ」を残します。コードを読めば、何をしているかは分かります。分からないのは、そうしている理由です。理由が書いていないと、後から触る人も AI も、不要に見える処理を消したり、よくある形に直したりして、元の狙いを壊します。

コメントの分だけ、ファイルは長くなります。それでも、理由を取り違えて作り直す手間の方がずっと大きいので、理由のコメントは惜しまずに書きます。

**残すコメント**

| 種類 | 例 | ある所 |
|---|---|---|
| そうしている理由 | メールアドレスが変わったときは、変わる前のアドレスにも送る。他人に書き換えられたときに本人が気付けるのは、変わる前のアドレスだけだから | `MemberProfileNotice` |
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
- クラスの中の説明で2行以下のものは、行コメント（`//`）にします。ブロックコメント（`/** */`）は、3行以上の説明か、`@param`・`@return` などの型の注記を書くときだけ使います。
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

### 入力をそろえる（InputNormalizer）

**ファイル**：`app/Support/InputNormalizer.php`（冒頭のコメント）・`app/Http/Middleware/NormalizeInput.php`、`app/Rules/KatakanaRule.php`・`HiraganaRule.php`

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
// モデル。データの仕様として持つ
public const RAW_INPUT_FIELDS = ['body'];

// コントローラー。モデルの指定を引く。CSV 取り込みにも効く
private const RAW_INPUT_FIELDS = News::RAW_INPUT_FIELDS;
```

- **改行を含む値も、長い値も、そろえます**。どの項目がそろえられるかを、値の中身で変えないためです。件名や管理メモのような文章も、指定しなければそろえられます。そろえたくない文章の項目は、必ず `RAW_INPUT_FIELDS` に書きます。
- 入力をそろえる時点では、どのモデルの入力かがまだ分からないので、そのリクエストを受け持つコントローラーの定数を見ます。定数を書いていないコントローラーでは、全部の項目をそろえます。ログインや検索の入力も、そろえます。
- 今、指定しているのは、ニュースの本文、一斉メールとその文面の件名・本文、お問い合わせの内容です。
- **ひらがなとカタカナは、変換しません**。フリガナの欄には `new KatakanaRule()` を付けて、ひらがなを入れたらエラーにします。通すのは、全角カタカナ・長音（`ー`）・中点（`・`）・全角と半角の空白です。英数字とハイフンは通しません。ひらがなの欄には `new HiraganaRule()` を使います。
- すでに保存されているデータは、変わりません。これから入力される分だけがそろいます。
- 探すときは、DB の照合順序（`utf8mb4_unicode_ci`）が、全角と半角・大文字と小文字・ひらがなとカタカナの違いを無視して比べます。そろえていない古いデータも、検索やログインでは見つかります。

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
- セルの値は、画面からの入力と同じく、検証の前に全角と半角をそろえます。コントローラーの `RAW_INPUT_FIELDS` に書いた項目の列は、そろえません（5章の「入力をそろえる」）。
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

**実例**：会員は `AuthSessionController`（パスワードが合った後の流れは `MemberLogin` トレイト）・`PasswordResetController`・`AuthPasswordController`・`AuthRegisteredMemberController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\TwoFactorChallengeController`

認証の画面は遷移が独特なので、FormFlow には載せず、それぞれのコントローラーで書いています。新しく作るより、実例を写して直す方が安全です。

### ガードと画面の区分

| | 個人会員 | 企業会員 | 管理画面 |
|---|---|---|---|
| ガード | `web`（`Member`、`t_members`） | `company`（`CompanyUser`、`t_company_users`） | `admin`（`Staff`、`t_staffs`） |
| ログインに使う値 | メールアドレス | 企業ID・担当者ID | ログインID |
| ログインが要る画面 | `Route::middleware(['auth:web', 'auth.session'])` | `Route::middleware(['auth:company', 'auth.session', 'company.approved'])` | `Route::middleware(['auth:admin', 'auth.session'])` |
| ログイン前だけの画面 | `guest` | `guest:company` | `guest:admin` |
| 2段階目 | メールの確認コード（`MemberVerificationCode`） | 同じ | TOTP（`TwoFactorAuthenticator`）＋バックアップコード |
| 2段階目の省略 | 「このデバイスを記憶する」（`TrustedDeviceManager::forMember()`） | 同じ | 「この端末を信頼する」（`TrustedDeviceManager::forStaff()`） |

未ログインのときの行き先・ログイン済みでゲスト専用画面に来たときの行き先は、`bootstrap/app.php` でルート名（`admin.*`・`company.*` かどうか）から振り分けています。

個人会員と企業会員は、同じ共通部品を、会員のモデルを渡して使い分けます。企業会員だけの仕組み（企業と担当者、承認、招待、移したデータの初回のログイン）と、会員の種類の足し方・外し方は、23章にまとめてあります。

### ログインの流れ（会員・管理とも同じ形）

1. ID・パスワードを `Auth::guard(...)->validate()` で確かめる（まだログインはしない）。
2. 信頼済み端末なら、そのまま `Auth::login()`。
3. そうでなければ「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ。2段階目の画面は `guest` にも `auth` にも入れず、コントローラー自身がセッションで守る。
4. 2段階目が通ったら `Auth::login()` と `session()->regenerate()`。
5. ログインが必要な画面から来た場合はその画面へ、そうでなければ既定の画面（マイページ・管理画面TOP）へ移す。移動先は `LoginRedirect::forMember(Member::class)`・`forStaff()` で決める（2・4とパスキーで通ったとき）。

開こうとしていた画面の記録（セッションの `url.intended`）は、会員と管理画面で1つしか無いので、`redirect()->intended()` を直接使わず `LoginRedirect` を通します。記録された URL がログインした側の画面（管理画面なら `admin.*` のルート）のときだけ戻り先にし、そうでなければ既定の画面へ移して、記録はもう一方の側のために残します。

記録された URL がどちらの側の画面かは、そのルートの `auth` のミドルウェアから読みます。ログインの必要なルートには、`auth:web`・`auth:admin` のように、ガードの名前を省かずに書きます。

### 会員の共通の型（MemberAccount）

訪問者側でログインするモデル（`Member`・`CompanyUser`）は、共通の型 `App\Support\MemberAccount` を実装します。認証の共通部品は、会員を `Member` の名指しではなく、この型で受け取ります。ガード・ルート・メールのテンプレート・信頼済み端末の Cookie の名前は、モデルの「種類の名前」（`MEMBER_TYPE`）から、決まりのとおりに作ります。名前の決まりと、モデル・コントローラーに書くものは、23章にあります。

### 古い方式のパスワード（LegacyPasswordUserProvider）

**ファイル**：`app/Support/LegacyPasswordUserProvider.php`（冒頭のコメント）・`app/Support/LegacyPassword.php`・`app/Support/Legacy/`・`config/members.php`

既存のシステムから会員を移したサイトだけで使います。既存のシステムのパスワードは、MD5 や SHA-1 のような古い方式のハッシュ値で持っていることが多く、元のパスワードには戻せません。古いハッシュ値のまま移し、本人が次にログインしたときに、今の方式（bcrypt）に置き換えます。会員にパスワードを決め直してもらう必要はありません。

**移すとき**

古いハッシュ値を、今の方式でもう一度ハッシュ値にして、`legacy_password` の列に入れます。`password` の列は空にします。古い方式のハッシュ値は短い時間で破られるので、新しいシステムの DB には、そのままでは置きません。

```php
$member->forceFill([
    'password' => null,
    'legacy_password' => LegacyPasswordUserProvider::wrap($oldHash),   // $oldHash は既存のシステムのハッシュ値
])->save();
```

**設定**（`config/members.php`）

```php
'member' => [
    'legacy_passwords' => [
        App\Support\Legacy\Md5Password::class,
        App\Support\Legacy\Sha1Password::class,
    ],
],
```

- 書いた順に試します。途中で方式を変えたシステムのデータも、並べておけば通ったものを採ります。
- ソルトの無い MD5・SHA-1・SHA-256 は、`App\Support\Legacy` に用意してあります。
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

ログアウトは `LoginSession::logout($request, ガードの名前)` で行います。会員のコントローラーは、`MemberLogin` トレイトの `logoutMember()` を呼びます。

- **そのガードのログインだけを終わらせます**。同じブラウザのほかのガードのログイン（会員と管理画面）は残ります。運営のスタッフが、会員の側の画面を確かめながら管理画面で操作する、といった使い方のためです。
- **ログアウトした人がセッションに残したものは、全部消えます**（検索条件、確認コードの仮置きなど）。残したほかのガードの側でも、検索条件などは消えます。
- `$request->session()->invalidate()` を直接呼ぶと、ほかのガードのログインも切れます。ログアウトの処理には使いません。

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

会員の種類は、作るときにモデルで渡します（`new MemberVerificationCode(Member::class)`）。メールのテンプレートとセッションのキーは、種類の名前から決まります。

用途（`PURPOSE_LOGIN`・`PURPOSE_PASSWORD_RESET`・`PURPOSE_MYPAGE_PASSWORD`・`PURPOSE_REGISTER`・`PURPOSE_PASSKEY`・`PURPOSE_FIRST_LOGIN`・`PURPOSE_EMAIL_CHANGE`）ごとにセッションで管理します。`issue()` で発行とメール送信、`verify()` で照合します。会員登録のようにまだ会員がいない場合と、初回のログインでの登録やメールアドレスの変更のようにメールアドレスがまだ保存されていない場合は、`issueForAddress()`・`verifyForAddress()` です。

### メールアドレスの変更の確認（EmailChange）

**ファイル**：`app/Support/EmailChange.php`　**実例**：`MypageController`・`Company\ProfileController`、画面は `mypage/email-verify.blade.php`・`company/mypage/profile-email-verify.blade.php`

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
- パスワードを変えたら、保存の直後に `PasswordChange::resetAndNotify($record, changedBy: 管理画面から変えたスタッフ)` を呼びます（トランザクションの中でよい）。信頼済み端末とパスキーをすべて無効にし、確定後に登録されているメールアドレスへお知らせを送ります（テンプレートは `member_password_changed`・`company_password_changed`・`staff_password_changed`）。スタッフの2段階認証（TOTP）は消しません。戻り値は削除したパスキーの件数で、画面のメッセージに使えます。

### 認証まわりのテーブル

信頼済み端末（`trusted_devices`）・バックアップコード（`two_factor_backup_codes`）・パスキー（`passkeys`）のようなフレームワーク内部のテーブルは、会員・スタッフで共通の1つにし、`authenticatable_type`（`'member'`・`'company_user'`・`'staff'`）と `authenticatable_id` で区別します。新しい認証対象のモデルを足したら、`AppServiceProvider` の `Relation::enforceMorphMap()` にも足します。

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

### 新しく登録する行の id の始まり（app:set-next-id）

**ファイル**：`app/Console/Commands/SetNextId.php`（冒頭のコメント）

会員などの id は、DB が自動で振る連番です。サイトを開ける前に、新しく登録する行の id を、区切りのよい番号から始めたいときは、このコマンドで決めます。既存のシステムのデータを元の id のまま取り込んだ後、新しい登録を別の範囲の番号にすれば、番号で見分けられます。

```bash
# 今の状態（いちばん大きい id と、次に振る id）を見る
php artisan app:set-next-id t_members

# 次に登録する会員の id を、100001 にする
php artisan app:set-next-id t_members 100001
```

- **今のいちばん大きい id より大きい番号にします**。小さい番号を DB に直接指定すると、DB は黙って「いちばん大きい id の次」から振るので、効いたように見えて効きません。このコマンドは、小さい番号を断ります。設定した後は、実際に効いたかを DB から読み直して確かめます。
- **戻せません**。一度大きい番号で登録されると、その下の番号には戻れません。サイトを開ける前に決めます。実行のときに確かめの質問が出ます。`--force` を付けると、質問を出さずに設定します。
- **サーバーごとに実行します**。DB が覚える値なので、開発サーバーで設定しても、本番には引き継がれません。本番のデータを取り込んだ後に、本番で実行します。
- MariaDB と MySQL だけで使えます。

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
| 企業会員の担当者の招待（14章） | `t_company_invitations` テーブル | 期限（`expires_at`）の切れた行 |
| 操作ログ（22章） | `t_operation_logs` テーブル | `OPERATION_LOG_DAYS`（既定は365日）より古い行 |
| お問い合わせ（12章） | `t_inquiries` テーブルと添付ファイル | `INQUIRY_KEEP_DAYS` より古い行と、その添付ファイル。空なら消さない |

- 一時ファイルは、アップロードと CSV 取り込みのたびにも同じ処理で消します。サーバーの cron が動いていなくても溜まり続けないようにするための控えです。
- **お問い合わせは、日数を決めたサイトだけ消します**。`.env` の `INQUIRY_KEEP_DAYS` に残す日数を書くと、過ぎた行と添付ファイルを消します。空なら、消さずに残し続けます。個人情報を、要らなくなった後も持ち続けないようにするためです。日数は、サイトの運用で決めます。1件ずつは操作ログに残さず、消した件数だけをログ（`laravel.log`）に残します。スタッフに届いたお問い合わせのメールは、消えません。
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

### お知らせメールの配信停止（MailUnsubscribe）

**ファイル**：`app/Support/MailUnsubscribe.php`（冒頭のコメント）・`app/Enums/NoticeMail.php`　**実例**：`MailUnsubscribeController`、画面は `mail_unsubscribe/show.blade.php`・`done.blade.php`

広告のメールには、配信停止の方法を載せる決まりがあります（特定電子メール法）。Gmail なども、大量に送る相手に、ワンクリックで解除できることを求めています。個人会員は、お知らせメール（一斉メール）を受け取るかどうかを持ちます。

| 場所 | できること |
|---|---|
| 会員登録 | 「受け取る」を選んだ状態で出す |
| マイページのプロフィール編集 | 本人が変える |
| 管理画面の会員 | 編集で変える。一覧を「受け取る・受け取らない」で絞る。CSV のダウンロードと取り込みにも「お知らせメール」の列がある |
| メールの中の配信停止の URL | ログインせずに「受け取らない」に変える |

- **宛先の CSV の作り方**：会員の一覧を「受け取る」で絞ってから、CSV をダウンロードして加工します。
- **「受け取らない」の会員には送れません**：宛先の CSV に、「受け取らない」の会員のアドレスがあると、確認画面でその行がエラーになり、送信のボタンが出ません。抽出した後に停止した人や、古い CSV の使い回しに備えるためです。会員ではないアドレスは、そのまま送れます。
- **配信停止の URL は、自動で付きます**：本文の末尾に、宛先ごとの URL が付きます。付ける文は、テンプレート（`resources/mail-templates/bulk_mail.blade.php`）にあります。メールソフトの「登録解除」のボタン用のヘッダー（`List-Unsubscribe`・`List-Unsubscribe-Post`）も付きます。
- **URL は署名付きです**：宛先のメールアドレスを入れて、署名を付けます。アドレスを他人のものに書き換えた URL では、停止できません。期限はありません。`APP_KEY` を変えると、それまでに送ったメールの URL は使えなくなります。
- **URL のドメインは `APP_URL` です**：メールはキューのワーカーが送るので、`.env` の `APP_URL` が、訪問者の開く URL（`https://` から）になっている必要があります。
- **開いただけでは停止しません**：URL を開くと「配信を停止する」のボタンの画面が出て、押すと停止します。受信側のウイルス検査やプレビューが、メールの中の URL を自動で開くことがあるためです。
- **画面の表示は、いつも同じです**：そのアドレスの会員がいてもいなくても、URL が正しくなくても、同じ画面を出します。そのアドレスの会員がいるかどうかを、外から確かめられないようにするためです。
- **停止の操作は、全部を操作ログに残します**（22章）：会員がいたときは、対象を会員の種類と id で残します。会員がいないときと URL が正しくないときは、どのアドレスへの操作かが分かるよう、メールアドレスを補足に残します。会員ではない宛先から停止の操作があったら、次に送る CSV から外します。
- **CSRF トークンは確かめません**：メールソフトの「登録解除」のボタンから、トークンの無い POST が届くためです（`bootstrap/app.php`）。本人のメールから来たことは、URL の署名で確かめます。
- 停止したことを知らせるメールは、送りません。本人は、マイページからいつでも「受け取る」に戻せます。
- 対象は個人会員だけです。企業会員の担当者には、この項目はありません。
- 確認コードやパスワードの変更のお知らせのように、本人の操作に応えて送るメールは、この項目に関係なく送ります。

テンプレートの見出しの行では書けないヘッダーは、`TemplatedMail` の4つ目の引数で足します。

```php
$url = MailUnsubscribe::url($email);

Mail::send(new TemplatedMail('bulk_mail', [
    // ...
    'unsubscribe_url' => $url,
], $attachments, MailUnsubscribe::headers($url)));
```

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
| 担当者の招待の送信・取り消し | `CompanyInvitationManager` | 無い |
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
- **登録・更新とは別の名前で残す**：コントローラーに `savedLogAction()` を書きます。一斉メールは、送信の記録を1件作るのが送信の始まりなので、「登録」ではなく「一斉メールの送信」として残しています。
- **一覧の表示と検索は記録しません**：1ページに出る件数が限られているためです。機械的に続けて取る動きは、下の「管理画面の回数の制限」で止めて記録します。
- **CSV 取り込みは、取り込み全体で1行です**：1件ずつの更新は記録しません。件数の内訳は取り込みの記録（`t_csv_import_logs`）が持ち、`detail` の id でつながります。CSV のダウンロードも同じです。
- **変わった列に数えない列**：`created_at`・`updated_at` などの保存のたびに変わる列と、アップロードの元のファイル名の列（`〇〇_origin`）は数えません。最後に更新したスタッフの id のように、入力とは関係なく変わる列は、モデルの定数 `OPERATION_LOG_IGNORE` に書きます（例：`Member`）。
- **変わった列は、本体のテーブルの列だけです**：多対多のような関連のテーブルの変更は、列の名前には出ません。「更新した」ことは残ります。
- **変わった項目は、列の名前のまま出します**（`phone`・`disp_flg` など）。日本語の名前の対応表は持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。
- **`detail` に個人情報と入力値を入れません**。ログインの失敗のログイン ID は、どのアカウントが狙われたかを調べるために残しています。お知らせメールの配信停止は、そのアドレスの会員がいないときだけ、どのアドレスへの操作かが分かるようにメールアドレスを残しています（21章）。例外は、この2つだけです。
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
- **CSV のダウンロードと取り込みは、対象の欄に CSV の名前を出します**（会員一覧など）。何に対する操作かを表すためです。
- **CSV と一斉メールの送信は、補足の欄に件数を出します**：`23件`、`120行（追加 0・更新 15・変更なし 105）`、`宛先 300件（送信済み 298・失敗 2）` の形です。件数は操作ログには持たせず、CSV の記録（`t_csv_download_logs`・`t_csv_import_logs`）と送信の記録（`t_bulk_mails`）から、その都度読みます（`OperationLog::relatedRecords()`）。
- **件数を押すと、内訳のモーダルが開きます**：CSV のダウンロードなら検索条件、取り込みならファイル名と件数の内訳、一斉メールなら件名と送信の結果です。検索条件は、記録してある形（検索の項目の名前と値）のまま出します。
- 操作ログを CSV で出したときも、「補足」の列に同じ件数が入ります。内訳は入りません。

### 件数の集計（OperationLogStats）

**ファイル**：`app/Support/OperationLogStats.php`　**実例**：`Admin\OperationLogController::index()`・`resources/views/admin/operation_logs/index.blade.php`

操作ログの一覧の上に、件数の棒グラフと、操作の多い人の表を出します。出すのは1ページ目だけです。2ページ目から先は、一覧を読み進めているので出しません。件数の多い時間帯や、ふだんと違う使われ方に気付けるようにするためです。

| 集計 | 期間 |
|---|---|
| 1時間ごとの棒 | 直近の24時間。検索で1日だけに絞っているときは、その日の0時から24時間 |
| 1日ごとの棒 | 今日までの30日（`STATS_DAYS`）。棒を押すと、今の検索条件のまま、その1日に絞る |
| 操作の多い人 | 1時間ごとの棒と同じ期間。スタッフの上位3人・会員の上位3人・訪問者（`OperationLogStats::TOP_COUNT`） |

- **今の検索条件で数えます**：操作を「詳細の閲覧」に絞れば閲覧だけの棒に、スタッフで絞ればその人だけの棒になります。日付の条件だけは外し、期間は上の表のとおりに決めます。
- **棒は HTML と CSS だけで書いています**。グラフの部品は使いません。棒の高さは、いちばん多い棒を100%とした割合です。見た目は、同じ画面の `<style>` にあります。
- `OperationLogStats` のメソッドは、対象を絞った操作ログのクエリを受け取って数えます。ほかの画面で同じ集計を出すときも、クエリを渡すだけです。

### 1日1回の報告のメール（OperationLogReport）

**ファイル**：`app/Support/OperationLogReport.php`（冒頭のコメント）・`app/Console/Commands/ReportOperationLogs.php`・`resources/mail-templates/operation_log_report.blade.php`

毎朝8時に、前の日の操作ログの報告を管理者へ送ります（`routes/console.php`）。**気になる点があった日だけ**送ります。毎日届くと読まれなくなり、肝心の日に埋もれるためです。

| 気になる点 | 目安（`OperationLogReport` の定数） |
|---|---|
| 1人が、1時間に詳細を開いた件数 | 50件以上（`VIEWS_PER_HOUR`） |
| 1人が、1日に CSV をダウンロードした回数 | 5回以上（`CSV_DOWNLOADS_PER_DAY`） |
| 1日のログインの失敗 | 10件以上（`LOGIN_FAILURES_PER_DAY`） |
| 管理画面の回数の制限に達した人 | 1人でもいれば |

- **宛先**：`.env` の `OPERATION_REPORT_TO`（カンマ区切りで複数書けます）。空なら送りません。エラーの通知（`ERROR_NOTIFY_TO`）は開発者、こちらは管理者と、読む人が違うので分けています。
- **件名**：`【要確認：サイト名】操作ログの報告（日付）`。頭は、エラーの通知と同じです。
- **載せるもの**：気になる点、操作の種類ごとの件数、操作の多い人です。操作ログと同じく、氏名のほかの個人情報は載せません。
- **手で動かす**：`php artisan app:report-operation-logs --date=2026-10-04 --always`。`--date` を書かなければ前の日、`--always` を付けると気になる点が無くても送ります。文面や宛先を確かめるときに使います。
- 目安の数を変えるときは、`OperationLogReport` の定数を直します。

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
- 本人がメールアドレスを変えるときは、保存の前に確認コードの入力を挟みます（`EmailChange`。14章）。お知らせは、コードが入力できて保存された後に届きます。

## 23. 個人会員と企業会員（MemberAccount）

<p align="right"><a href="#目次" data-href="#目次" class="internal-link">目次へ戻る</a></p>

**ファイル**：`app/Support/MemberAccount.php`・`IsMemberAccount.php`・`MemberLogin.php`・`CompanyInvitationManager.php`・`LoginIdMemory.php`、`config/members.php`、設計の詳細は「個人会員と企業会員を共存させる設計」（`docs/member-types-spec.md`）　**実例**：個人会員は `AuthSessionController` ほか、企業会員は `app/Http/Controllers/Company/` の下と `Admin\CompanyController`・`Admin\CompanyUserController`

訪問者側でログインする人は、個人会員と、企業の担当者の2種類です。ログインの仕組み（14章）は同じ共通部品を使い、画面・URL・コントローラーは種類ごとに分けて書きます。企業会員を使わないサイトは、企業会員のファイルを消します。

### 名前の決まり

ログインするモデルは、共通の型 `MemberAccount` を実装します。ガード・ルート・メールのテンプレート・Cookie の名前は、モデルの「種類の名前」から決まりで作り、設定には書きません。共通部品は、渡されたモデルに名前を聞くだけで、個人か企業かの場合分けを持ちません。

| もの | 決まり | 個人会員（`Member`） | 企業の担当者（`CompanyUser`） |
|---|---|---|---|
| 種類の名前 | モデルの定数 `MEMBER_TYPE` | `member` | `company` |
| ガード | 種類の名前。個人会員だけは `web` | `web` | `company` |
| ルートの名前 | 頭に「種類の名前.」。個人会員だけは付けない | `login`・`mypage` | `company.login`・`company.mypage` |
| メールのテンプレート | 頭に「種類の名前_」 | `member_verification_code` | `company_verification_code` |
| 信頼済み端末の Cookie | 「種類の名前_trusted_device」 | `member_trusted_device` | `company_trusted_device` |
| 操作ログなどでの名前 | `enforceMorphMap()` に書いた名前 | `member` | `company_user` |

個人会員のガードとルートに頭を付けていないのは、URL とルートの名前を短いままにするためです。モデルの定数 `MEMBER_GUARD`・`MEMBER_ROUTE_PREFIX` で、決まりと違う名前にしています。

### モデルに書くもの

```php
class CompanyUser extends Authenticatable implements MemberAccount, PasskeyUser
{
    use HasPasskeys;        // パスキー（14章）
    use IsMemberAccount;    // 名前の決まり

    public const MEMBER_TYPE = 'company';

    // 画面やメールに出す名前
    public function displayName(): string { ... }

    // お知らせや確認コードを送るメールアドレス。未登録なら null
    public function notificationEmail(): ?string { ... }

    // ログインに使う値。メールアドレスでログインする会員は、書かなくてよい
    public function loginId(): string { ... }

    public function trustedDevices(): MorphMany { ... }
}
```

### コントローラーに書くもの

共通部品には、会員のモデルを渡します。

```php
// ログインのコントローラー
use MemberLogin;
private const MEMBER_CLASS = CompanyUser::class;                 // ガード・ルート・テンプレートの名前は、ここから決まる
private const LOGIN_VERIFY_VIEW = 'company.auth.login-verify';   // 確認コードの入力画面

// パスワードが合った後は、トレイトに任せる（記憶済みの端末か、確認コードか）
return $this->continueAfterPassword($request, $user, $request->boolean('remember'));
```

| したいこと | 書き方 |
|---|---|
| ログイン中の人を取る | `Auth::guard(CompanyUser::memberGuard())->user()` |
| その種類のルートへ移す | `route(CompanyUser::memberRoute('mypage'))` |
| 確認コード | `new MemberVerificationCode(CompanyUser::class)` |
| 信頼済み端末 | `TrustedDeviceManager::forMember($user)` |
| ログインの後の移動先 | `LoginRedirect::forMember(CompanyUser::class)` |
| パスワードを変えた後始末 | `PasswordChange::resetAndNotify($user)` |
| 情報が変わったお知らせ | `MemberProfileNotice::send($user, $changedFields)` |
| メールアドレスの変更の確認 | トレイトの `holdForEmailChange($request, $user)`（`EmailChange`） |
| ログアウト | トレイトの `logoutMember($request)` |

### ルート

```php
Route::prefix('company')->name('company.')->group(function () {
    Route::middleware('guest:company')->group(function () { /* 登録・ログイン・パスワードの再設定・招待された人の登録 */ });

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
| ログイン | `Company\AuthSessionController` | 企業ID・担当者ID・パスワード。「企業ID と担当者ID を記憶する」（`LoginIdMemory`） |
| 初回のログインでの登録 | `Company\FirstLoginSetupController` | 既存のシステムから移した企業だけ。企業の情報を1つ照合して、氏名・メールアドレス・担当者ID・パスワードを決める |
| マイページ・企業の情報の変更 | `Company\MypageController` | どの担当者も変えられる。パスキーの管理も持つ |
| 自分の情報の変更 | `Company\ProfileController` | 氏名とメールアドレス。メールアドレスが変わるときは、新しいアドレスへの確認コードの入力を挟む（`EmailChange`） |
| パスワードの変更・再設定 | `Company\AuthPasswordController`・`Company\PasswordResetController` | 再設定は、企業ID・担当者ID・メールアドレスの3つが合う人に確認コードを送る |
| 担当者の管理 | `Company\UserController` | 一覧・招待・ほかの担当者の編集と削除 |
| 招待された人の登録 | `Company\InvitationController` | 招待のメールのリンクから、担当者ID・氏名・パスワードを決める |
| 管理画面：企業会員 | `Admin\CompanyController` | 一覧・登録・詳細・編集・CSV、承認・却下・停止・再開・削除、招待のメールの送信 |
| 管理画面：担当者 | `Admin\CompanyUserController` | 確認・編集・削除 |

### 企業会員の決まりごと

- **企業と担当者に分かれます**。企業（`Company`）は情報を持つだけで、ログインするのは担当者（`CompanyUser`）です。担当者に権限の区別は無く、どの担当者も同じことができます。誰が行ったかは、操作ログ（22章）で追います。
- **企業ID**（`t_companies.code`）は、新しく登録した企業では id と同じ番号が自動で入ります。始まりの番号は `app:set-next-id`（16章）で決めます。既存のシステムから移した企業には、今までのログインID を入れます。
- **担当者ID**（`t_company_users.login_id`）は、企業の中でだけ重ならなければよい値です。使える文字は `CompanyUser::LOGIN_ID_PATTERN` です。メールアドレスは、担当者どうしで重なっていて構いません。
- **状態**（`CompanyStatus`）は、申請中・承認済み・停止の3つです。ログインできるのは、承認済みの企業の担当者だけです。企業の側が登録した企業は申請中、運営が管理画面で登録した企業は承認済みで作られます。
- **企業の側に、退会の画面はありません**。退会の連絡を受けたら、運営が管理画面で停止してから削除します。削除できるのは、停止の企業だけです。承認済みの企業を、押し間違いで消さないようにするためです。削除すると、企業・担当者・招待の行と、担当者の信頼済み端末・パスキーが消えます。
- **担当者は、招待のメールで足します**（`CompanyInvitationManager`）。招待する側が担当者ID やパスワードを決めて渡すことはしません。パスワードを本人のほかに知っている人を作らないためです。運営が企業を登録するときも、最初の担当者へ招待を送ります。
- **招待のリンク**は7日間有効で、1回使うと消えます。DB には、リンクに入れた値のハッシュ値だけを持ちます（`t_company_invitations`）。期限の切れた招待は、後片付け（19章）が消します。
- **お知らせのメール**は、次のときに送ります。運営が管理画面から氏名やメールアドレスを変えたときは、送りません。

| メール | テンプレート | 送るとき |
|---|---|---|
| 確認コード | `company_verification_code` | 登録・ログイン・パスワードの変更と再設定・パスキーの登録・初回のログインでの登録・自分のメールアドレスの変更 |
| 申請の通知（運営宛） | `company_registration_staff` | 企業の側が登録したとき。宛先は `.env` の `COMPANY_REGISTRATION_STAFF_EMAIL` |
| 承認・却下 | `company_approved`・`company_rejected` | 運営が承認・却下したとき。停止と再開では送らない |
| 招待 | `company_invitation` | 担当者か運営が招待したとき、送り直したとき |
| パスワードの変更 | `company_password_changed` | 本人か運営がパスワードを変えたとき |
| 担当者の情報の変更 | `company_profile_changed` | 本人か、同じ企業のほかの担当者が、氏名やメールアドレスを変えたとき |

### 既存のシステムから会員を移すサイト

既存のシステムからデータを移すサイトだけで働く機能が、2つあります。新しく始めるサイトでは、対象の会員がいないので、何も起きません。初回のログインでの登録は、コントローラーとルートを消しても構いません。

| 機能 | 設定（`config/members.php`） | 働く条件 |
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
| `app/Models/`・`app/Enums/` | `Company`・`CompanyUser`・`CompanyInvitation`、`CompanyStatus` |
| `app/Support/`・`app/Http/Middleware/` | `CompanyInvitationManager`・`LoginIdMemory`、`EnsureCompanyIsApproved` |
| `resources/` | `views/company/`・`views/layouts/company.blade.php`・`views/admin/companies/`・`views/admin/company_users/`、`mail-templates/company_*.blade.php`、管理画面のトップのリンク |
| `routes/web.php` | 企業会員のグループと、管理画面の企業会員管理のルート |
| `config/` | `auth.php` の `company` のガードとプロバイダー、`members.php` の `company` |
| `bootstrap/app.php` | 移動先の `company.*` の行、`company.approved` の短い名前 |
| `database/` | 企業会員の3つのテーブルのマイグレーション、`CompanySeeder` と、`DatabaseSeeder` の呼び出し |
| `tests/Feature/Company/` | 全部 |

共通部品の中にも、企業会員の名前を書いた行があります。残しておいても動きますが、消すなら次の所です。

- `AppServiceProvider` の `enforceMorphMap()` の `company`・`company_user`
- `OperationLogSubject` の `Company`・`CompanyUser` と、`OperationLog::subjectNames()` の読み方
- `OperationLogAction` の `InvitationSend`・`InvitationCancel`
- `TemporaryDataCleaner::expiredCompanyInvitations()` と、`all()` の行
- `ErrorNotifyHandler` の `company` のガードを見る行

### 個人会員をログインID でログインさせるサイト

見本のサイトの個人会員は、メールアドレスでログインします。ログインID に切り替える設定は、持っていません。設定で切り替える形にすると、見本のサイトが使わない形のために、ログイン・パスワードの再設定・会員登録・マイページの全部に分岐が入るためです。ログインID でログインさせるサイトでは、企業会員のものを写して、個人会員のものを書き換えます。企業ID の入力が要らない分、企業会員より簡単になります。

| 直す所 | 中身 | 写す元 |
|---|---|---|
| `t_members` | `login_id` の列を足し、重ならない制約を付ける。メールアドレスの重ならない制約は外す | `t_company_users` |
| `Member` | `loginId()` を書いて `login_id` を返す | `CompanyUser` |
| ログイン | `login_id` で会員を探す。試行制限のアカウントも `login_id` にする | `Company\AuthSessionController::store()` |
| パスワードの再設定 | ログインID とメールアドレスの両方を入力させ、両方が合う人に確認コードを送る | `Company\PasswordResetController` |
| 会員登録 | ログインID を入力させ、重なりを確かめる。メールアドレスの重なりは確かめない | `Company\RegistrationController` |
| マイページ・管理画面の会員 | メールアドレスの重なりの検証を外す。ログインID を変えさせるかを決める | `Company\ProfileController` |
| 初回のログインでの登録 | メールアドレスが空の会員を移すサイトだけ。会員のデータにある値を1つ（生年月日や電話番号）照合する | `Company\FirstLoginSetupController` |

- ログインID は、個人会員の中で重ならないようにします。メールアドレスは、重なっていて構いません（家族で1つのアドレスを使っている、など）。
- 「メールアドレスでログインする人」と「ログインID でログインする人」が混ざるデータは、ログインID の列にそろえます。メールアドレスでログインしていた人には、メールアドレスをログインID として入れ、入力欄は「ログインID またはメールアドレス」の1つにします。
- 2つの列のどちらかに一致すれば通す、という形にはしません。ある人のログインID と、別の人のメールアドレスが同じ文字列だったときに、どちらの人かを決められないためです。
- メールアドレスが重なってよくなると、メールアドレスだけでは本人を決められません。パスワードの再設定で、ログインID も一緒に入力させるのはこのためです。

### 会員の種類をもう1つ足すとき

代理店のような3つ目が要るようになったら、企業会員のモデル・コントローラー・画面・ルートを写して作ります。共通部品は、種類の名前をモデルから聞いて動くので、次の所に名前を足すだけです。

1. モデルに `MemberAccount`・`IsMemberAccount` と、`MEMBER_TYPE` を書く。
2. `config/auth.php` に、種類の名前のガードとプロバイダーを足す。古い方式のパスワードを使うなら、`driver` を `eloquent-legacy` にする。
3. `AppServiceProvider` の `enforceMorphMap()` と、`OperationLogSubject`（`OPERATORS` にも）、`OperationLog::subjectNames()` に足す。
4. `bootstrap/app.php` の、ログインしていないときの移動先に足す。
5. メールのテンプレートを、種類の名前を頭に付けて用意する（`種類の名前_verification_code`・`_password_changed`・`_profile_changed`）。
6. `ErrorNotifyHandler` の、ログイン中の人を見る所に足す。
7. `config/members.php` に、種類の名前のキーを足す。

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

- 確かめるのは、`.env` の `MAIL_FROM_ADDRESS` に書いたアドレスのドメインです。
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
