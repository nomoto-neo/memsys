# 仕様メモ：コンパイル済みの画面を Web の利用者から守る置き場所（compiled-views）

- 実装プロンプト：`docs/prompt/compiled-views.md`
- 実装完了日：2026-11-02

## A. 概要

**目的・解決する課題**

コンパイル済みの画面（`storage/framework/views`）は PHP としてそのまま動くのに、apache が書ける場所にあった。そのため次の問題があった。

- apache の権限で侵入されると、裏口を埋められる。`storage/` は改ざん監視の対象外なので、気付けない
- 改ざん監視の cron（devapp）がメールを作るときに読み込むので、コードを書ける権限で動かされる
- 持ち主が apache・devapp・作業者に混ざり、Blade の `touch()` が持ち主でないと失敗して、知らせのメールが止まった。失敗はログに残るだけで、エラーの通知のメールも同じ部品を通るので届かなかった

**変えたこと**

- コンパイル済みの画面を `bootstrap/cache/views` に移した。apache は読むだけ。改ざん監視の対象に入る
- デプロイの手順の `view:clear` を `view:cache` に変えた
- メールのテンプレートを、ファイルに書かずにメモリの上で展開するようにした
- `neobit::` の画面を登録するパスから `..` を取った

**変更したファイル**

| 場所 | ファイル |
|---|---|
| 見本のサイト | `config/view.php`（新規）・`bootstrap/cache/.gitignore`・`bootstrap/cache/views/.gitignore`（新規） |
| フレームワーク | `MailTemplate.php`・`NeobitServiceProvider.php`・`FileWatcher.php`（説明） |
| 道具 | `set_permissions.sh`・`check_latest.sh` |
| テスト | `tests/Feature/MailTemplateTest.php`・`tests/Feature/FileWatchTest.php` |
| 文書 | 利用ガイドの setup.md と file-watch.md・`docs/new-project-files.md`・`docs/project-overview.md`・`docs/file-watch-spec.md` |

## B. 実装仕様

**決まり**

| 場所 | apache | 中身 |
|---|---|---|
| `storage/` の全部 | 読み書き | データ。コードとして動くものは置かない |
| `bootstrap/cache/`（`views/` を含む） | 読むだけ | 設定のキャッシュ、コンパイル済みの画面、改ざん監視の記録 |

**置き場所**：`config/view.php` の `compiled` に `base_path('bootstrap/cache/views')` を書く。手元の開発環境も同じ。

**デプロイ**：`config:cache` の次に `php artisan view:cache`（`view:clear` は中で実行される）。鍵のファイルは、その後に更新する。

**メールのテンプレート**：`MailTemplate::expand()` が `Blade::compileString()` の結果を `eval` で展開する。`Blade::render()` は置き場所にファイルを書くので使わない。本文を包む `neobit::mail._raw_text` は普通のビューで、`view:cache` が作る。

**`set_permissions.sh`**：`bootstrap/cache/views` を作る（サーバーへ送らないため）。確かめに `bootstrap/cache/views に書けない` を足した。

## C. 前提・依存

- `view:cache` は、登録された画面の場所を実際のパスにしてコンパイルする。コンパイル済みファイルの名前はパスの文字列から作るので、登録するパスに `..` があると、画面を開くときに別のファイルとみなされる
- apache が新しいファイルを読めるのは、`bootstrap/cache` の既定の ACL による
- パッケージの追加・更新は無い

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 置き場所 | `bootstrap/cache/views` へ移す | `storage/framework/views` のまま権限を絞る：`storage/` と `storage/framework/` の持ち主も変える必要があり（親のディレクトリを持つと丸ごと差し替えられる）、`storage/` の中に書けない場所が混ざる。mPDF の作業用も先に作る必要がある |
| 場所の書き方 | `config/view.php` に直接 | `.env` の `VIEW_COMPILED_PATH`：書き忘れると、apache が書ける場所へ黙って戻る |
| コンパイルするとき | デプロイのときに全部 | apache に書かせる：侵入されたときに書き換えられる問題が残る |
| メールの展開 | メモリの上（`eval`） | `Blade::render()`：動いている最中に書くので、apache が書けないと止まる。テンプレートはプログラマーだけが直す前提なので、`eval` で扱う範囲は今と変わらない |
| devapp だけ別の置き場所 | しない | apache の画面は書けるままで、裏口の問題が残る |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | `view:cache` を忘れて画面を送ると、直した画面と知らせのメールが、書けずにエラーになる。利用ガイドの手順で毎回とした |
| 中 | devapp は apache が持つ `storage/logs` にログを書く。ログをシンボリックリンクに差し替えられると、その先へ追記させられる可能性がある。まだ確かめていない |
| 低 | `view:cache` の実行中は、作り直しの間だけ画面がエラーになることがある |
| 低 | `APP_DEBUG=true` のときの Laravel のエラーの詳細の画面（`laravel-exceptions-renderer`）は、登録のパスに `..` があるので作れず、簡単なエラーの表示になる |

## F. セキュリティ・権限・データ整合性の注意

- コードとして動くものを `storage/` に置かない
- コンパイル済みの画面の書き換えと追加は、改ざん監視で知らせる。`view:cache` は全部を作り直すので、鍵のファイルは必ず後に更新する
- `bootstrap/cache/views` はサーバーへ送らない。手元のコンパイル済みファイルで上書きしないため

## G. テスト状況

- `MailTemplateTest`：展開でファイルを書かないこと、置き場所が `bootstrap/cache` の下であること、`neobit::` のパスに `..` が無いこと
- `FileWatchTest`：コンパイル済みの画面の書き換えと追加を知らせること
- 手元で `view:cache` を流し、画面の場所ごとに、実際のパスで全部コンパイルされていることを確かめた。本体の画面116件・`neobit` 33件・`errors` 10件・`pagination` 9件・`notifications` 1件のすべて
- 登録のパスと実際のパスの比較で、`..` が残るのは `laravel-exceptions`・`laravel-exceptions-renderer`・`mcp` だけ。`laravel-exceptions` は `errors` と同じファイルで、表示には `errors` の登録を使う。`mcp` は開発用
- 手元の Windows ではパスの区切り文字が違うので、画面を開いたときにコンパイル済みファイルが使われることは確かめられない
- 開発サーバー（apache が書けない状態）で、次を確かめた。画面、存在しない URL の 404、お問い合わせのメール、PDF、改ざんの知らせのメール（5分ごとに届く）、`.version` での取り込み、`neobit:gatekeeper --export`
- 開発サーバーで、`packages/` を送ったのに `NeobitServiceProvider.php` の変更が反映されていないとみられる状態で `neobit::` の画面が `tempnam` のエラーになり、`packages/` を送り直して `view:cache` を流すと直った（OPcache の可能性は確かめていない）

## H. 今後の改善提案・TODO

- devapp のログの書き込み先を確かめ、必要なら apache が持たない場所へ分ける
- `view:cache` の忘れを `check_latest.sh` か手順で気付けるようにする
