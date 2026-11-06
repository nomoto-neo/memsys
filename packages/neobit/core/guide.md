# ネオビットフレームワーク 利用ガイド

ネオビットフレームワークは、Laravel の上に「コーナー（一覧・詳細・登録・編集・削除などをひとまとめにした管理単位）を同じ型で作るための共通部品」を載せたものです。見本のサイト（memsys）で、実際に動くコーナーを作りながら育てています。このガイドは、新しいコーナーや機能を作るときに、どの部品を使い、コントローラー・画面・ルートに何を書けばよいかを、機能ごとにまとめたものです。

- Laravel そのものの基礎（ルーティング、Eloquent、Blade、マイグレーション、バリデーションのルールなど）は含めていません。
- 各部品の細かい仕様（引数の意味、内部の動き、なぜそうしているか）は、それぞれのファイルの冒頭のコメントに書いてあります。このガイドは「どこを見て、どう組むか」の地図で、詳しくは機能ごとのページに書いたファイルを参照してください。
- 実例として、見本のサイト（memsys）に、どの機能にも動いているコーナーがあります。迷ったら実例のコントローラーと画面をそのまま写すのが一番早い方法です。

版ごとの変更は [CHANGELOG.md](CHANGELOG.md) にあります。コードを書くときの決まりは [rules.md](docs/rules.md)、案件のコードが名前で頼っている部品は [names.md](docs/names.md) にあります。

このガイドに書いたパスは、見本のサイト（memsys）のものです。案件では、`packages/neobit/core/` は `vendor/neobit/core/` にあります。

## 設置と全体

- [サイトの設置と設定](docs/setup.md)：サーバーへ送った後に実行するコマンド、画面と JS・CSS の作り直し、サーバーの権限、サイトの名前、管理画面の URL と見た目、プロキシ、セキュリティのヘッダー
- [全体像](docs/overview.md)：動作条件、コントローラーの並び順、全体に共通する約束、コメントの書き方
- [部品の一覧](docs/parts.md)：フレームワークの部品ごとの場所と概要、使い方のファイル

## コーナーを作る

- [新しいコーナーを作る手順](docs/new-corner.md)：管理画面に一覧・検索・登録・詳細・編集・削除のあるコーナーを、実例を写しながら作る手順
- [一覧・検索](docs/list.md)：検索の条件の検証と保存、並び順、ページと検索の条件の復元（`SearchableList`）
- [詳細表示](docs/detail.md)：入力欄と同じ画面を、読み取り専用で出す
- [登録・更新](docs/form.md)：入力・確認・保存の流れ（`FormFlow`）。確認画面のあり・なし、保存する値、子テーブル
- [削除](docs/delete.md)：物理削除と、論理削除・取り消し
- [トランザクションとファイルの削除](docs/transaction.md)：ファイルの削除とメールの送信を、DB の書き込みが確定してから行う
- [テーブル・モデルの決まり](docs/table-model.md)：テーブルの名前、マイグレーション、モデルの決まり
- [画面（Blade）の決まり](docs/blade.md)：レイアウト、画面ごとのスクリプト、全体の CSS

## ファイルと入出力

- [ファイルアップロード・WYSIWYG](docs/upload.md)：画像・添付ファイルと WYSIWYG の画像のアップロード（`AjaxFileUpload`）。一時ディレクトリを経て保存先へ移す
- [区分表・選択肢](docs/code-table.md)：値と名称の組。列挙型・CSV・DB のどれから作っても、同じヘルパーで使う
- [CSV ダウンロード](docs/csv-download.md)：一覧の CSV ダウンロードと、項目の定義の書き方（`CsvDownload`）
- [CSV 取り込み](docs/csv-import.md)：CSV の追加・更新の取り込み（`CsvImport`）
- [PDF 出力](docs/pdf.md)：Blade で書いた画面を PDF にする（`PdfDownload`）

## メールとフォーム

