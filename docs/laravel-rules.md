# Laravel・PHP の決まり

Laravel を使っているプロジェクトで、コードを書くときの決まり。Laravel Boost が配るガイドラインを元に、このプロジェクトの決まりと食い違う所を直してまとめたもの。

Laravel Boost は、ガイドラインを `CLAUDE.md` へ書き出さない設定にしてある（`boost.json` の `guidelines` が `false`）。`CLAUDE.md` はどのプロジェクトでも共通の決まりだけにして、Laravel の決まりはこのファイルに置くため。`php artisan boost:install` をやり直すときは、「AI Guidelines」を選ばない。MCP の道具とスキルは、今までどおり使う。

## 版を確かめる

使っているパッケージの版に合った API を使う。版を思い込みで決めない。

- PHP のパッケージ：`composer show --direct` で、直接使っているパッケージと版の一覧が出る。1つだけなら `composer show <vendor/package>`
- JavaScript のパッケージ：`package.json` を見る

## スキル

`.claude/skills/` に、分野ごとのスキルがある。その分野の作業をするときは、行き詰まる前に読む。

- `laravel-best-practices`：Laravel のコードを書く・見直す・直すとき
- `testing-best-practices`：テストの対象の選び方、名前、構成、検証の仕方、依存の切り離し方

スキルの内容がプロジェクトの決まりと食い違うときは、プロジェクトの決まり（`docs/neobit-framework-rules.md` と `CLAUDE.md`）を優先する。

## Laravel Boost の道具

Laravel Boost は、このアプリ専用の道具を持つ MCP サーバー。シェルのコマンドやファイルの読み込みで代わりにするより、Boost の道具を先に使う。

- `database-query`：DB を読むだけの問い合わせ。tinker に SQL を書く代わりに使う
- `database-schema`：テーブルの構造。マイグレーションやモデルを書く前に見る
- `get-absolute-url`：プロジェクトの URL の、正しいスキーム・ドメイン・ポート。URL を伝える前に必ず使う
- `browser-logs`：ブラウザのログ・エラー・例外。役に立つのは新しいログだけで、古いものは無視する

### 文書を探す（search-docs）

Laravel まわりの API・動作・設定・版ごとの書き方に頼る変更をする前に、`search-docs` で調べる。文言だけの直しのように、パッケージの文書が関係しない変更では使わない。十分な結果がもう手元にあれば、探し直さない。

- 関係するパッケージが分かっていれば、`packages` の配列で絞る
- 広めの、話題ごとの問い合わせをいくつか並べる。例：`['rate limiting', 'routing rate limiting', 'routing']`。関係の深い結果が先に出る
- 問い合わせにパッケージの名前は入れない。パッケージの情報は別に渡っている。`filament 4 test resource table` ではなく `test resource table`

問い合わせの書き方。

1. 単語を並べると、語幹をそろえた AND になる。`rate limit` は「rate」と「limit」の両方を含むもの
2. `"引用符で囲んだ句"` は、その並びのまま一致するもの。`"infinite scroll"`
3. 単語と句は混ぜられる。`middleware "rate limit"`
4. OR にしたいときは、問い合わせを分ける。`queries=["authentication", "middleware"]`

## Artisan

- Artisan のコマンドは、コマンドラインで直接実行する（例：`php artisan route:list`）。使えるコマンドは `php artisan list`、引数は `php artisan [コマンド] --help` で確かめる
- ルートは `php artisan route:list` で見る。`--method=GET`・`--name=users`・`--path=api`・`--except-vendor`・`--only-vendor` で絞れる
- 設定の値は、ドットでつないだ名前で読む（`php artisan config:show app.name`）。`config/` のファイルを直接読んでもよい
- 新しいファイルは `php artisan make:` のコマンドで作る（マイグレーション、コントローラー、モデルなど）。ふつうの PHP のクラスは `php artisan make:class`
- どの Artisan のコマンドにも `--no-interaction` を付け、入力を待たずに動くようにする。動きを決める `--options` も正しく付ける
- DB を消すコマンド（`migrate:fresh`・`db:wipe` など）は、実行する前に確認を取る

## Tinker

- デバッグや確認のために、アプリの中で PHP を実行できる。モデルは、利用者の了承なしに作らない。確認にはファクトリーを使うテストを先に考える。独自の tinker のコードより、既存の Artisan のコマンドを先に使う
- シェルに展開されないよう、必ず一重引用符で囲む。`php artisan tinker --execute 'Your::code();'`
  - 中の PHP の文字列は二重引用符にする。`php artisan tinker --execute 'User::where("active", true)->count();'`

## PHP の書き方

- 制御構造には、本体が1行でも必ず波括弧を付ける
- コンストラクタは、PHP 8 のプロパティの昇格で書く。`public function __construct(public GitHub $github) { }`。引数の無い空の `__construct()` は、private のとき以外は置かない
- メソッドには、戻り値の型と引数の型を必ず書く。`function isAccessible(User $user, ?string $path = null): bool`
- 列挙型のケースの名前は、先頭を大文字にした形にする。`FavoritePerson`・`BestLake`・`Monthly`
- PHPDoc には、配列の形の型の定義を使う
- コメントの書き方は `CLAUDE.md` に従う。PHPDoc だけにせず、行内のコメントも日本語で書く

## Laravel の書き方

- モデルを新しく作るときは、役に立つファクトリーとシーダーも作る。ほかに要るものがあるかは利用者に聞く。選べるものは `php artisan make:model --help` で確かめる
- API は、Eloquent の API リソースと API の版の分け方を既定にする。既存の API のルートがそうしていなければ、既存のやり方に合わせる
- ほかの画面へのリンクは、名前付きのルートと `route()` で作る
- 検証は Form Request を使わず、コントローラーの `rules()` に書く。理由は `docs/neobit-framework-rules.md`
- 画面の変更が反映されないときや、「Unable to locate file in Vite manifest」のエラーが出たときは、`npm run build` を実行する。または、利用者に `npm run dev` か `composer run dev` を実行してもらう
- デプロイに Laravel Cloud は使わない。公開の環境は、プロジェクトの概要（`docs/project-overview.md`）にある

## 整形（Pint）

- PHP のファイルを直したら、作業を終える前に `vendor/bin/pint --dirty --format agent` を実行して、プロジェクトの書式にそろえる
- `vendor/bin/pint --test --format agent` は使わない。`vendor/bin/pint --format agent` で直す

## テスト（PHPUnit）

- テストは PHPUnit。`php artisan make:test --phpunit {名前}` で作る。機能テストが既定で、単体テストは `--unit` を付ける。ほとんどは機能テストにする
- `{名前}` に、テストのディレクトリは入れない。`Feature/SomeFeatureTest` ではなく `SomeFeatureTest`
- テストで動作が確かめられるものに、確認用のスクリプトや tinker を作らない。単体テストと機能テストのほうが大事
- テストでモデルを作るときは、そのモデルのファクトリーを使う。手で値を組む前に、使える状態（state）がファクトリーにあるかを見る
- Faker は `$this->faker->word()` や `fake()->randomDigit()` のように使う。`$this->faker` と `fake()` のどちらにするかは、既存のテストに合わせる
- 変更に当たる、いちばん狭い範囲のテストを実行する。`php artisan test --compact` に、ファイルのパスか `--filter=テスト名` を渡す
- テストを直したら、そのたびに実行し直す
- `vendor/bin/phpunit` で、テストの実行を直接呼べる。ファイルのパスと `--filter=テスト名` は同じように渡せる
