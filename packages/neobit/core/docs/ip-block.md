# IPブロック管理（IpBlocker）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Security/IpBlocker.php`（冒頭のコメント）・`src/Http/Middleware/RejectBlockedIp.php`・`src/Http/Controllers/IpBlockController.php`・`bootstrap/app.php`・`routes/admin.php`

不正な URL を続けて叩く BOT を、IP アドレスで止めます。Cloudflare などの WAF や fail2ban を入れられない環境で、PHP の側で身を守るためのものです。止めた IP アドレスからのアクセスには、どのページも 404 を返します。

- **止める場所**：全体のミドルウェア `RejectBlockedIp` です。ルートの無い URL（`/.env`・`/wp-login.php` など）も、ルートを探す前にここを通ります。止めている一覧はキャッシュに置き、毎回 DB は読みません。403 ではなく 404 にし、画面もルートの無い URL を開いたときと同じ 404 の画面にします。止められていることを、相手に気付かせないためです。Apache が PHP を通さずに返すファイル（画像・CSS など）は止まりません。
- **自動で止める**：応答が 404・405 になったアクセスを、IP アドレスごとに数えます。`config/neobit.php` の `security.ip_block_watch_minutes`（10分）の中に `security.ip_block_watch_count`（30回）を超えたら、`security.ip_block_hours`（24時間）止めます。`security.ip_block_auto` を false にすると数えず、管理画面からの登録だけになります。IPv6 は `/64` のまとまりで数えて止めます。
- **止めない IP アドレス**：アクセス制限（[access-restriction.md](access-restriction.md)）のサイトと管理画面の IP アドレスは、`.env` の分も画面の分も、自動でも手動でも止めません。管理画面からは、今の自分の IP アドレスを含むものも登録できません。管理者が自分を止めないようにするためです。
- **管理画面**：止めている IP アドレスの一覧と、手動の登録・期限の変更・解除です。IP アドレスは1つでも、`203.0.113.0/24` のようなまとまりでも登録できます。一覧と登録の欄には、実際に止まる範囲（`203.0.113.0 ～ 203.0.113.255`）を出します。まとまりは、広すぎると関係の無い人まで止めるので、IPv4 は `/16`、IPv6 は `/32` より狭いものだけにします。
- **ルート**：案件が書きます。機能を使うかどうかを、案件ごとに決めるためです。管理者だけのグループの中に、`Neobit\Core\Routing\FrameworkRoutes` の1行を書きます。

    ```php
    // routes/admin.php（管理者だけのグループの中）
    FrameworkRoutes::ipBlocks('/ip-blocks');
    ```

    ミドルウェアは、ルートを書かなくても動きます。管理画面を出さない案件でも、自動で止める仕組みは働きます。`bootstrap/app.php` の全体のミドルウェアに `RejectBlockedIp` を足してあることを確かめてください。
- **記録**：止めたこと・解除したこと・期限を変えたことを、操作ログに残します。自動で止めたものは、操作した人が空です。自動で止めたときは `warning` のログも書き、毎朝の操作ログの報告の「気になる点」にも載せます。期限の過ぎた行は、毎時の片付けが消します。
- **気を付けること**：手前にプロキシがある環境で `TRUSTED_PROXIES` が正しくないと、全員が同じ IP アドレスに見えます。その IP アドレスを止めると、サイト全体が止まります。IP アドレスを次々に変えて来る攻撃は止めきれません。それは WAF の領域です。

## 入口の見張り（Gatekeeper）

**ファイル**：`packages/neobit/core/gatekeeper.php`（冒頭のコメント）・`src/Security/Gatekeeper.php`・`src/Security/GatekeeperState.php`・`src/Console/CheckGatekeeper.php`、入口の `public/index.php`

IP ブロックとアクセス制限（[access-restriction.md](access-restriction.md)）の判断を、Laravel を起動する前にも行います。止めている IP アドレスの要求を Laravel の起動の前に返して、攻撃が続いているときの負荷を軽くするためです。Laravel を通らない PHP（WordPress など）にも、同じ守りを掛けられます。

- **掛け方**：`public/index.php` の頭で `gatekeeper.php` を `require` します。新しいプロジェクトも、見本のサイトの `public/index.php` を写します。

    ```php
    // IPブロックとアクセス制限を、Laravelを起動する前に判断する（Neobit\Core\Security\Gatekeeper）。
    // 止めるときは、ここで応答を返して終わる。見張りのファイルが無ければ、後ろのLaravelが判断する
    define('NEOBIT_GATEKEEPER_STATE', __DIR__.'/../storage/app/gatekeeper/state.json');
    if (is_file($gatekeeper = __DIR__.'/../vendor/neobit/core/gatekeeper.php')) {
        require $gatekeeper;
    }
    ```

- **判断は1つ**：見張りと、Laravel のミドルウェア（`RejectBlockedIp`・`RestrictSiteAccess`・`RestrictAdminAccess`）は、同じ判断のクラス `Gatekeeper` を使います。見張りが掛かっていない環境（`php artisan serve`・テスト）や、見張りの状態が古いときは、ミドルウェアが同じ判断をします。
- **状態のファイル**：見張りは Laravel を読み込まないので、Laravel が書き出した `storage/app/gatekeeper/state.json` を読みます。中身は、止めている IP アドレスと期限、サイトと管理画面の制限と許す IP アドレス（`.env` の分も）、管理画面の URL の頭、サイトの制限を掛けない URL、信頼するプロキシと、WordPress を置く案件では WordPress の URL の決まり（[wordpress.md](wordpress.md)）です。IP ブロックとアクセス制限を変えたときに、書き出し直します。JSON にしているのは、apache が書ける場所のファイルを、どの要求でも PHP のコードとして動かさないためです。
- **応答**：今までと同じです。止めている IP アドレスと管理画面の制限は 404、サイトの制限は 503 です。画面も、Laravel が返すものと同じ HTML を、書き出しのときに作って置いてあります（`404.html`・`maintenance.html`）。止められていることに、相手に気付かせないためです。
- **`.env` を変えたとき**：許す IP アドレスや `TRUSTED_PROXIES` を変えて `config:cache` をやり直したら、`php artisan neobit:gatekeeper --export` で書き出し直します（[deploy.md](deploy.md)の表）。忘れても、設定のキャッシュより状態のファイルが古ければ、次のアクセスで Laravel が書き出し直します。
- **確かめ**：`php artisan neobit:gatekeeper` で、書き出した日時・止めている IP アドレスの数・制限の状態を出します。状態のファイルが無いときや、今の状態と食い違っているときは、知らせて失敗で終わります。
- **書き出せないとき**：解除などで書き出しに失敗すると、エラーのログを書きます（エラーの通知のメールが届きます）。古い状態のままだと、見張りが解除した IP アドレスを止め続けるためです。`storage/app/gatekeeper/` に書けるかを確かめて、`php artisan neobit:gatekeeper --export` を実行します。状態のファイルを読めないときと、`gatekeeper.php` が無いとき（送り忘れなど）は、見張りは何もせずに通し、ミドルウェアが判断します。画面だけを作れないとき（案件が足したエラーの画面がコマンドから描けないなど）は、状態は書き出し、エラーのログを書きます。見張りは、画面の代わりに短い文を返します。
- **プロキシの後ろ**：見張りも、`TRUSTED_PROXIES` に書いたプロキシから届いたときだけ `X-Forwarded-For` を読みます。求め方は Laravel と同じです。
- **WordPress**：`index.php` を通らない WordPress には、PHP の `auto_prepend_file` か `wp-config.php` で見張りを掛けます（[wordpress.md](wordpress.md)）。
