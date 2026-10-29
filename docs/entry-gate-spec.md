# 仕様メモ：入口の見張り（entry-gate）

- 実装プロンプト：`docs/prompt/entry-gate.md`
- 実装完了日：2026-10-29
- 計画書の段階4-13 の A

## A. 概要

**目的・解決する課題**

IP ブロックとアクセス制限は、Laravel の全体のミドルウェアで判断していた。止めている IP アドレスの要求にも Laravel の起動（数十ミリ秒）を払ってから 404 を返していたので、BOT に大量に叩かれると負荷が大きい。また、Laravel を通らない PHP（WordPress など）には、この守りが掛からなかった。

そこで、同じ判断を、Laravel を起動する前に動く小さな見張り（`gate.php`）でも行う。WordPress と共存しないサイトでも、これを既定の形にする。

**影響範囲**

| 範囲 | 内容 |
|---|---|
| フレームワーク | 新規：`gate.php`（見張り）、`Gate`（判断。Laravel に頼らない）、`GateState`（状態を作って書き出す）、`CheckGate`（`app:gate`）。変更：`IpBlocker`・`AccessRestriction`（判断を `Gate` に任せ、変えたら書き出す）、`RejectBlockedIp`（状態が無いか古ければ書き出し直す）、`RestrictSiteAccess`（判断を `Gate` に任せる。`VIEW` を公開、`exceptPaths()` を足す）、`RestrictAdminAccess`（コメントだけ） |
| 見本のサイト | `public/index.php`（頭で `gate.php` を読む）、`tests/TestCase.php`（テストの間の `storage` を仮の場所にする） |
| サーバー | フレームワークのファイルと `public/index.php` を送る。毎回 `config:cache` の後に `php artisan app:gate --export` |

## B. 実装仕様

**流れ**

1. `public/index.php` が、Composer と Laravel を読む前に `gate.php` を `require` する。`gate.php` が無ければ読まない（送り忘れで、サイト全体を止めないため）
2. `gate.php` は、`Gate.php` だけを読み、`Gate::guard()` を呼ぶ。2回目（`auto_prepend_file` と両方で掛かったとき）は何もしない
3. `guard()` は、状態のファイル（`storage/app/gate/state.json`）を読み、訪問者の IP アドレスとパスから判断する。止めるなら、書き出しておいた画面を送って `exit`。通すなら何もせずに戻り、Laravel が続く
4. Laravel の側では、今までどおりミドルウェアが同じ判断をする。状態は `GateState::build()` で作り、ファイルは読まない

**判断**（`Gate::decide()`）

| 順 | 当たるとき | 応答 |
|---|---|---|
| 1 | 止めている IP アドレス（期限が今より後か、期限なし） | 404 |
| 2 | WordPress のいつも隠す URL（`wordpress.hidden`。WordPress を置くときだけ） | 404 |
| 3 | WordPress のいつも通す URL（`wordpress.open`） | 通す |
| 4 | 管理画面の URL（`admin_path` か、その下）と、WordPress の管理画面とログイン（`wordpress.admin`） | 管理画面の制限の外なら 404。中なら通す |
| 5 | サイトの制限を掛けない URL（`site_except`）か、サイトの制限の中か、管理画面の許す IP アドレス | 通す |
| 6 | それ以外 | 503（メンテナンス中の画面） |

WordPress の分と、5 の管理画面の許す IP アドレスは、2026-10-29 に WordPress との共存（`docs/wordpress-coexist-spec.md`）で足した。

**状態**（`GateState::build()`。見張りは、これに `exported_at` を足した JSON を読む）

