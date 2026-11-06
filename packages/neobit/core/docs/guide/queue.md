# キュー（一斉メール）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`app/Jobs/`（ジョブ）・`routes/console.php`（ワーカーのスケジュール）　**実例**：一斉メール（`Admin\BulkMailController`・`Admin\BulkMailTemplateController`・`app/Jobs/SendBulkMail.php`）

画面の中で終わらない重い処理や、外のサーバーへの送信をたくさん行う処理は、キューに積んで裏で動かします。どのクラスを動かすかは、積む側が積むときに決めます。`app/Jobs/` のジョブのクラスを作って `dispatch()` で積むと、ワーカーが、そのクラスの `handle()` を呼びます。キューは DB（`QUEUE_CONNECTION=database`）を使い、積んだジョブは `jobs` テーブルに入って、終われば消えます。

## ワーカー

```php
// routes/console.php。毎分動かし、積まれているものが無くなったら止まる
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--sleep=0'])->everyMinute()->withoutOverlapping();
```

- スケジューラー（[scheduler.md](scheduler.md)）の cron のままで動き、Supervisor などの常駐の仕組みは要りません。毎分の cron の実行のタイミングで処理されるので、積んでから送り始めるまでに、最大1分ほど掛かります。
- cron を動かせないサーバー（`CRON_ENABLED=false`）では、ワーカーの予定も、サイトへのアクセスを契機に動きます（[scheduler.md](scheduler.md)「cron を動かせないサーバー」）。
- 手元で試すときは `php artisan queue:work --stop-when-empty` を直接実行します。

## ジョブの書き方

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
- **失敗**：試し直しても送れなかったジョブは `failed_jobs` に残り、エラーの通知（[error-notify.md](error-notify.md)）にも届きます。放置しても、7日を過ぎると `TemporaryDataCleaner` が消します（[scheduler.md](scheduler.md)）。送り直したいときは、その前に `php artisan queue:retry all` を実行してください。
- **宛先のドメインの確認**：宛先の CSV を読むときに、メールアドレスのドメインに、メールを受け取る設定（MX）かサーバーの住所（A・AAAA）があるかを DNS で確かめ、無ければ確認画面でエラーの行にします（`App\Rules\MailDomainRule`）。`gmial.con` のようなドメインの打ち間違いを、送る前に見つけるためです。同じドメインは1回だけ引きます。アカウントが無いこと（User unknown）は送ってみないと分からないので、ここでは見つかりません。`exmaple.com` のように、打ち間違いを狙って登録されているドメインも、あることになるので止まりません。DNS を引けないサーバーと、外のネットワークに頼らないテストでは、`.env` の `MAIL_BULK_CHECK_DOMAIN=false` で切ります（`config/site_bulk_mail.php` の `check_domain`）。
- **送れなかった宛先**：送信の詳細の画面の下に、送れなかった宛先（氏名・メールアドレス・送れなかった理由・日時）を枠で囲って並べます。宛先は送信の記録に保存しないので、`failed_jobs` に残ったジョブの中から読み出します。そのため、見られるのは上の日数の間だけで、日数は枠の中にも出します。画面に並べるのは先頭の100件で、全件は「CSVダウンロード（全件）」で落とせます。CSV は「氏名, メールアドレス, 送れなかった理由」の3列で、見出しの行はありません。3列目を消せば、そのまま宛先の CSV として読み込めます。ほかの CSV のダウンロードと同じく、ダウンロードの記録と操作ログを残します（`Admin\BulkMailController::failedCsv()`・`BulkMail::failedRecipients()`）。
- **バッチ**：たくさんのジョブを、1つずつ積まずに `Bus::batch()` で1つにまとめて積むと、Laravel がそのまとまりの件数を `job_batches` に数えます。進み具合（済み・失敗・残り）を、そこから読めます。`finally()` で、全部が終わったときの処理を書けます。一斉メールは、宛先の数だけのジョブを1つのバッチにして積み、送信の詳細の画面に進み具合を出しています（`Admin\BulkMailController` の `dispatchSendJobs()`・`sendCounts()`）。
- **トランザクション**：ジョブは、トランザクションが終わった後で積んでください。トランザクションの中で保存した行は、終わるまでワーカーからは見えません。中で積むと、ワーカーが先に動いて、行が見つからずに失敗することがあります。中で積むしかないときは、`dispatch()` に `->afterCommit()` を付けると、終わるまで待ってから積まれます。一斉メールは、送信の記録を保存し終えてから積んでいます（`Admin\BulkMailController::store()`）。

    ```php
    $bulkMail = DB::transaction(function () {
        return BulkMail::create([...]);
    });                                          // ここで確定。ワーカーにも見える

    SendBulkMail::dispatch($bulkMail->id);       // トランザクションの外で積む
    ```

