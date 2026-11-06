# 操作ログ（OperationRecorder）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Operations/OperationRecorder.php`（冒頭のコメント）・`packages/neobit/core/src/Models/OperationLog.php`・`packages/neobit/core/src/Enums/OperationLogAction.php`・`packages/neobit/core/src/Http/Controllers/OperationLogController.php`（一覧）・`app/Enums/AppOperationLogModel.php`　**実例**：`Admin\MemberController::show()`（詳細の閲覧）

誰が・いつ・どこから・何に・何をしたかを、`operation_logs` に1行ずつ残します。情報が漏れたときや、「変えていないのに変わっている」という問い合わせがあったときに、後からたどれるようにするためです。

## 残すもの・残さないもの

| 列 | 中身 |
|---|---|
| `operator_type`・`operator_id` | 操作した人の種類（`staff`・`member`）と id。誰もログインしていない訪問者の操作は空で、画面では「訪問者」と出す |
| `action` | 操作の種類（`OperationLogAction`） |
| `target_type`・`target_id` | 対象の種類と id。CSV のダウンロードのように、1件に決まらないときは空 |
| `changed_fields` | 更新のときだけ。値が変わった列の名前の一覧 |
| `detail` | 種類ごとの補足。CSV の名前、ログインに失敗したログイン ID など |
| `ip`・`device` | IP アドレスと、端末の種類（`UserAgentLabel`。例：iPhone・Safari） |

- **氏名などの個人情報と、変更の前後の値は残しません**：操作ログが個人情報の写しにならないようにするためです。前の値までスタッフが知っていることは、会員の不信感にもつながります。画面では、id から今の氏名を引いて出します。
- **種類の名前**は、`AppServiceProvider` の `Relation::enforceMorphMap()` に書いた名前です。画面やメールでは、`config/neobit.php` の `operation_log.models` に書いた列挙型（見本のサイトでは `App\Enums\AppOperationLogModel`）から、「会員」のような名前にします。操作の種類（`operation_log.actions`）と同じく、フレームワークがコード表 `operation_log_model` を作ります。列挙型に無い種類は、種類の名前のまま出ます。操作ログに残すモデルを増やしたら、`enforceMorphMap()` と列挙型の両方に足します。

## 記録するところ

| 操作 | 記録するところ | コーナーに書くもの |
|---|---|---|
| 登録・更新・削除 | `FormFlow` の `saveData()`・`deleteData()` | 無い（別の名前で残すときだけ `operationLogActionType()`） |
| ログイン・ログアウト | `AppServiceProvider`（Laravel のログイン・ログアウトのイベント） | 無い |
| ログインの失敗 | ログインのコントローラー（試行制限の回数を足すところ） | 無い |
| パスワードの変更 | `PasswordChange::resetAndNotify()` | 無い |
| パスキーの登録・削除 | `PasskeyManagement` | 無い |
| 担当者の招待の送信・取り消し | `TeamInvitationManager` | 無い |
| お知らせメールの配信停止 | `MailUnsubscribe`（メールの中の URL からの停止。[queue.md](queue.md)） | 無い |
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

## 決まりごと

