# コードのセキュリティの確認

プログラムを読んで、穴になりやすい所を確かめた記録です。確かめる観点と探し方を残してあるので、機能を足した後や、公開の前に、同じ観点でやり直せます。

設置したサイトを外から確かめる手順は `security-external-check.md`、未対応の課題の一覧は `security-issues.md` にあります。

## この確認で分かること・分からないこと

| | 内容 |
|---|---|
| 分かること | 認証の付け忘れ、エスケープの漏れ、SQL への文字の埋め込み、ファイルの入出力の穴など、コードの書き方から読み取れる穴 |
| 分からないこと | サーバーの設定の漏れ（`security-external-check.md`）、パッケージの中の穴（`composer audit`・`npm audit`）、読んでいない所の誤り |

穴になりやすい所に絞って読みます。全部の行を読むものではありません。読んだ範囲と読んでいない範囲を、記録に書きます。

## 観点と探し方

コマンドは、プロジェクトの直下で、Git Bash から動かします。`grep` は、当たった行を1つずつ開いて、入力の値がそこへ届くかを確かめるための入口です。当たりが無いことだけでは、安全とは言えません。

### 1. ルートの認証

```bash
php artisan route:list --except-vendor -v
```

- **見るところ**：管理画面のルートの全部に `auth:admin` があること。管理者だけの画面に `acl.manager` があること。会員と企業会員の画面に、それぞれ `auth:web`・`auth:company` があること。
- **認証の無いルート**は、公開の画面と、ログインの途中の画面だけであること。メールを送る・DB に書くルートには、回数の制限（`throttle`）があること。

### 2. エスケープなしの出力

```bash
grep -rn "{!!" resources/views --include=*.blade.php | grep -v '\$required\['
```

- **見るところ**：`{!! !!}` で出しているのが、必須マーク（`$required[...]`・`required_mark()`）と、`safe_html()` を通した本文だけであること。
- `resources/mail-templates/` は、テキストのメールなので `{!! !!}` で書きます。こちらは、観点9で確かめます。

### 3. SQL への文字の埋め込み

```bash
grep -rnE "selectRaw|whereRaw|orderByRaw|havingRaw|groupByRaw|DB::raw|DB::statement|DB::select|DB::unprepared" app
```

- **見るところ**：入力の値を、`?` のバインドで渡していること。SQL の文字として埋め込んでいるのが、プログラムの中の固定の値だけであること。
- 一覧の並び順と検索の列は、`SearchableList` が `ORDER_OPTIONS` と `srchRules()` にあるものだけを使います。

### 4. 危険な関数と、デバッグの残り

```bash
grep -rnE "\b(eval|exec|shell_exec|system|passthru|proc_open|popen|unserialize|extract|assert)\s*\(" app
grep -rnE "\b(md5|sha1|rand|mt_rand|uniqid)\s*\(" app
grep -rnE "\bdd\(|\bdump\(|var_dump\(|print_r\(|phpinfo\(|env\(" app
```

- **見るところ**：1行目は、当たりが無いこと。2行目は、秘密の値（トークン・パスワード）を作ったり確かめたりする所に使っていないこと。3行目は、当たりが無いこと。`env()` は `config/` の中だけで使います。

### 5. 入力を丸ごと保存していないか

```bash
grep -rnE '\$request->all\(\)|->fill\(\$request|::create\(\$request|->update\(\$request|\$guarded|forceFill' app
```

- **見るところ**：`$request->all()` を、そのまま `create()`・`update()` に渡していないこと。保存する列は、`FormFlow` の `saveFieldNames()` で絞ります。`forceFill()` は、入力の値ではなく、プログラムが決めた値にだけ使っていること。

### 6. 外のサイトへ飛ばせないか

```bash
grep -rnE "->intended\(|redirect\(\\\$|redirect\(\)->to\(\\\$|url\(\)->previous|redirect\(\)->away" app
```

- **見るところ**：入力の値や、ブラウザが送ってきた URL へ、そのまま移動していないこと。ログイン後の移動先は `LoginRedirect` が、パスだけを使い、そのガードの画面かをルートから確かめます。

### 7. ファイルを返す処理

`app/Http/Controllers/UploadedFileController.php` を読みます。

- **見るところ**：ファイル名の形（`UploadFilePath::SAFE_FILENAME`）、DB にそのファイルの記録があるか、見てよい人か（ポリシーの `viewFiles()`）の3つを確かめてから返していること。
- 非公開のフィールドを足したら、そのモデルのポリシーに `viewFiles()` があることを確かめます。無いと、誰も見られません（安全な側に倒れます）。

### 8. アップロード

`app/Support/AjaxFileUpload.php` の `uploadAjaxFile()` を読みます。

