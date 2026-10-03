<laravel-boost-guidelines>
=== .ai/neobit-framework rules ===

# ネオビットフレームワーク（このリポジトリの決まり）

このリポジトリ（memsys）は、Laravel の上に「コーナー（一覧・詳細・登録・編集・削除をひとまとめにした管理単位）を同じ型で作るための共通部品」を載せた「ネオビットフレームワーク」の見本のサイト。共通部品は `app/Support/`・`app/helpers.php`・`resources/views/` 直下の部分ビュー・`resources/js/` にある。

- 部品の使い方の全体は `docs/neobit-framework-guide.md`（利用ガイド）。新しいコーナーや機能を作る前に、該当する章を読む
- CSV取り込みの設計は `docs/csv-import-spec.md`、パスキーの設計は `docs/passkeys-spec.md`
- 各部品の細かい仕様は、それぞれのファイルの冒頭のコメントに書いてある
- 迷ったら、同じ機能を使っている既存のコーナー（ニュース・会員・スタッフ・カテゴリー）を写す
- 動作条件は PHP 8.3 以上・Laravel 13 以上。古い版への配慮は要らない

## Boost の一般的な指示との優先順位

このファイルの決まりと、Laravel Boost のガイドライン・スキル（`laravel-best-practices` など）が食い違うときは、このファイルを優先する。特に次の点。

- コメント：PHPDoc だけにせず、処理の意図や理由を日本語のコメント（行内のコメントを含む）で書く。既存のファイルと同じ密度で書く
- 検証：Form Request は使わず、コントローラーの `rules()` に書く（必須マークを `rules()` から組み立てるため）
- 画面：CSS は Bootstrap（レイアウトで CDN から読み込み）。Tailwind CSS のクラスは使わない
- デプロイ：Laravel Cloud は使わない。自前の VPS（AlmaLinux）へ人が送る

## コードの書き方

- コメントには、今のコードが何をしていて、なぜそうなっているかだけを書く。試行錯誤の経緯（以前はこうだった、名前を変えた理由など）は書かない
- コメントと画面の文言は日本語
- コメントだけを上から読めば、処理の流れと分岐が追えるように書く。処理の段落ごとに、何をするかを1〜2行で端的に書く
- 三項演算子は、短く1行で読めるときだけ使う。枝の中が長くなるときは if に分け、枝ごとに「どんなときか」をコメントで書く
- 説明は、それが当てはまる行のすぐそばに置く。メソッド・クラスの上には、全体の目的と「なぜそうするか」を書く
- クラス・メソッドの上の説明は、要点を短い文でまとめる。1文目に何をするものかを書き、理由はその後に続ける。補足は括弧書きにせず、別の文にする
- クラスの中の説明で2行以下のものは、行コメント（//）にする。ブロックコメント（/** */）は、3行以上の説明か、@param・@returnなどの型の注記を書くときだけ使う
- 長い配列は、要素ごとに行を分ける
- 管理画面のコントローラーは FormFlow の形にする。入口の public メソッドは残し、「共通処理（トレイト）」「〇〇の設定」「このコーナーの項目の定義」「一覧・検索」「登録」「詳細・編集・削除」のようにコメントのブロックで区切る（並び順は利用ガイド1-3）
- 定数はクラスの冒頭にまとめ、何で使うものかでブロックに区切る
- 保存する項目名は `saveFieldNames()` で明示する。入力値をそのまま使わずに保存する項目は `additionalFields()`
- 検証のルールは `validate([...])` に直接書かず `rules()` に切り出す（必須マークを `rules()` から組み立てるため）
- 1か所からしか呼ばれない数行の処理は、細かいメソッドに分けず、コメントを1行入れて呼び元に書く
- 生成物の形を決める呼び出し（`downloadCsv()`・`CsvImportSettings`・`downloadPdf()` など）は、引数に既定の値を持たせず、呼ぶ側は名前付き引数で全部書く
- 共通部品に、個々の画面の都合の一覧を持たせない。画面ごとに決まる値は、コントローラーに用途の分かる名前の定数で持つ（例：`THROTTLE_SCOPE`）
- データ項目の仕様（画像の横幅など）はモデルの定数に持ち、コントローラーや画面はそれを参照する
- 定義の中の値の変換は、書式の変換は `'カラム|date:Y/m/d'` のような短い記法、外から渡す一覧による置き換えは配列、特殊な変換はクロージャではなく英字の識別名（`'@age'`）でコントローラーのメソッドを呼ぶ
- 選択肢のように決まった種類の値は `app/Enums/` の列挙型にする。値ごとの名前や設定は、`match` でメソッドの中に書かず、冒頭の定数（キーは `self::Xxx->value`）にまとめる
- 区分表は、出どころ（列挙型・`code/*.csv`・`t_codes`）に関係なく `code_table()`・`code_keys()`・`code_label()` で使う。Blade に `\App\Enums\...` を書かない
- コントローラーやテンプレートの書き方を、アップロードや WYSIWYG の有無で変えない

