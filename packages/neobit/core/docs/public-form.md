# 訪問者向けのフォーム（お問い合わせ）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：`ContactController`・`resources/views/contact/`

## 管理画面の登録との違い

管理画面の登録と同じく FormFlow で作ります。違いは次の4つです。

- **確認画面を経由した1回だけの送信**：確認画面を出すたびに使い捨ての合言葉（`confirm_token`）を発行してセッションと hidden に持たせ、送信のときに一致を確かめ、保存できたら消します。合言葉は `$input` に混ぜず、送信フォームにだけ埋めます。
- **連続送信の制限**：送信とアップロードのルートに `throttle:回数,分,識別名` を付けます。識別名はルートごとに重ならない名前にします（省略すると別のルートとカウンターを共有してしまいます）。
- **保存の後の処理**：保存（`saveData()`）の後に通知メールを送り、完了画面へリダイレクトします。
- **スパム対策**：下の「スパム対策（SpamGuard）」。

## スパム対策（SpamGuard）

**ファイル**：`packages/neobit/core/src/Security/SpamGuard.php`（冒頭のコメント）・`packages/neobit/core/resources/views/_spam_guard.blade.php`・`packages/neobit/core/src/Enums/SpamCheckResult.php`　**実例**：`ContactController::confirm()`

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

必須マークは `config/neobit.php` の `form` の節の `public` が使われます。