- [メール送信](docs/mail.md)：送信元・宛先・件名・本文をテンプレートのファイルで決めるメール（`MailTemplate`・`TemplatedMail`）
- [訪問者向けのフォーム](docs/public-form.md)：お問い合わせなどの訪問者向けのフォーム。二重送信の防止とスパム対策
- [キュー](docs/queue.md)：重い処理を裏で動かす。一斉メールの実例

## ログインと権限

- [権限](docs/authorization.md)：URL 単位の権限（ミドルウェア）と、1件ごとの権限（Policy）
- [ログイン認証](docs/login.md)：ログイン、確認コード、パスワードの再設定・変更、2段階認証、パスキー
- [個人会員と企業会員](docs/member-types.md)：ログインする人の種類を共存させる形（`LoginAccount`）と、種類を足す・外す手順

## 運用

- [スケジューラー](docs/scheduler.md)：定期的な処理と cron の設定。cron の動かないサーバーでの動かし方
- [エラーの通知](docs/error-notify.md)：ログに書いたエラーを、開発者へメールで知らせる（`ErrorNotifyHandler`）
- [操作ログ](docs/operation-log.md)：誰が・いつ・どこから・何に・何をしたかの記録と、毎朝の報告（`OperationRecorder`）

## サイトの守り

- [アクセス制限](docs/access-restriction.md)：サイトと管理画面を開ける IP アドレスを、管理画面から絞る
- [ベーシック認証](docs/basic-auth.md)：訪問者画面と管理画面のベーシック認証を、管理画面から切り替える
- [IPブロック管理](docs/ip-block.md)：不正な URL を続けて叩く BOT を、IP アドレスで止める（`IpBlocker`）
- [改ざん監視](docs/file-watch.md)：変わらないはずのファイルの変化と、許していないアップロードのファイルを知らせる（`FileWatcher`）
- [公開ディレクトリの HTML と古い PHP](docs/public-files.md)：公開ディレクトリに置いた HTML と古い PHP を、Laravel を通して返す（`PublicFiles`）
- [WordPress との共存](docs/wordpress.md)：Laravel を前に出し、WordPress には決まったコーナーだけを受け持たせる置き方と守り
- [フレームワーク外で考慮すべきセキュリティ対策](docs/security.md)：サーバー・DNS・運用の側で、サイトごとに決めておくこと

## 見せ方

- [SEO](docs/seo.md)：ページのタイトル・説明文・OGP・noindex と、サイトマップ（`Seo`）
- [リダイレクト](docs/redirect.md)：古い URL を新しい URL へ移す 301 リダイレクトを、管理画面から登録する

## 見本のサイトにある機能

