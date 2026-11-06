# 仕様メモ：画面と JS・CSS の作り直し（auto-rebuild）

- 実装プロンプト：`docs/prompt/auto-rebuild.md`
- 実装完了日：2026-11-07
- 計画書の段階4-15

## A. 概要

**目的・解決する課題**

コンパイル済みの画面は apache が書けない場所（`bootstrap/cache/views`）に置いているので、Blade を差し替えるたびに、シェルで `view:cache` を流す必要があった。JS・CSS も `npm run build` が要る。デザイナーのちょっとした文字の直しでも、シェルを使うことになっていた。

**変えたこと**

- コードを更新する利用者（devapp）の cron が毎分、元のファイルの変化を見つけて、画面と JS・CSS を作り直すコマンド `neobit:rebuild` を足した
- サーバーでは、送ってから作り直すまで前の画面を出すよう、`config/view.php` に `check_cache_timestamps` を足した

**変更したファイル**

| 場所 | ファイル |
|---|---|
| フレームワーク | `src/Operations/AssetRebuilder.php`・`src/Console/Rebuild.php`・`config/neobit/rebuild.php`（新規）、`NeobitServiceProvider.php`・`stubs/config/neobit.php` |
| 見本のサイト | `config/view.php`・`.env.example`・`.gitignore` |
| 道具 | `set_permissions.sh`・`check_latest.sh` |
| テスト | `tests/Feature/RebuildTest.php`（新規） |
| 文書 | 利用ガイド（deploy.md の「画面と JS・CSS の作り直し」）・名前の一覧・`docs/new-project-files.md`・計画書 |

## B. 実装仕様

**変わったかどうか**：元のファイルの一覧（パス・大きさ・日時）を並べたもののハッシュ値を、`bootstrap/cache/rebuild.json` の値と比べる。種類ごと（画面・JS と CSS・パッケージ）に持つ。

| 種類 | 見るもの |
|---|---|
| 画面 | 登録された画面の場所の全部の `*.blade.php`（`view:cache` と同じ場所） |
| JS・CSS | `resources/js`・`resources/css`・`vendor/neobit/core/resources/js`・`css`・`vite.config.js` |
| パッケージ | `package.json`・`package-lock.json` |

**画面**：Laravel のコンパイラを複製して置き場所だけを `bootstrap/cache/views_next` に変え、全部をコンパイルする。置き場所の中の `.php` でないファイル（`.gitignore`）を写してから、`views` を `views_old` に変え、`views_next` を `views` にし、`views_old` を消す。途中で止まったら `views_old` を戻す。名前の変更は、つかまれていて失敗したら少し待ってやり直す（Windows のウイルス対策など）。

**JS・CSS**：`npm run build -- --outDir public/build_next` で作り、`manifest.json` ができていれば、`public/build` を `public/build_old` に変えてから `public/build_next` を `public/build` にし、`build_old` を消す。

**失敗**：入れ替えずに前のものを残し、`Log::error()` に書く（エラーの通知のメール）。しるしは記録するので、元のファイルがまた変わるまでは作り直さない。ビルドの失敗は、出力の最後の20行をログに残す。

**パッケージ**：変わっていたら JS・CSS は作らず、「npm ci と npm run build を流してください」とエラーで知らせる。画面は作り直す。

**初回**：記録が無ければ、作り直さずにしるしだけを記録する。

**重ねて動かさない**：`bootstrap/cache/rebuild.lock` を `flock` で押さえる。押さえられなければ何もせずに終わる。

**設定**

| 設定 | 既定 | `.env` |
|---|---|---|
| `neobit.rebuild.npm` | `npm` | `REBUILD_NPM` |
| `view.check_cache_timestamps`（Laravel の項目） | `true` | `VIEW_CHECK_CACHE_TIMESTAMPS`。サーバーは `false` |

## C. 前提・依存

- パッケージの追加・更新は無い
- サーバーで devapp が `npm` を使え、`bootstrap/cache/views`・`public`・`node_modules` に書けること（`set_permissions.sh` が確かめる）
- devapp の cron が動くこと。cron の無いサーバー（`CRON_ENABLED=false`）では動かない

## D. 設計判断とトレードオフ

| 判断 | 選んだもの | 選ばなかったもの |
|---|---|---|
| 作り直す人 | devapp の cron | apache が画面を開いたときに作り直す（Laravel の標準）：apache の権限で書かれた PHP を実行することになり、侵入されたときの裏口と、改ざん監視の見えない場所になる |
| 変わったかの見方 | ファイルの一覧のしるし | 元のファイルがコンパイル済みのものより新しいか：消したファイルに気付けない |
| 送ってから作り直すまで | `check_cache_timestamps` を `false` にして前の画面を出す | そのまま：最大1分、直した画面がエラーになる |
| 画面の作り直し | 仮のディレクトリにコンパイルして入れ替える | その場で作り直す：中身の変わらない画面で、コンパイラが日時を更新しようとし（`touch()`）、持ち主でない利用者（`view:cache` を流した人）が作ったファイルでは失敗する。開発サーバーで実際に起きた |
| JS・CSS の作り直し | 仮のディレクトリに作って入れ替える | `public/build` に直接作る：Vite が先に空にするので、作っている間は見た目と動きが崩れる |
| 失敗した後 | 元のファイルがまた変わるまで作り直さない | 毎分やり直す：同じ失敗のメールが毎分届く |
| `npm ci` | 人が行う | 自動で流す：外からパッケージを取ってくる操作を、確かめずに行うことになる |

## E. 課題・懸念点

| 重要度 | 内容 |
|---|---|
| 中 | ファイルを消したとき、使っているところ（`@include`・コントローラー・`vite.config.js`・`@vite()`）が残っていると、作り直した後にその画面がエラーになる。今の手作業と同じ決まりで防ぐ |
| 低 | `public/build` の入れ替えの2回の名前の変更の間（ごく短い間）は、JS・CSS が見つからない |
| 低 | `bootstrap/cache/views` の入れ替えの2回の名前の変更の間（ごく短い間）は、画面が見つからない |

## F. セキュリティ・権限・データ整合性の注意

- コンパイル済みの画面と JS・CSS を書くのは devapp だけで、apache には書かせない。`set_permissions.sh` が「apache は `public/build` に書けない」も確かめる
- 作り直した結果も、改ざん監視が変わったファイルとして知らせる。デザイナーは、作り直しを待ってから管理画面で承認する

## G. テスト状況

- 全部のテスト：552件のうち551件が通り、1件はスキップ（`FileWatchTest` の root でしか確かめられないもの）
- `RebuildTest`（6件）：初回はしるしだけ、画面の足し・消し、JS の作り直しと入れ替え、ビルドの失敗で前のものが残り作り直さないこと、パッケージが変わったときは作らないこと、ロック
- 手元で、`php artisan neobit:rebuild` を流し、Blade と JS を書き換えると、画面と `public/build` が作り直され、変わっていなければ何もしないことを確かめた
- 開発サーバーでの確かめは、まだしていない

## H. 今後の改善提案・TODO

- 開発サーバーで、デザイナーの手順（送る・待つ・承認する）を通して確かめる