## テーブル

- 業務のデータと、業務の側で参照したり古い行を消したりする記録は `t_` を付ける
- 認証の保持・試行制限のようなフレームワーク内部のテーブルは `t_` を付けず、会員・スタッフ共通の1つにして `authenticatable_type`・`authenticatable_id` で区別する。新しい認証対象のモデルを足したら `AppServiceProvider` の `Relation::enforceMorphMap()` にも足す
- 外部キー制約は付けない。関連の片付けはコードで行う
- DB への書き込みが2つ以上続く処理は `DB::transaction()` で囲む。ファイルの削除とメールの送信は、トランザクションが確定した後に行う

## 画面（Blade）

- テンプレートは細かく部品化しない。デザイナーが触れる HTML のまま保つ。`<x-...>` のコンポーネントはあまり使わない
- Blade にコントローラー名は書かない。共通の部分ビューにはモデル名も書かない（コーナー専用のテンプレートが、そのコーナーのモデルの定数を参照するのはよい）
- コーナーの入力欄は `_fields.blade.php` 1つにまとめ、新規登録・編集・確認・詳細の4画面から呼ぶ
- エラー欄は `<div class="invalid-feedback" data-item="項目名">{{ $errors->first('項目名') }}</div>`。`<div>` と `{{ }}` の間に改行や空白を入れない
- `config/filesystems.php` の `url` は `/` で始まるパスにする（`http://` から始めない）

## 作業の進め方

- 手元の開発環境は Windows 11（PowerShell）、PHP 8.3、MariaDB 10.11（`memsys_local`）。メールはレンタルサーバーの SMTP で実際に送る
- 本番・開発サーバーは AlmaLinux 10＋Apache＋PHP-FPM＋MariaDB。サーバーには触らない（サーバーへの送信・デプロイは人が行う）
- DB を消すコマンド（`migrate:fresh`・`db:wipe` など）、`git push`、パッケージの追加・更新は、実行する前に確認を取る
- 手元の DB は `php artisan migrate:fresh --seed` で作り直せる前提。サーバーのデータは持ってこない（`APP_KEY` が違うので、暗号化した列を復号できない）
- 共通部品を足したり使い方を変えたりしたら、`docs/neobit-framework-guide.md` の該当する章と、冒頭の版の表も直す

## コミットのメッセージ

1行目の頭に、次の3つのどれかの目印を付ける（例：`add: ニュースに掲載開始日時・掲載終了日時を追加`）。コードやファイルの上で足したか消したかではなく、その変更の主な目的で選ぶ（ファイル単位の細かな追加・削除は判断の材料にしない）。対象はサイトの画面や機能に限らず、プロジェクトの決まり・共通部品（モジュール）・設定やドキュメントのファイルも同じように考える。

- `add:` 今までなかったものを足す。機能や仕様、ページや情報などの文章、決まり、共通部品の追加など
- `update:` 今まであったものを、新しいものに差し替える。既存の機能の改修や代わりの機能への差し替え、文章や決まりの書き換えなど
- `fix:` 間違いや不具合を正す、または働きを変えずに整える。組み込み済みの機能のバグの修正、決まりや文章の誤りの訂正、機能に影響しないコードやコメントの整理など

1回の作業に種類の違う変更が混ざるときは、種類ごとにコミットを分ける。

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
