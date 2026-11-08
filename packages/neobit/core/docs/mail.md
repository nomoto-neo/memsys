# メール送信（MailTemplate・TemplatedMail）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Mail/MailTemplate.php`・`MailTemplateParser.php`・`packages/neobit/core/src/Mail/TemplatedMail.php`・`resources/mail-templates/`　**実例**：`ContactController::sendStaffNotification()`・`LoginVerificationCode`

送信元・宛先・件名・本文は、テンプレートファイルがすべて決めます。呼び出し側は変数と添付ファイルを渡すだけです。

## テンプレートの置き場所

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

## テンプレートの書き方と送り方

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
    ['path' => $absolutePath, 'name' => $originalName],   // 添付ファイル（任意）。サーバー上のファイル
    ['data' => $pdf, 'name' => '履歴書.pdf'],              // ファイルの中身を直接渡す
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
- 添付ファイルは、`path`（サーバー上のファイルの場所）か `data`（ファイルの中身）のどちらかと、`name`（メールに付けるときのファイル名）で書きます。作った PDF などは、`data` で渡せばファイルに書かずに付けられます。`data` のファイルの種類は、`name` の拡張子から決まります。
- キューに積んで送るときは、`data` ではなく、ファイルに保存して `path` で渡します。
- `Mail::to()` は使わず `Mail::send()` で送ります（宛先はテンプレートが持つため）。
- 保存と一緒に送るメールは、保存のトランザクションが確定した後に送り、送信に失敗しても保存は取り消さず、ログに残します（実例：お問い合わせ）。

## 届かなかったメールの戻り先（Return-Path）

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
