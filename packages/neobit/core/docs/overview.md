# 全体像

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

## 動作条件

| | 最低バージョン | 根拠の代表例 |
|---|---|---|
| PHP | 8.3 | Laravel 13 本体が PHP 8.3 以上を要求する（`laravel/framework` の composer.json が `"php": "^8.3"`）。フレームワーク自身のコードで使っている一番新しい書き方は、列挙型の定数のキーに case の値を使う `self::Gender->value => [...]`（PHP 8.2 から。`OperationLogAction::LABELS`） |
| Laravel | 13 | 見本のサイト（memsys）の composer.json が `"laravel/framework": "^13.17"` で、この版で開発・動作確認している。見本のサイトのコードで使っている機能の一番新しいものは、モデルのスコープを属性で書く `#[Scope]`（Laravel 12 から。`News::visibleTo()`） |

- PHP の最低バージョンは、フレームワーク自身の書き方ではなく、Laravel 13 の要求で決まっています。
- Laravel 12 でも動く可能性はありますが、動作を確かめていないので対象外とします。新しい案件は、その時点の最新の Laravel で始める前提です。
- このほか、パスキー（[login.md](login.md)）を使う場合は、ブラウザで開くアドレスが HTTPS であること（開発時の `localhost` は例外）が必要です。
- 新しい機能を足して、ここに書いたより新しい PHP・Laravel の機能を使ったときは、この表を直します。

## コントローラーの並び順

どのコントローラーも、次の順にコメントのブロックで区切って書きます。

```php
class NewsController extends Controller
{
    // ---- 共通処理（トレイト） ----
    use SearchableList;
    use FormFlow;
    use AjaxFileUpload;
    use CsvDownload;
    use CsvImport;

    // ---- 一覧・検索（SearchableList）の設定 ----
    private const INDEX_ROUTE = 'admin.news.index';
    // ...

    // ---- アップロード（AjaxFileUpload）の設定 ----
    private const UPLOAD_FILES = [...];

    // ---- このコーナーの項目の定義 ----
    private function rules(): array { ... }
    private function formValues(...): array { ... }
    // defaultValues()・prepareInput() は必要なときだけ

    // ---- 一覧・検索 ----
    public function index(...) { ... }
    private function searchRules(): array { ... }
    private function applyCustomSearch(...): bool { ... }

    // ---- CSVダウンロード ----  ---- CSV取り込み ----
    // ---- 登録 ----
    // ---- 詳細・編集・削除 ----

    // ---- 保存・削除処理の補助（FormFlow） ----
    private function saveValues(...): array { ... }
    private function afterSave(...): void { ... }
    private function beforeDelete(...): void { ... }
    // afterSave()・beforeDelete() は必要なときだけ
}
```

- 「このコーナーの項目の定義」には、検証のルールや入力欄の値のような、項目の定義を返すメソッドを置きます。
- 保存する値を作る `saveValues()` と、保存の直後・削除の直前に呼ばれる `afterSave()`・`beforeDelete()` は、ルートから呼ばれる入口のブロックの後ろに「保存・削除処理の補助」のブロックを作り、呼ばれる順に置きます。実際に保存・削除するのは入口の `store()`・`update()`・`destroy()` で、このブロックには、そこから呼ばれる部品を置きます。入口の後ろに、トレイトから呼ばれるメソッドのブロック（2段階認証の管理など）や、コントローラーの中で使い回す処理のブロックがあるときは、それらより前に置きます。

- 定数はクラスの冒頭にまとめ、何で使うものか（一覧・検索の設定、アップロードの設定など）でブロックに区切ります。
- 1か所からしか呼ばれない数行の処理は、細かいメソッドに分けず、コメントを1行入れて呼び元に書きます。
- トレイトの「必要なときだけ書くメソッド」は、トレイト側に何もしない版があります。コントローラーに同じ名前で書けば、そちらが使われます。

## 決まり

コードを書くときの決まり（コントローラー・データ項目・設定・テーブル・トランザクション・画面・コメント）は、[rules.md](rules.md) にまとめてあります。