- **操作した人**：渡さなければ、その画面のガードでログインしている人です。管理画面ならスタッフ、マイページなら会員になります。2段階目の途中や、ログアウトした後のように、ログインはしていないが誰か分かっている操作は、`operator:` で渡します。
- **訪問者の操作も残します**：お問い合わせの送信やログインの失敗のように、誰もログインしていない操作は、操作した人を空にして残します。IP アドレスは残るので、回数の制限をすり抜けるような大量の操作があったときに、後から調べられます。
- **ログインの失敗は、対象にログインの種類を残します**：パスワードやパスキーの照合に失敗したときは、誰の失敗かが分からないので、対象の欄に種類だけ（個人会員・企業の担当者・スタッフ）を残します。どのログイン画面が狙われたかを、一覧の「対象」の検索で絞れます。種類だけを残すときは、`OperationRecorder::record()` に `targetClass:` でモデルのクラスを渡します。
- **登録・更新とは別の名前で残す**：コントローラーに `operationLogActionType()` を書きます。一斉メールは、送信の記録を1件作るのが送信の始まりなので、「登録」ではなく「一斉メールの送信」として残しています。
- **一覧の表示と検索は記録しません**：1ページに出る件数が限られているためです。機械的に続けて取る動きは、下の「管理画面の回数の制限」で止めて記録します。
- **CSV 取り込みは、取り込み全体で1行です**：1件ずつの更新は記録しません。件数の内訳は取り込みの記録（`csv_import_logs`）が持ち、`detail` の id でつながります。CSV のダウンロードも同じです。
- **変わった列に数えない列**：`created_at`・`updated_at` などの保存のたびに変わる列と、アップロードの元のファイル名の列（`〇〇_origin`）は数えません。最後に更新したスタッフの id のように、入力とは関係なく変わる列は、モデルの定数 `OPERATION_LOG_IGNORE` に書きます（例：`Member`）。
- **変わった列は、本体のテーブルの列だけです**：多対多のような関連のテーブルの変更は、列の名前には出ません。「更新した」ことは残ります。
- **変わった項目は、列の名前のまま出します**（`phone`・`disp_flg` など）。日本語の名前の対応表は持ちません。画面を足すたびに対応表も足すことになり、CSV の見出しなどと二重に持つことになるためです。
- **`detail` に個人情報と入力値を入れません**。ログインの失敗のログイン ID は、どのアカウントが狙われたかを調べるために残しています。お知らせメールの配信停止は、そのアドレスの会員がいないときだけ、どのアドレスへの操作かが分かるようにメールアドレスを残しています（[queue.md](queue.md)）。例外は、この2つだけです。
- **残す期間**：`config/neobit.php` の `cleanup` の節の `operation_log_days`（365日）。過ぎた行は、一時データの後片付け（[scheduler.md](scheduler.md)）が消します。日数は、操作ログの一覧の上にも出します。
- **取らないとき**：`operation_log_days` を `0` にすると、操作ログを取りません。操作ログを求められない案件のためです。それまでに溜まった行は、片付けでは消さずに残ります。日々の報告のメールも、気になる点が出ないので届きません。IPブロック管理が自動で止めたことは、`warning` のログとエラーの通知のメール（[error-notify.md](error-notify.md)）で分かります。

## 管理画面の回数の制限（AdminRequestLimit）

**ファイル**：`packages/neobit/core/src/Security/AdminRequestLimit.php`（冒頭のコメント）・`routes/admin.php`（ログイン中のスタッフのグループの `throttle:admin-screen`）

ログイン後の管理画面の全体に、スタッフごとの回数の制限を掛けています。一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止めるためです。

- **回数**：`config/neobit.php` の `security.admin_requests_per_minute`（1分に120回）。人が普通に使って届かない数にします。画面の表示のほか、アップロードや、送信中の画面の読み直しも1回に数えます。`0` にすると、制限を掛けません。
- **数える単位**：操作しているスタッフの id です。同じ事務所から複数の人が使っても、互いに影響しません。
- **超えたとき**：その回は処理せず、少し待つよう案内する画面（`admin/too_many_requests.blade.php`）を返します。案内の文は、`config/neobit.php` の `security.admin_requests_exceeded_message` に書きます。アップロードのような JavaScript からの送信には、同じ文を JSON で返します。操作ログに「回数の制限」を残し、`warning` のログも書きます。`ERROR_NOTIFY_LEVEL` が `warning` なら、エラーの通知のメール（[error-notify.md](error-notify.md)）が届きます。件名は `【要確認：サイト名】WARNING：管理画面の操作が上限を超えました（スタッフID:3）` です。
- **記録は、スタッフごとに1分に1回まで**：続けて取り出そうとすると、超えた回数だけ記録が増えてしまうためです。
- **管理画面のルートを足すとき**：`auth:admin` のグループの内側に書けば、この制限が掛かります。足すものはありません。
- **止められないもの**：上限より遅く、時間をかけて取る動きは止まりません。そちらは、詳細の閲覧や CSV のダウンロードの記録から調べます。

## 画面

- **一覧と検索**（`/app/admin/operation-logs`）：日付の範囲・操作・操作した人・対象・IP アドレス・補足で絞れます。補足は、中の語を部分一致で探します。空白で区切ると、全部の語を含む行に絞ります。CSV でも出せます。見るだけの画面で、登録・編集・削除はありません。
- **どちらの画面も、管理者だけが開けます**：記録を取っていることを、スタッフの全員に見せる必要は無いためです。管理画面のトップのリンクも、管理者にだけ出ます。
- **会員ごとの操作ログ**（`/app/admin/members/{id}/operation-logs`。会員の詳細画面の「操作ログ」のボタン）：その会員を対象にした操作と、その会員が行った操作だけを出します。本人が変えたのか、スタッフが変えたのか、誰が詳細を開いたのかが、時刻の順に並びます。問い合わせに答えるのに使います。こちらも管理者だけが開け、ボタンも管理者にだけ出ます。
- **一覧の画面はフレームワークにあります**（`OperationLogController`・`neobit::admin.operation_logs.index`）。ルートは案件が書きます。管理者だけのグループの中に、`Neobit\Core\Routing\FrameworkRoutes` の1行を書きます。日々の報告のメールに画面の URL を載せるなら、`config/neobit.php` の `operation_log.route` に「頭.index」を書きます。会員ごとの操作ログの画面は案件の画面で、表は同じ部分ビュー（`neobit::admin.operation_logs._table`）を使います。

    ```php
    // routes/admin.php（管理者だけのグループの中）
    FrameworkRoutes::operationLogs('/operation-logs');
    ```