- **見るところ**：種類を、拡張子と中身の両方で確かめていること（`mimes:`）。保存する名前が、利用者の付けた名前ではなく、ランダムな名前であること。受け付ける種類に、ブラウザやサーバーで動くもの（`html`・`svg`・`php`）が無いこと。

### 9. メールの見出しに、入力の値が入らないか

```bash
for f in resources/mail-templates/*.blade.php; do awk 'NF==0{exit} {print FILENAME": "$0}' "$f"; done | grep -v "FROM_MAIL\|FROM_NAME"
```

- **見るところ**：見出しの行（`TO_MAIL:`・`SUBJECT:` など）に入る変数です。訪問者の入力が入るものを拾います。
- 見出しに入る値の改行は、`MailTemplate` が除きます。宛先の行（`TO_MAIL:`・`REPLY_TO:` など）は、カンマで複数書けるので、宛先に入れる値は、呼ぶ側で `email` のルールで検証していることを確かめます。

### 10. ログインまわり

```bash
grep -rnE "regenerate\(|invalidate\(" app
grep -rln "new LoginThrottle" app
grep -rnE "random_int|Str::random|Hash::make|Hash::check" app/Support
```

- **見るところ**：ログインが完了する全部の所で、セッションを作り直していること。ログイン・確認コード・パスワードの再設定に、試行制限（`LoginThrottle`）があること。確認コードやトークンを、暗号用の乱数（`random_int()`・`Str::random()`）で作り、ハッシュ値で保存していること。

### 11. 権限の越境

- **見るところ**：URL の id を書き換えて、ほかの人や、ほかの企業のデータを開けないこと。企業会員の担当者の画面は、`Company\UserController` の `abortUnlessOwnCompany()` で確かめています。
- 新しい画面を足したら、「id を書き換えたら 404 になる」というテストを書きます。

### 12. そのほか

```bash
git ls-files | grep -iE "\.env|\.pem|\.key|secret|credential|\.sql$|id_rsa"
grep -rn "escapeFormula:" app
grep -rnE 'href="\{\{ \$' resources/views
```

- **秘密の値**：Git に入っているのが `.env.example` だけであること。
- **CSV の数式**：ダウンロードと取り込みで、`escapeFormula: true` になっていること。
- **リンクの URL**：入力の値を `href` に出す所は、検証が `url` のルールで、`javascript:` などを断っていること。

## 実施の記録

### 2026-10-07

固定ページのコーナーを足した後のコード（コミット `c877403`）を、上の12の観点で読みました。手元のコードを読んだもので、サイトへの検査はしていません。

#### 結果

| 観点 | 結果 | 判定 |
|---|---|---|
| 1. ルートの認証 | 238ルートを確認。管理画面は全部 `auth:admin`。認証の無いルートは21個で、公開の画面とログインの途中の画面だけ | **所見2** |
| 2. エスケープなしの出力 | 画面の `{!! !!}` は、必須マークと `safe_html()` を通した本文だけ | 良い |
| 3. SQL | 生の SQL は12か所。入力の値は全部バインド。埋め込んでいるのは固定の書式だけ | 良い |
| 4. 危険な関数 | 1行目・3行目は当たり無し。`md5`・`sha1` は、古い方式のパスワードの照合と、キャッシュのキーだけ | 良い |
| 5. 入力の丸ごとの保存 | `$request->all()` は、検証と、アップロードの確定に渡す所だけ。`forceFill()` は、プログラムが決めた値だけ | 良い |
| 6. 外のサイトへの移動 | `LoginRedirect` が、パスだけを使い、ルートからガードを確かめている | 良い |
| 7. ファイルを返す処理 | 3つの確かめが全部ある。見てはいけない人には 404 | 良い |
| 8. アップロード | 画像は jpg・png・webp、添付は pdf・doc・docx・xls・xlsx・csv・zip。ランダムな名前で保存 | 良い |
| 9. メールの見出し | 氏名・企業名の改行で、宛先の行を足せた | **所見1** |
| 10. ログインまわり | セッションの作り直しは15か所。試行制限は12のファイルで使っている。乱数とハッシュ値も良い | 良い |
| 11. 権限の越境 | 担当者の画面は、ほかの企業のデータを 404 にしている。テストもある | 良い |
| 12. そのほか | Git の秘密の値は無し。CSV の数式の対策は有効。`url` のルールは `javascript:`・`data:`・`vbscript:` を断る | 良い |

観点12の補足です。`url` のルールが何を断るかは、`Validator` で実際に試しました。`http://` は通り、`javascript:alert(1)`・`data:text/html,x`・`vbscript://x.com` は断ります。`ftp://` は通ります。

#### 所見1　メールの宛先を足せる（メールヘッダインジェクション）