| キー | 中身 | 出どころ |
|---|---|---|
| `blocks` | `[IP アドレスかまとまり, 期限の UNIX 時刻か null]` の並び | `IpBlocker::blockedList()`（DB。キャッシュ） |
| `site`・`admin` | `restricted` と `ips`（画面と `.env` を足したもの） | `AccessRestriction`（画面の分はキャッシュ、`.env` の分は毎回設定から） |
| `admin_path` | 管理画面の URL の頭 | 設定の `neobit.admin_path` |
| `site_except` | サイトの制限を掛けない URL | `RestrictSiteAccess::exceptPaths()`（`bootstrap/app.php` の `except()` と管理画面の下） |
| `trusted_proxies` | 信頼するプロキシ | 設定の `app.trusted_proxies`（`.env` の `TRUSTED_PROXIES`） |

**書き出し**（`GateState::export()`）

- 置き場所は `storage/app/gate/`。`state.json` と、見張りが返す画面の `404.html`・`maintenance.html`
- 仮のファイルに書いてから名前を変える
- 状態を先に書き、画面はその後に別に作る。画面を作れなくても（案件が足したエラーの画面がコマンドから描けないなど）、状態の書き出しは成功とし、画面の失敗はエラーのログに残す。状態の写しまで古いまま残さないため
- 画面は、`$withPages` が true のとき（`app:gate --export`、設定のキャッシュが新しくなったとき）と、まだ無いときだけ作る。404 は例外のハンドラーに `NotFoundHttpException` を渡して描かせ、メンテナンス中の画面は `neobit::maintenance` を描く。Laravel が返すものと同じ中身にするため
- 書き出すとき：IP の登録・期限の変更・解除・自動で止めたとき・期限切れの片付け（`IpBlocker::forgetList()`）、アクセス制限の保存と非常口（`AccessRestriction::save()`）。失敗したらエラーのログ（`exportOrReport()`）
- `RejectBlockedIp` が毎回 `refreshIfStale()` を呼ぶ。状態のファイルが無いか、設定のキャッシュ（`bootstrap/cache/config.php`）より古ければ書き出し直す。設定のキャッシュが無いとき（手元・テスト）は、無いときだけ。失敗したら、5分の間は書き出し直しもログも止める（攻撃の最中に、毎回の書き出しのやり直しで重くしないため）

**訪問者の IP アドレス**（`Gate::clientIp()`）

`$request->ip()`（`TrustProxies` を通した後）と同じ求め方にした。直接つないできた相手が信頼するプロキシのときだけ、`X-Forwarded-For` に直接の相手を足して、IPv4 の `:ポート`・IPv6 の `[]` を外し、正しくない形と信頼するプロキシを除いて、右端のものを訪問者とする。全部が信頼するプロキシなら、その最初のもの。`REMOTE_ADDR`・`PRIVATE_SUBNETS`（`private_ranges`）の書き方は Symfony と同じに扱う。

**IP アドレスの照らし合わせ**（`Gate::inRange()`）

`inet_pton()` で比べる。まとまりの長さのビットまでを比べ、IPv4 と IPv6 は互いに当たらない。正しくない形は当たらないものとして読み飛ばす。Laravel の側の `IpBlocker`・`AccessRestriction` も、Symfony の `IpUtils` からこれに替えた。

**パス**（`Gate::path()`・`matchesPath()`）

`REQUEST_URI` のパスを `rawurldecode` し、頭と末尾の `/` を外す。最上位は `/`。`$request->decodedPath()` と同じ形。`*` は `/` も含めて何文字でも当たる（`$request->is()` と同じ）。

**確かめのコマンド**（`php artisan app:gate`）

- オプションが無ければ、書き出した日時・止めている IP アドレスの数（期限の過ぎていないもの）・サイトと管理画面の制限の状態を出す
- 状態のファイルが無いときと、今の状態（`build()`）と食い違っているときは、知らせて失敗で終わる
- `--export` は、状態と画面を書き出し直してから、状態を出す

## C. 前提・依存