- **CSV のダウンロードと取り込みは、対象の欄に CSV の名前を出します**（会員一覧など）。何に対する操作かを表すためです。
- **CSV と一斉メールの送信は、補足の欄に件数を出します**：`23件`、`120行（追加 0・更新 15・変更なし 105）`、`宛先 300件（送信済み 298・失敗 2）` の形です。件数は操作ログには持たせず、CSV の記録（`csv_download_logs`・`csv_import_logs`）と送信の記録（`t_bulk_mails`）から、その都度読みます（`OperationLog::relatedRecords()`）。
- **件数を押すと、内訳のモーダルが開きます**：CSV のダウンロードなら検索条件、取り込みならファイル名と件数の内訳、一斉メールなら件名と送信の結果です。中身は、記録の側が返します（`OperationLogRelatedRecord` の `operationLogDetails()`。項目の名前 => 値）。詳しい画面があれば、`operationLogUrl()` の URL へのボタンも出します（一斉メールの送信の状況の画面）。画面は記録の種類を見ないので、案件の記録もフレームワークの画面に同じ形で出せます。
- 操作ログを CSV で出したときも、「補足」の列に同じ件数が入ります。内訳は入りません。
- **長い補足は、切って出します**：「変わった項目・補足」の欄が60文字（`OperationLog::DETAIL_SHOWN_LENGTH`）を超えたら、60文字で切って「…」と「全文」のリンクを出します。押すとモーダルが開き、変わった項目を1行、補足の項目を1行ずつ出します。アクセス制限の変更のように、IP アドレスの一覧が並ぶ補足で、表が縦に伸びないようにするためです。CSV で出したときは、切らずに全文が入ります。

## 件数の集計（OperationLogStats）

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

## 1日1回の報告のメール（OperationLogReport）

**ファイル**：`packages/neobit/core/src/Operations/OperationLogReport.php`（冒頭のコメント）・`packages/neobit/core/src/Console/ReportOperationLogs.php`・`packages/neobit/core/resources/mail-templates/operation_log_report.blade.php`

毎朝8時に、前の日の操作ログの報告を管理者へ送ります（`routes/console.php`）。**気になる点があった日だけ**送ります。毎日届くと読まれなくなり、肝心の日に埋もれるためです。

| 気になる点 | 目安（`config/neobit.php` の `operation_log.report_thresholds`） |
|---|---|
| 1人が、1時間に詳細を開いた件数 | 50件以上（`views_per_hour`） |
| 1人が、1日に CSV をダウンロードした回数 | 5回以上（`csv_downloads_per_day`） |
| 1日のログインの失敗 | 10件以上（`login_failures_per_day`） |
| 管理画面の回数の制限に達した人 | 1人でもいれば |

- **宛先**：`.env` の `OPERATION_REPORT_TO`（カンマ区切りで複数書けます）。空なら送りません。エラーの通知（`ERROR_NOTIFY_TO`）は開発者、こちらは管理者と、読む人が違うので分けています。
- **件名**：`【要確認：サイト名】操作ログの報告（日付）`。頭は、エラーの通知と同じです。
- **載せるもの**：気になる点、操作の種類ごとの件数、操作の多い人です。操作ログと同じく、氏名のほかの個人情報は載せません。
- **手で動かす**：`php artisan neobit:report-operation-logs --date=2026-10-04 --always`。`--date` を書かなければ前の日、`--always` を付けると気になる点が無くても送ります。文面や宛先を確かめるときに使います。
- 目安の数は、サイトの規模（スタッフや会員の数、1日の操作の量）に合わせて、`config/neobit.php` の `operation_log.report_thresholds` で決めます。0 にすると、その項目は見ません。

## 会員情報が変わったときのお知らせメール（LoginProfileNotice）

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
- 本人がメールアドレスを変えるときは、保存の前に確認コードの入力を挟みます（`EmailChange`。[login.md](login.md)）。お知らせは、コードが入力できて保存された後に届きます。
