# スケジューラー（定期的な処理）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`routes/console.php`（スケジュールの一覧）・`packages/neobit/core/src/Console/`（コマンド）　**実例**：一時データの後片付け（`packages/neobit/core/src/Operations/TemporaryDataCleaner.php`・`packages/neobit/core/src/Console/CleanupTemporaryData.php`）

「決まった時刻に何かを実行する」処理は、Artisan のコマンドとして作り、`routes/console.php` でいつ動かすかを決めます。表示するたびに今の時刻と比べれば済むもの（ニュースの掲載期間など）は、スケジューラーを使いません。

## コマンドとスケジュール

```php
// packages/neobit/core/src/Console/CleanupTemporaryData.php（php artisan make:command で作る）
#[Signature('neobit:cleanup-temporary-data')]
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
- 登録した一覧と次に動く時刻は `php artisan schedule:list`、すぐに動かすときは `php artisan neobit:cleanup-temporary-data` のようにコマンドを直接実行します。

## 一時データの後片付け（TemporaryDataCleaner）

**ファイル**：`packages/neobit/core/src/Operations/TemporaryDataCleaner.php`（冒頭のコメント）・`config/neobit.php` の `cleanup` の節（残す日数と、プロジェクトの片付け）

フレームワークが片付けるものは、次のとおりです。

| 一時データ | 置き場所 | 消すもの |
|---|---|---|
| アップロード直後の一時ファイル | `storage/app/private/upload_tmp` | 24時間（`MAX_AGE_HOURS`）より古いファイル |
| CSV 取り込みの作業用ファイル | `storage/app/private/csv_import` | 24時間より古いファイル |
| 試行制限の回数などのキャッシュ | `cache`・`cache_locks` テーブル | 期限の切れた行（キャッシュを database に置いているときだけ） |
| 「この端末を信頼する」の記録 | `trusted_devices` テーブル | 期限（`expires_at`）の切れた行 |
| 操作ログ（[operation-log.md](operation-log.md)） | `operation_logs` テーブル | `config/neobit.php` の `cleanup` の節の `operation_log_days`（365日）より古い行 |
| 送れなかったキューのジョブ（[queue.md](queue.md)） | `failed_jobs` テーブル | `config/neobit.php` の `cleanup` の節の `failed_job_days`（7日）より古い行 |
| 終わったジョブのバッチ（[queue.md](queue.md)） | `job_batches` テーブル | 終わってから `failed_job_days`（7日）を過ぎた行。送り直せる間だけ残す |
| 招待（[member-types.md](member-types.md)） | `team_invitations` テーブル | 期限（`expires_at`）の切れた行（`TeamInvitationManager::deleteExpired()`） |

業務のデータの片付けは、`TemporaryDataCleaner` には書かず、`config/neobit.php` の `cleanup` の節の `tasks` に足します。フレームワークの片付けの後に、同じ1時間ごとの片付けの中で呼ばれます。サンプルサイトでは、お問い合わせと一斉メールの記録の片付けを足しています。

| 片付け | 置き場所 | 消すもの |
|---|---|---|
| お問い合わせ（[public-form.md](public-form.md)） | `t_inquiries` テーブルと添付ファイル | `tasks` の `days` に書いた日数より古い行と、その添付ファイル。`null` なら消さない（`App\Services\InquiryCleanup`） |
| 一斉メールの記録（[queue.md](queue.md)） | `t_bulk_mails` テーブルと添付ファイル | 送り終えてから `tasks` の `days` に書いた日数を過ぎた記録と、その添付ファイル・バッチの行。`null` なら消さない。日数は送信の履歴の画面にも出る（`App\Services\BulkMailCleanup`） |

```php
// config/neobit.php の cleanup の節
'tasks' => [
    '保存期間を過ぎたお問い合わせ' => [
        'run' => [InquiryCleanup::class, 'deleteOld'],                // 呼ぶメソッド。消した件数を返す
        'days' => null,                                               // 残す日数。メソッドに引数で渡す。行ごとに期限を持つものは、days を書かない
    ],
],
```

- 一時ファイルは、アップロードと CSV 取り込みのたびにも同じ処理で消します。サーバーの cron が動いていなくても溜まり続けないようにするための控えです。
- **お問い合わせは、日数を決めたサイトだけ消します**。`config/neobit.php` の `cleanup` の節の `tasks` の `days` に残す日数を書くと、過ぎた行と添付ファイルを消します。`null` なら、消さずに残し続けます。個人情報を、要らなくなった後も持ち続けないようにするためです。日数は、サイトの運用で決めます。1件ずつは操作ログに残さず、消した件数だけをログ（`laravel.log`）に残します。スタッフに届いたお問い合わせのメールは、消えません。
- 何かを消したときだけログに残し、何もしなかった回は残しません。同じ行が1時間ごとに並ばないようにするためです。
- 新しい片付けを足すときは、消した件数を返すメソッドを `app/Services/` のクラスなどに書き、`config/neobit.php` の `cleanup` の節の `tasks` に1つ足します。残す日数があるものは `days` に書くと、メソッドに引数で渡されます。名前は、片付けのコマンドの表とログに出ます。

## サーバーの設定（cron）

スケジューラーは、サーバーの cron で毎分 `php artisan schedule:run` を動かしたときに働きます。cron が無いと、`routes/console.php` に書いた処理も、キューのワーカー（[queue.md](queue.md)）も何も動きません。cron を設定できないサーバーは、下の「cron を動かせないサーバー」のとおりにします。

サイトごとに `/etc/cron.d/` へファイルを1つ置きます。例は、開発用のサイトの `/etc/cron.d/memsys_dev` です。

```cron
* * * * * apache cd /var/www/memsys_dev && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

- `/etc/cron.d/` のファイルは、時刻の後に実行するユーザーを書きます。**PHP-FPM のそのサイトのプールと同じユーザー**にします（プールの設定の `user`）。root で動かすと、ログ（`storage/logs`）などのファイルが root の持ち物になり、画面からの処理が書き込めなくなります。apache のようにログインできないユーザーでも動きます。
- cron から動かすコマンドも、`bootstrap/cache/config.php` から設定を読みます。`schedule:run` が SQLite のエラーで止まるときは、`config:cache` をやり直してください（[deploy.md](deploy.md)「サーバーへ送った後に実行するコマンド」）。
- cron の PATH は最小限なので、`php` は絶対パスで書きます。場所は `which php` で確かめます。サイトのパスもサーバーに合わせます。
- `/etc/cron.d/` に置くファイルは、root の持ち物で 644 にしてください。そうでないと、cron が読み飛ばします。最後の行の後ろには改行を入れます。無いと、最後の行が読まれないことがあります。ファイル名に `.` を入れると、読み飛ばされます。
- 開発用と本番が同じサーバーにあるときは、`/etc/cron.d/` に置くファイルを `memsys_dev`・`memsys` のように分け、中に書くサイトのパスと実行するユーザーを、それぞれに合わせます。
- 動いているかは、1〜2分待ってから `php artisan schedule:list` で次に動く時刻を見るか、`storage/logs` に一時データの片付けのログが出るかで確かめます。

## cron を動かせないサーバー

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