- **重さ**：高。ログインしていない人が使えます。
- **起きていたこと**：お問い合わせの「氏名」と、企業会員の登録の「企業名」に、改行と `BCC_MAIL: …` を書いて送ると、サイトから好きな宛先へメールを送らせることができました。お問い合わせの内容が、足した宛先にも届きます。迷惑メールの踏み台にもなります。
- **原因**：`MailTemplate` は、変数を埋めた後の文字を、見出しと本文に分けていました。見出しの行（`SUBJECT: 【お問い合わせ】氏名 様より`）に入る値に改行があると、その後ろが次の見出しの行として読まれます。氏名の検証は `string`・`max:255` だけで、改行を断っていませんでした。
- **確かめ方**：手元で `MailTemplate::render('contact_staff', …)` に、改行を含む氏名を渡して、結果の `bcc` に足した宛先が入ることを確認しました。メールは送っていません。
- **入口になるテンプレート**：見出しの行に入力の値が入るものは、`contact_staff`（氏名）、`company_registration_staff`・`company_invitation`（企業名）の3つでした。`error_notify` の件名も、例外の文言に改行があれば、同じ形になりえました。
- **直した内容**：`MailTemplate::render()` で、展開の前に見出しの行と本文を分け、見出しの行には、改行を空白に置き換えた値を入れるようにしました。検証のルールに頼らず、メールの部品の1か所で必ず除きます。本文に入る値の改行は、そのままです。
- **テスト**：`tests/Feature/MailTemplateTest.php`（改行で宛先が足されないこと、空行で見出しが終わらないこと、本文の改行は残ること、`email` のルールがカンマや改行を断ること）。
- **残る注意**：宛先の行はカンマで複数書けます。宛先に入れる値は、`email` のルールで検証します。一斉メールの件名と、宛先の CSV の氏名は、以前から改行を検証で断っていました。

#### 所見2　比較用のお問い合わせフォームに、回数の制限が無い

- **重さ**：低。
- **起きていたこと**：`/contact2`（フレームワークの部品を使わない、比較用のフォーム）は、送信の回数の制限も、スパムの確認もありませんでした。送るたびにスタッフへメールが届くので、大量に送りつけられます。添付ファイルも10MB まで付けられます。
- **直した内容**：1分に5回までの制限（`throttle:5,1,contact2-store`）を付けました。スパムの確認は付けていません。
- **残る注意**：公開するサイトでは、`/contact2` の2つのルートを消します。`routes/web.php` のコメントにも書いてあります。

#### この回で読んでいない所

- パスキーと2段階認証の、照合の細部（`PasskeyCeremony`・`TwoFactorAuthenticator`）
- CSV 取り込みの中身（大きなファイルや、壊れたファイルへの耐性）
- JavaScript の側（`resources/js`）
- 操作ログ・一斉メール・コード表の管理の、画面ごとの細かい動き
- 開発用のツール（`tools/`）。サーバーの画面からは呼ばれません

#### この回で実際に流したコマンド

```bash
# ルートを、ミドルウェアごとにまとめる（JSON を Python で集計した）
php artisan route:list --json --except-vendor

# 観点2〜6（Claude Code の検索の道具で流した。grep で書くと、次のとおり）
grep -rn "{!!" resources/views --include=*.blade.php | grep -v '\$required\[' | grep -v "resources/views/mail/"
grep -rnE "selectRaw|whereRaw|orderByRaw|havingRaw|DB::raw|DB::statement|DB::select|DB::unprepared|->raw\(|groupByRaw" app
grep -rnE "\b(eval|exec|shell_exec|system|passthru|proc_open|popen|unserialize|extract|assert|create_function)\s*\(|\b(md5|sha1|rand|mt_rand|uniqid|lcg_value)\s*\(|\bdd\(|\bdump\(|var_dump\(|print_r\(|phpinfo\(|env\(" app
grep -rnE '\$request->all\(\)|->fill\(\$request|::create\(\$request|->update\(\$request|\$guarded|forceFill|->intended\(|redirect\(\$|redirect\(\)->to\(\$|url\(\)->previous|redirect\(\)->away' app

# 観点9：見出しの行に入る変数
for f in resources/mail-templates/*.blade.php; do awk 'NF==0{exit} {print FILENAME": "$0}' "$f"; done | grep -v "FROM_MAIL\|FROM_NAME"

# 観点10・12
grep -rnE "regenerate\(|regenerateToken\(|invalidate\(" app
grep -rln "new LoginThrottle" app
git ls-files | grep -iE "\.env|\.pem|\.key|secret|credential|\.sql$|id_rsa"
grep -rn "escapeFormula:" app
```

読んだファイルは、`UploadedFileController`・`AjaxFileUpload`（アップロードの受け取りの部分）・`MailTemplate`・`MailTemplateParser`・`LoginRedirect`・`Contact2Controller`・`OperationLogStats`（集計の SQL）・4つのポリシー・`Company\UserController`（権限の確かめの部分）です。
