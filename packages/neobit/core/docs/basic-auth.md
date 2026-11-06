# ベーシック認証

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Security/BasicAuth.php`（冒頭のコメント）・`src/Http/Controllers/BasicAuthController.php`・`src/Models/BasicAuthUser.php`、画面は `packages/neobit/core/resources/views/admin/basic_auth/index.blade.php`　**実例**：管理画面の「セキュリティ」の「ベーシック認証」

運用する人が管理画面から、訪問者画面と管理画面のそれぞれに、ベーシック認証を掛けるかと、そのユーザーを別々に変えられます。認証するのは Apache なので、Laravel を通らない `public` の下の HTML や画像にも掛かります。

- **仕組み**：管理画面で切り替えると、目印のファイル `storage/app/basic_auth/site.on`（訪問者画面）・`admin.on`（管理画面）を作る・消します。ユーザーを追加・削除すると、その範囲の `storage/app/basic_auth/site.htpasswd`・`admin.htpasswd` を書き直します（パスワードは bcrypt の `$2y$`）。サーバーの VirtualHost に書いた決まりが、目印のファイルがあるときだけ認証を掛けます。`public/.htaccess` は変えません。
- **サーバーの設定**（1回だけ。サーバーを管理する人）：VirtualHost に次の決まりを書き、Apache を読み直します。パスはそのサーバーのプロジェクトの場所に書き換えます。決まりを書いていないサーバーでは、画面で切り替えても掛かりません。

    ```apache
    <Directory "/var/www/memsys/public">
        # 管理画面の「ベーシック認証」の訪問者画面。目印のファイルがあるときだけ掛ける。死活監視・配信停止・管理画面・/.well-known には掛けない。
        # index.php に回し直した2回目の判断では掛けない（掛けるかは、届いた URL の1回目で決まっているため）
        <If "-f '/var/www/memsys/storage/app/basic_auth/site.on' && -z %{ENV:REDIRECT_STATUS} && \
            %{REQUEST_URI} !~ m#^/(up|mail/unsubscribe|app/admin|\.well-known)(/|$)#">
            AuthType Basic
            AuthName "Restricted"
            AuthUserFile /var/www/memsys/storage/app/basic_auth/site.htpasswd
            Require valid-user
        </If>
        # 管理画面の「ベーシック認証」の管理画面。AuthNameは訪問者画面と分ける
        <If "-f '/var/www/memsys/storage/app/basic_auth/admin.on' && %{REQUEST_URI} =~ m#^/app/admin#">
            AuthType Basic
            AuthName "RestrictedAdmin"
            AuthUserFile /var/www/memsys/storage/app/basic_auth/admin.htpasswd
            Require valid-user
        </If>
    </Directory>
    ```
- **回し直した2回目の判断では掛けません**（`-z %{ENV:REDIRECT_STATUS}`）。`/up` など Laravel が返す URL は、`public/.htaccess` が `index.php` に回し直し、Apache はその要求でもう一度 `<If>` を判断します。そのときの `%{REQUEST_URI}` は `/index.php` なので、これが無いと除外に当たらず、`/up`・`/mail/unsubscribe` と管理画面にも、訪問者画面の認証が掛かります。`REDIRECT_STATUS` は Apache が回し直しのときにだけ付ける値で、外からは送り込めません。`/index.php` を直接開かれたときは1回目の判断なので、認証が掛かります。
- 除外は、`%{THE_REQUEST}`（届いた要求の行）や、`<Directory>` の外の `SetEnvIf` では判断しません。どちらも `..` や `%2e` を片付ける前の URL を見るので、`/up/../` のように書かれると、除外に当たったまま別のページを開けてしまうためです。`(/|$)` は、`/upload` のような、頭だけが同じ URL を除外しないための区切りです。
- 書いたら、`curl -sI` で `/`（401）・`/up`（200）・`/.well-known/acme-challenge/test`（401 以外）を確かめます。
- **AuthName は範囲ごとに分けます**（訪問者画面は `Restricted`、管理画面は `RestrictedAdmin`）。ブラウザは入れた ID とパスワードを、ホスト名と AuthName の組ごとに覚えます。同じ AuthName にすると、訪問者画面で入れたものを管理画面にも黙って送り、同じユーザーが両方にあれば、管理画面の窓が出ずに通ってしまいます。
- **サーバーでのオンオフ**：目印のファイルを作る・消すだけです。パスワードを忘れて管理画面に入れなくなったときは、`admin.on` を消します。

    ```bash
    touch storage/app/basic_auth/admin.on   # 管理画面に掛ける
    rm storage/app/basic_auth/admin.on      # 外す
    ```
- **掛けない URL**：訪問者画面に掛けても、死活監視（`/up`）とメルマガの配信停止（`/mail/unsubscribe`）と `/.well-known` の下には掛けません。`/.well-known` は、Let's Encrypt（certbot）が証明書を更新するたびに外から開いて確かめる場所（`/.well-known/acme-challenge/`）で、掛けると更新が失敗し、気付かないと証明書が切れます。`security.txt` など、外から読まれる前提のものも置かれます。訪問者画面に掛けても管理画面には掛からず、管理画面に掛けても訪問者画面には掛かりません。
- **ユーザー**：訪問者画面と管理画面で別々に持ちます。同じユーザー名を両方に作っても、別のユーザーです。追加と削除だけです。パスワードを変えるときは、削除して追加し直します。ユーザー名は半角の英数字と `-`・`_`・`.` で、`:` は使えません（ユーザー名とパスワードの区切りのため）。パスワードは8文字以上です。
- **締め出さない**：範囲ごとに、ユーザーが0人のときは掛けられず、掛けている間は最後のユーザーを削除できません。
- **操作ログ**：「ベーシック認証の変更」「ベーシック認証のユーザーの追加」「ベーシック認証のユーザーの削除」で残します。補足には範囲とユーザー名だけを残し、パスワードは残しません。
- **パスワードの総当たりは止めません**：認証するのは Laravel より手前の Apache なので、失敗の回数を数えられません。要るときは、サーバーの fail2ban などで止めます。
- **アクセス制限との関係**：別の仕組みで、互いに影響しません。アクセス制限で通している IP アドレスにも、ベーシック認証は掛かります。