## 一斉メール

- **文面の管理**：タイトル（管理用）・件名・本文を登録しておきます。
- **送信**：入力（文面を選ぶと欄に入るので、必要な箇所を書き換える。添付ファイルは1つ）→ 確認 → 送信 → 状況の画面。読み込ませる宛先の CSV は、「氏名,メールアドレス」の見出し無しの形です。文字コードは自動判別します。`CsvRead`（[csv-import.md](csv-import.md)）で、読み込みと検証だけを借ります。
- **氏名の差し込み**：件名と本文の `{{$name}}` を宛先の氏名に置き換えます。Blade では展開せず、文字列を置き換えるだけです。Blade で展開すると、画面から Blade のコードを入力されたときに、それがサーバーで実行されてしまうためです。ほかの `{{…}}` は入力エラーにします。件名と CSV の氏名は、メールの見出しを崩さないよう改行を許しません。
- **宛先は保存しない**：送るたびに送信の記録（`t_bulk_mails`）を1件作りますが、持つのは件名・本文・添付ファイル・件数だけです。宛先はジョブの中にだけあり、送り終えれば消えます。送り終えたら送れた件数と失敗した件数を記録に写します。
- **二重の送信**：送信中の一斉メールがあれば、入力画面を開いても状況の画面に回します。送信の実行はロックの中で確かめるので、2つの画面から同時に押しても1つしか送りません。
- 使えるのは管理者だけです。

## お知らせメールの配信停止（MailUnsubscribe）

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
- **停止の操作は、全部を操作ログに残します**（[operation-log.md](operation-log.md)）：会員がいたときは、対象を会員の種類と id で残します。会員がいないときと URL が正しくないときは、どのアドレスへの操作かが分かるよう、メールアドレスを補足に残します。会員ではない宛先の停止は、メルマガの行（下の「メルマガだけの申し込み」）にも「受け取らない」で残るので、次の一斉メールでは自動でエラーになります。
- **CSRF トークンは確かめません**：メールソフトの「登録解除」のボタンから、トークンの無い POST が届くためです（`bootstrap/app.php`）。本人のメールから来たことは、URL の署名で確かめます。
- 停止したことを知らせるメールは、送りません。本人は、マイページからいつでも「受け取る」に戻せます。

## メルマガだけの申し込み（NewsletterSubscription）

**ファイル**：`app/Services/NewsletterSubscription.php`（冒頭のコメント）・`app/Models/NewsletterSubscriber.php`　**実例**：`NewsletterController`（訪問者）・`Admin\NewsletterSubscriberController`（管理画面）、画面は `newsletter/`・`admin/newsletter_subscribers/`　**設計**：`docs/newsletter-subscription-spec.md`

会員登録をしなくても、お知らせメール（メルマガ）だけを受け取れるようにします。

- **持つ場所は1か所**：会員の「受け取るか」は会員のテーブル（`t_members.notice_mail`）、会員ではない人は `t_newsletter_subscribers` で持ちます。同じアドレスについて、どの時点でも1か所だけで持つので、ずれません。
- **申し込み**（`/newsletter`）：氏名（ニックネームでも可）とメールアドレスを入れると、会員登録と同じ確認コード（[login.md](login.md)）が届きます。コードが通って初めて「受け取る」にします。他人のアドレスを勝手に登録されないようにし、受け取ると決めた日時と IP アドレスを、同意の記録として残すためです。スパム対策（Turnstile）と、送信の回数の制限を付けています。
- **会員のアドレスで申し込んだとき**：メルマガの行は作らず、会員の側を「受け取る」にします。完了の画面は同じ文で、そのアドレスの会員がいるかを外から確かめられないようにしています。
- **連動するのは2つの場面だけ**
  - 会員登録：同じアドレスのメルマガの行を消します。受け取るかは、登録の画面で本人が選んだものにします。
  - 退会：「受け取る」だった会員は、「受け取らない」の行を残します。会員の情報が消えた後に、同じアドレスが宛先に入っても送らないためです。
- **管理画面**：「運用」の「メルマガ希望者」で、会員ではない人の一覧・検索と、手での停止ができます。管理者だけが使えます。
- 対象は個人会員だけです。企業会員の担当者には、この項目はありません。
- 確認コードやパスワードの変更のお知らせのように、本人の操作に応えて送るメールは、この項目に関係なく送ります。

配信停止のヘッダーは、テンプレートの見出しの行に書いてあります（[mail.md](mail.md)）。呼ぶ側は、配信停止の URL を変数で渡すだけです。

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
