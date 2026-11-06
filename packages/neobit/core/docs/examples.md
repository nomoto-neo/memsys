# 見本のサイト

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

見本のサイト（memsys）にある、実際に動くコーナーと、自動テストです。

## 実例のコーナー

| 実例 | 使っている機能 |
|---|---|
| 訪問者向けニュース（`NewsController`） | ログイン不要の一覧・検索・詳細、会員限定の記事（ログイン中の会員にだけ見せる）、掲載期間（掲載開始日時・掲載終了日時。表示するたびに今の時刻と比べるので、スケジューラーは使わない）。条件は `News::visibleTo()`・`isVisibleTo()` にまとめてある |
| 訪問者向け固定ページ（`PageController`） | URL の名前（`/aboutus`・`/service/price` のように、`/` を含む名前も使える）が、そのままパスになるページ。WYSIWYG で書いた本文の表示 |
| お問い合わせ（`ContactController`） | 訪問者向けの入力・確認・送信、添付ファイル、メール送信、二重送信防止、スパム対策 |
| 会員の認証まわり（`AuthSessionController` ほか） | ログイン、確認コード、パスワード再設定・変更、会員登録、退会、パスキー |
| マイページ（`MypageController`） | 確認画面なしの編集（FormFlow）、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書）、退会、パスキー |
| 企業会員（`app/Http/Controllers/Company/` の下） | 企業と担当者、企業ID・担当者ID でのログイン、登録と運営の承認、担当者の招待、既存のシステムから移したデータの初回のログイン（[member-types.md](member-types.md)） |
| 管理画面のログイン（`Admin\AuthSessionController` ほか） | TOTP・バックアップコード、信頼済み端末、パスキー |
| 管理画面：ニュース（`Admin\NewsController`） | 一覧・検索、登録・編集・確認画面、削除、アップロード（単数・複数）、WYSIWYG、CSVダウンロード・取り込み（追加あり）、記事の状態で見せ方の変わるファイル（一般公開・会員限定・非表示） |
| 管理画面：固定ページ（`Admin\PageController`） | 一覧・検索、登録・編集・確認画面、削除、WYSIWYG（SunEditor。ニュースは summernote）、本文に入れる画像のアップロード |
| 管理画面：会員（`Admin\MemberController`） | 一覧・検索、詳細・編集・確認画面、区分表（都道府県）、CSVダウンロード・取り込み（更新だけ）、`@名前` の列、ログインした人だけが見られるアップロード（顔写真）、子テーブル（経歴）、PDF（履歴書） |
| 管理画面：企業会員（`Admin\CompanyController`・`Admin\CompanyUserController`） | 一覧・検索、詳細・編集・確認画面、CSVダウンロード、列挙型の状態をボタンで変える操作（承認・却下・停止・再開）と、その操作ログ、お知らせのメール、企業の詳細から入る担当者の確認・編集・削除（親の id を URL に持つコーナー） |
| 管理画面：スタッフ（`Admin\StaffController`） | 一覧・検索、登録・編集、論理削除と取り消し、権限（Policy）、列挙型、パスワード |
| 管理画面：カテゴリー（`Admin\CategoryController`） | 確認画面なしの登録・編集、並び替え、ページ分けしない一覧 |
| 管理画面：項目見出し一覧（フレームワークの `CodeTableController`） | DBで管理する区分表の編集（複数行をまとめて保存、行の追加・削除・並び替え） |
| 管理画面：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`・`app/Services/MailUnsubscribe.php`） | キュー（宛先の数だけのジョブを1つのバッチにして積む、送る速さの制限、進み具合の表示）、宛先の CSV の読み込みと検証（`CsvRead`）、氏名の差し込み、添付ファイル、配信停止（[queue.md](queue.md)） |
| 管理画面：操作ログ（フレームワークの `OperationLogController`） | 登録・編集・削除の無い、見るだけの一覧。検索（日付の範囲・補足の中の語など、列と直接比べられない条件）、件数の棒グラフと、棒を押しての絞り込み、CSVダウンロード（[operation-log.md](operation-log.md)） |

## 自動テスト

自動テストは、認証の流れ（個人会員とスタッフのログイン、パスワードの変更と再設定、会員登録。`tests/Feature/Auth/`）と、企業会員（登録・ログイン・マイページ・招待・初回のログインでの登録と、管理画面の企業会員と担当者。`tests/Feature/Company/`）にあります。パスキーと、ほかの管理画面のコーナーは、まだです。そろってきたら、ページを足します。

テストは `php artisan test` で動かします。テスト用の DB（`memsys_testing`。`phpunit.xml` に書いてあります）を、手元の DB のサーバーに作っておきます。テストのたびにテーブルを作り直すので、ふだんの DB（`memsys_local`）とは別にします。

```sql
CREATE DATABASE memsys_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON memsys_testing.* TO 'memsys'@'localhost';
```