- 見張りは PHP の標準の関数だけで動く（`inet_pton`・`json_decode`・`filter_var`）
- 状態のファイルの場所は、`public/index.php` が定数 `NEOBIT_GATE_STATE` で渡す。渡さなければ、`gate.php` から3つ上（`vendor/neobit/core/` の形）をプロジェクトのルートとみなす
- `storage/app/gate/` に、Web の利用者と作業する人の両方が書けること（`set_permissions.sh` の形なら書ける）
- WordPress など、Laravel を通らない PHP には、`auto_prepend_file` か `wp-config.php` で掛ける（`docs/wordpress-coexist-spec.md`）

## D. 設計判断とトレードオフ

| 項目 | 決めたこと | ほかの案と、選ばなかった理由 |
|---|---|---|
| 判断の置き場所 | `Gate` の1つ。見張りとミドルウェアの両方が使う | 見張りに別に書く：同じ判断を2か所に持ち、食い違う |
| ミドルウェア | 残す。状態は `build()` から作り、ファイルは読まない | ファイルに一本化：ファイルが古いときに両方が同じ古い答えを出し、保険にならない。テストで設定を途中で変える確かめも通らない |
| 状態のファイルの形 | JSON | PHP の配列（OPcache が効く）：`storage/` は apache が書ける場所なので、書き換えられるとどの要求でもそのコードが動く |
| IP の照らし合わせ | 自前（`inet_pton`） | Symfony の `IpUtils`：見張りは Composer を読まないので使えない。同じ答えになることはテストで確かめた |
| 画面 | 書き出しのときに Laravel のビューから作る | 見張りが短い文を返す：止められていることに気付かれる。今までのテストも「ルートの無い URL と同じ 404」を確かめている |
| 画面を作る時 | `--export`・設定のキャッシュの更新・無いときだけ | 毎回の書き出しで作る：自動で止めたとき（要求の途中）にビューを2つ描く分、重くなる |
| 書き出しの漏れ | 設定のキャッシュより古ければ、次のアクセスで書き出し直す | 人が `app:gate --export` を忘れない前提：`.env` の許す IP を変えても見張りに移らず、締め出しや止め損ないが起きる |
| コマンド | `app:gate` の1本。既定は状態を出す、`--export` で書き出し直す | 2本に分ける：利用者の希望で1本にした |
| テストの `storage` | `tests/TestCase.php` で、テストごとに仮の場所にする | そのまま：テストを流すと手元の状態のファイルが書き換わる |

## E. 課題・懸念点

| 重さ | 内容 |
|---|---|
| 中 | 写しが古いまま書き出せない（`storage/app/gate/` に書けない）と、見張りは解除した IP アドレスを止め続ける。止められた人の要求は Laravel に届かず、Laravel の側では直せない。エラーのログ（エラーの通知のメール）と `app:gate` の食い違いの知らせで、人に直してもらう |
| 中 | メンテナンス中の画面を案件で上書きしたとき（`resources/views/vendor/neobit/maintenance.blade.php`）は、`app:gate --export` まで見張りの画面が古いまま。0章の手順で毎回流すので、デプロイの後には直る |
| 中 | 見張りが返す 404・503 には、`SecurityHeaders` のヘッダーが付かない。中身は書き出した固定の HTML だけなので害は小さいが、Laravel の 404 にはヘッダーが付くので、見比べると止められていることが分かる |
| 低 | 入口の `index.php` の変数 `$gate` は、PublicFiles で動かす古い `.php` からも見える（`$app`・`$script` と同じ） |
| 低 | 管理画面の制限は、Laravel では管理画面のルートだけに掛かるが、見張りは管理画面の URL の下の全部に掛かる。ルートの無い URL も 404 なので、見た目は変わらない |
| 低 | `TRUSTED_PROXIES=*` は、Laravel の側で `['*']` の配列になり、`TrustProxies` の「全部を信頼する」に当たらないので、何も信頼しない。見張りも同じ答えにそろえた（テストで確かめた）。`*` を使いたい案件が出たら、`config/app.php` の作り方から直す |
| 低 | 置き方 B（Laravel の本体を公開ディレクトリの中に置く）で、Laravel が公開ディレクトリの下の階層で動くときは、見張りのパスに頭が付き、管理画面の URL と合わない。今の置き方 B は入口が公開ディレクトリの直下なので当たらない |