| 実例 | 使っている機能 |
|---|---|
| 訪問者向けニュース（`NewsController`） | ログイン不要の一覧・検索・詳細、会員限定の記事（ログイン中の会員にだけ見せる）、掲載期間（掲載開始日時・掲載終了日時。表示するたびに今の時刻と比べるので、スケジューラーは使わない）。条件は `News::visibleTo()`・`isVisibleTo()` にまとめてある |
| 訪問者向け固定ページ（`PageController`） | URL の名前（`/aboutus`・`/service/price` のように、`/` を含む名前も使える）が、そのままパスになるページ。WYSIWYG で書いた本文の表示 |
| お問い合わせ（`ContactController`） | 訪問者向けの入力・確認・送信、添付ファイル、メール送信、二重送信防止、スパム対策 |
| 会員の認証まわり（`AuthSessionController` ほか） | ログイン、確認コード、パスワード再設定・変更、会員登録、退会、パスキー |
| マイページ（`MypageController`） | 確認画面なしの編集（FormFlow）、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書）、退会、パスキー |
| 企業会員（`app/Http/Controllers/Company/` の下） | 企業と担当者、企業ID・担当者ID でのログイン、登録と運営の承認、担当者の招待、既存のシステムから移したデータの初回のログイン（[member-types.md](docs/member-types.md)） |
| 管理画面のログイン（`Admin\AuthSessionController` ほか） | TOTP・バックアップコード、信頼済み端末、パスキー |
| 管理画面：ニュース（`Admin\NewsController`） | 一覧・検索、登録・編集・確認画面、削除、アップロード（単数・複数）、WYSIWYG、CSVダウンロード・取り込み（追加あり）、記事の状態で見せ方の変わるファイル（一般公開・会員限定・非表示） |
| 管理画面：固定ページ（`Admin\PageController`） | 一覧・検索、登録・編集・確認画面、削除、WYSIWYG（SunEditor。ニュースは summernote）、本文に入れる画像のアップロード |
| 管理画面：会員（`Admin\MemberController`） | 一覧・検索、詳細・編集・確認画面、区分表（都道府県）、CSVダウンロード・取り込み（更新だけ）、`@名前` の列、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書） |
| 管理画面：企業会員（`Admin\CompanyController`・`Admin\CompanyUserController`） | 一覧・検索、詳細・編集・確認画面、CSVダウンロード、列挙型の状態をボタンで変える操作（承認・却下・停止・再開）と、その操作ログ、お知らせのメール、企業の詳細から入る担当者の確認・編集・削除（親の id を URL に持つコーナー） |
| 管理画面：スタッフ（`Admin\StaffController`） | 一覧・検索、登録・編集、論理削除と取り消し、権限（Policy）、列挙型、パスワード |
| 管理画面：カテゴリー（`Admin\CategoryController`） | 確認画面なしの登録・編集、並び替え、ページ分けしない一覧 |
| 管理画面：項目見出し一覧（フレームワークの `CodeTableController`） | DBで管理する区分表の編集（複数行をまとめて保存、行の追加・削除・並び替え） |
| 管理画面：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`・`app/Services/MailUnsubscribe.php`） | キュー（宛先の数だけのジョブを1つのバッチにして積む、送る速さの制限、進み具合の表示）、宛先の CSV の読み込みと検証（`CsvRead`）、氏名の差し込み、添付ファイル、配信停止（[queue.md](docs/queue.md)） |
| 管理画面：操作ログ（フレームワークの `OperationLogController`） | 登録・編集・削除の無い、見るだけの一覧。検索（日付の範囲・補足の中の語など、列と直接比べられない条件）、件数の棒グラフと、棒を押しての絞り込み、CSVダウンロード（[operation-log.md](docs/operation-log.md)） |

## まだ無い機能（今後の予定）


自動テストは、認証の流れ（個人会員とスタッフのログイン、パスワードの変更と再設定、会員登録。`tests/Feature/Auth/`）と、企業会員（登録・ログイン・マイページ・招待・初回のログインでの登録と、管理画面の企業会員と担当者。`tests/Feature/Company/`）にあります。パスキーと、ほかの管理画面のコーナーは、まだです。そろってきたら、ページを足します。

テストは `php artisan test` で動かします。テスト用の DB（`memsys_testing`。`phpunit.xml` に書いてあります）を、手元の DB のサーバーに作っておきます。テストのたびにテーブルを作り直すので、ふだんの DB（`memsys_local`）とは別にします。

```sql
CREATE DATABASE memsys_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON memsys_testing.* TO 'memsys'@'localhost';
```

## このガイドの直し方

- 共通部品を足したり、使い方が変わったりしたら、`docs/` の当てはまるファイルを直します。直したことは、`CHANGELOG.md` の次の版の欄にも書きます。
- 機能を足したら、`docs/` にファイルを足し、この入口の当てはまるまとまりに1行足します。ファイルの名前には番号を付けません。並びはこの入口で決めます。
- ファイルの頭には、見出しの下に、ほかのファイルと同じ「利用ガイドへ戻る」の1行（`<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>`）を写します。Markdown には右寄せの書き方が無いため、HTML で書きます。
- 新しい機能のファイルは、「ファイル・実例 → コントローラーに書くもの → ルート → 画面 → 決まりごと」の順にそろえます。
- ほかのファイルを指すときは、`[ip-block.md](ip-block.md)` のように、ファイルの名前でリンクします。
- 細かい仕様は各ファイルの冒頭のコメントに書き、このガイドには「どこを見て、どう組むか」だけを書きます。同じことを2か所に詳しく書くと、片方だけ直して食い違うためです。