## F. セキュリティ・権限・データ整合性の注意

- 状態のファイルは JSON。書き換えられても、読み間違えるか、読めずに通すだけで、コードは動かない。読めないときはミドルウェアが判断するので、守りは外れない
- 状態のファイルを書き換えられたとき、制限を外す向き（許す IP を足す、止めている IP を消す）なら、後ろのミドルウェアが DB から判断するので、Laravel の画面は守られる
- 止める向き（`0.0.0.0/0` を止める一覧に足すなど）では、要求が Laravel に届かず、サイト全体が 404 や 503 のままになる。Laravel の側では直らないので、サーバーで `php artisan app:gate --export` を実行して書き出し直す
- 見張りは、信頼するプロキシから届いたときだけ `X-Forwarded-For` を読む。ほかから届いたものは使わない
- 書き出しは、仮のファイルから名前を変える形なので、読みかけの見張りが半端な中身を読むことは無い

## G. テスト状況

- `tests/Unit/GateTest.php`（7件）：IP アドレスとまとまりの照らし合わせ（IPv4・IPv6・`/0`・正しくない形）、期限、止めている IP アドレスはどこでも 404、サイトの制限と制限を掛けない URL、管理画面の制限が管理画面の下だけに掛かること、パスの読み方、信頼するプロキシからだけ `X-Forwarded-For` を読むこと
- `tests/Feature/GateStateTest.php`（9件）：登録・解除・アクセス制限の保存で書き出されること（`.env` の分・信頼するプロキシ・制限を掛けない URL も）、見張りを別の PHP で動かして Laravel と同じ 404 とメンテナンス中の画面を返すこと、状態が読めないときは通してミドルウェアが止めること、訪問者の IP アドレスが `$request->ip()` と同じになること（10通り）、無いときと設定のキャッシュより古いときに書き出し直すこと、解除の後の書き出しの失敗がエラーのログになること、画面が描けなくても状態は書き出されること、`app:gate` の出力と食い違いの知らせと `--export`
- 今までの IP ブロック・アクセス制限のテストは、そのまま通る。全体で 502件（成功 501件、飛ばし 1件）
- 手元で、`php -S` で `public/index.php` を通し、最上位が 200、ルートの無い URL が 404 になることを確かめた

2026-10-29 に開発サーバーで確かめた。止める IP には、サーバーの中から `curl --resolve dev.neobit.jp:443:127.0.0.1` で開いた 127.0.0.1 を使った。

- `app:gate --export` で3つのファイルを書き出せた。`storage/app/gate` は既定の ACL で apache が書ける
- 管理画面での止める・解除・アクセス制限の保存で、apache が状態のファイルを書き直し、`app:gate` は食い違いを知らせない
- サイトの制限の外は 503、管理画面の下は 200。止めた IP は、最上位も管理画面の下も 404。解除すると、すぐに戻る
- 503 と 404 の応答には `X-Frame-Options`・`Cache-Control`・`Set-Cookie` が無く、Laravel を起動せずに見張りが返している。`X-Content-Type-Options` だけは、そのサーバーの Apache の設定で付く
- 許す IP（サイトの制限の許す IP を含む）に入れた IP は、IP ブロックの画面で止められない。試すときは、先に許す IP から外す

## H. 今後の改善提案・TODO

- 見張りが止めた回数を数えられると、攻撃の量が分かる。数えるとファイルへの書き込みが増えるので、要るかは運用を見て決める
- 見張りが返す応答に、`SecurityHeaders` と同じヘッダーを足すか。書き出しのときに、Laravel の 404 の応答のヘッダーも書き出せば、見比べても分からなくなる。Apache の HTTPS の VirtualHost に `Header always set` で書けば付くことを、2026-10-30 に開発サーバーで確かめた（利用ガイドの31章）。WordPress を置かないサイトでも同じ書き方で付く
