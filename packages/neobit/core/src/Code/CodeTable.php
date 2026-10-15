<?php

namespace Neobit\Core\Code;

use BackedEnum;
use Illuminate\Support\Str;
use Neobit\Core\Enums\CodeTableEnum;
use Neobit\Core\Models\Code;
use Neobit\Core\Models\OperationLog;
use ReflectionMethod;

/**
 * 値と名称の組の一覧であるコード表を読み込む。画面・検証・CSVのどこからでも、
 * code_table()・code_keys()・code_label()のヘルパーを通して使う。使う側はコード名だけを
 * 知っていればよく、どこにあるコード表かを知らなくてよい。
 *
 * ■ 出どころ
 * コード名から次の4つを探す。2つ以上にあれば、1つにするよう例外を投げる。
 * 1. 列挙型：コード名をクラス名の形にした列挙型。CodeTableEnumを実装していれば
 *    全部の値と名前の一覧にする。スタッフの権限のように、プログラムが値で動きを変えるもの。
 *    アプリの名前空間（config('neobit.code_table_enum_namespaces')。既定はApp\Enums\）を
 *    書いた順に探し、無ければフレームワークのNeobit\Core\Enums\を探す
 * 2. CSV：code/<コード名>.csv。都道府県のように選択肢として並べるだけのもの。
 *    ファイルを直すだけで項目を増やせる
 * 3. DB：config('neobit.code_table_db')に載せたコード名なら、code_tablesテーブルを表示順に読む。CSVと同じく
 *    並べるだけのもので、管理画面の項目見出し一覧から書き換えられる。値と名称の扱いはCSVと同じ
 * 4. メソッド：config('neobit.code_table_method')に、コード名 => [クラス, 静的メソッドの名前]で
 *    書いたもの。そのメソッドが返す「値 => 名称」の配列をそのまま使う。スタッフの一覧のように、
 *    ほかのテーブルから作るもの。設定に関数ではなく名前を書くのは、config:cacheで固められるようにするため
 *
 * フレームワークが自分で持つメソッドのコード表（CORE_METHODS）は、設定に書かなくても、上の4つより
 * 先に見る。操作ログの操作のように、フレームワークの部品がいつも読むコード表を、プロジェクトの
 * 設定の書き忘れで欠けさせないため。
 *
 * ■ CSVの形式
 * 「値,名称」を1行1件。
 * - 名称にカンマがあってもよいよう、最初のカンマだけで区切る。引用符で囲む書き方は求めない
 * - #で始まる行はコメント、空行は読み飛ばす
 * - 数字だけの値はintにする。前ゼロが除かれ、DBの数値の列とそのまま比べられる
 * - 名称の中の「\n」の2文字は改行にする。引用符で囲んで複数行にする書き方は、
 *   1行が1件という見やすさが崩れるので使わない
 * - ファイルが無いか開けないか、形が正しくないときは例外を投げる。コード表を直した人が
 *   それを使う画面を開いたときに、すぐ気付けるようにするため
 *
 * ■ 2階層のCSV
 * [東北] のように、[ ]で囲んだ見出しの行を書くと、その後ろの行は、次の見出しまで
 * その見出しの中に入る。地域ごとに分けた都道府県のような、まとまりのある選択肢に使う。
 *
 *   [東北]
 *   2,青森県
 *   3,岩手県
 *   [関東]
 *   13,東京都
 *
 * このときget()は、['東北' => [2 => '青森県', 3 => '岩手県'], '関東' => [13 => '東京都']]を返す。
 * 最初の見出しより前の行は、見出しの外の選択肢になる。
 * 2階層のまま使えるのは、プルダウンを出すcode_options()だけ。<optgroup>にまとめて出す。
 * code_keys()・code_label()や検証・CSVの出力は、1階層のコード表のためのものなので、
 * 2階層のコード表に使うときは、使う側で形を合わせる。
 */
class CodeTable
{
    private const DIRECTORY = 'code';

    /**
     * フレームワークの列挙型を置く名前空間。列挙型のコード表は、アプリの名前空間
     * （config('neobit.code_table_enum_namespaces')）を先に探し、最後にここを探す。
     */
    private const CORE_ENUM_NAMESPACE = 'Neobit\\Core\\Enums\\';

    /**
     * フレームワークが自分で持つ、メソッドのコード表。コード名 => [クラス, 静的メソッドの名前]。
     * operation_log_actionは、フレームワークの操作とサイトの操作を合わせたもの
     * （config('neobit.operation_log_actions')の列挙型）。operation_log_modelは、操作ログに出てくる
     * モデルの種類の名前（config('neobit.operation_log_models')の列挙型）。
     */
    private const CORE_METHODS = [
        'operation_log_action' => [OperationLog::class, 'actionOptions'],
        'operation_log_model' => [OperationLog::class, 'modelOptions'],
    ];

    // コード名ごとの読み込み済みのコード表
    /** @var array<string, array<int|string, string|array<int|string, string>>> */
    private static array $cache = [];

    /**
     * そのコード名の値と名称の一覧を返す。
     *
     * @param  string  $codeName  例：'prefectures'はcode/prefectures.csv、'staff_acl'はApp\Enums\StaffAcl
     * @return array<int|string, string|array<int|string, string>>  2階層のCSVなら、見出し => [値 => 名称]
     */
    public static function get(string $codeName): array
    {
        // 1回読んだら同じリクエストの中では使い回す
        if (array_key_exists($codeName, self::$cache)) {
            return self::$cache[$codeName];
        }

        // フレームワークが自分で持つコード表
        if (isset(self::CORE_METHODS[$codeName])) {
            return self::$cache[$codeName] = (self::CORE_METHODS[$codeName])();
        }

        // 4つの出どころのどこにあるかを調べ、2つ以上にあればエラー
        $enumNamespaces = [...(array) config('neobit.code_table_enum_namespaces', []), self::CORE_ENUM_NAMESPACE];
        $enumClass = $enumNamespaces[0].Str::studly($codeName);
        $isEnum = false;
        foreach ($enumNamespaces as $namespace) {
            $candidate = $namespace.Str::studly($codeName);

            if (enum_exists($candidate)
                && is_subclass_of($candidate, BackedEnum::class)
                && is_subclass_of($candidate, CodeTableEnum::class)) {
                $enumClass = $candidate;
                $isEnum = true;
                break;
            }
        }
        $path = base_path(self::DIRECTORY.'/'.$codeName.'.csv');
        $isDb = array_key_exists($codeName, (array) config('neobit.code_table_db', []));
        $method = ((array) config('neobit.code_table_method', []))[$codeName] ?? null;

        $sources = array_keys(array_filter([
            "列挙型（{$enumClass}）" => $isEnum,
            "CSV（{$path}）" => is_file($path),
            'DB（neobit.code_table_db）' => $isDb,
            'メソッド（neobit.code_table_method）' => $method !== null,
        ]));

        if (count($sources) > 1) {
            throw new CodeTableException("コード表（{$codeName}）が、".implode('と', $sources).'にあります。どれか1つにしてください。');
        }

        // メソッドのコード表：書いた静的メソッドを呼び、返した配列をそのまま使う
        if ($method !== null) {
            // モデルは無いメソッドの呼び出しもクエリへ回すので、is_callable()ではなく、メソッドが
            // 実際にあって、publicかつstaticであることを確かめる
            $callable = is_array($method) && count($method) === 2
                && is_string($method[0]) && is_string($method[1])
                && method_exists($method[0], $method[1])
                && (new ReflectionMethod($method[0], $method[1]))->isPublic()
                && (new ReflectionMethod($method[0], $method[1]))->isStatic();

            if (! $callable) {
                throw new CodeTableException("コード表（{$codeName}）を作るメソッドが呼べません。config('neobit.code_table_method')に、[クラス, 静的メソッドの名前]で書いてください: ".json_encode($method, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            return self::$cache[$codeName] = $method();
        }

        // DBのコード表：code_tablesを表示順に読む
        if ($isDb) {
            $options = [];
            $rows = Code::where('type', $codeName)->orderBy('sort_order')->orderBy('id')->get(['code', 'name']);
            foreach ($rows as $row) {
                $options[self::normalizeKey($row->code)] = self::normalizeLabel($row->name);
            }

            return self::$cache[$codeName] = $options;
        }

        // 列挙型：全部の値から、値と名前の一覧を作る
        if ($isEnum) {
            $options = [];
            foreach ($enumClass::cases() as $case) {
                $options[$case->value] = $case->label();
            }

            return self::$cache[$codeName] = $options;
        }

        // CSV：1行ずつ読む
        if (! is_readable($path)) {
            $message = "コード表が読み込めません（{$codeName}）: {$path}";
            throw new CodeTableException($message);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $message = "コード表が開けません（{$codeName}）: {$path}";
            throw new CodeTableException($message);
        }

        $options = [];
        $lineNumber = 0;

        // 今読んでいる行が入る見出し。見出しの行が出るまではnullで、見出しの外に入れる
        $group = null;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                // Excelなどで保存したCSVの先頭に付く、見えない印のBOMを除く
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            $line = trim($line, "\r\n ");

            // 空行とコメントの行は読み飛ばす
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // [東北] のような見出しの行。この後ろの行は、次の見出しまでこの中に入れる
            if (preg_match('/^\[(.+)\]$/u', $line, $matches)) {
                $group = trim($matches[1]);
                $options[$group] ??= [];

                continue;
            }

            // 最初のカンマで値と名称に分ける
            $parts = explode(',', $line, 2);

            if (count($parts) < 2) {
                fclose($handle);

                $message = "コード表{$codeName}の{$lineNumber}行目の形式が不正です: {$line}";
                throw new CodeTableException($message);
            }

            [$rawKey, $label] = $parts;

            if ($group !== null) {
                $options[$group][self::normalizeKey($rawKey)] = self::normalizeLabel($label);
            } else {
                $options[self::normalizeKey($rawKey)] = self::normalizeLabel($label);
            }
        }

        fclose($handle);

        return self::$cache[$codeName] = $options;
    }

    /**
     * CSVとDBで共通に、値の形をそろえる。前後の空白を除き、数字だけならintにする。
     * 前ゼロは除かれるので、'01'と'1'は同じ値1になる
     */
    public static function normalizeKey(string $rawKey): int|string
    {
        $rawKey = trim($rawKey);

        return ctype_digit($rawKey) ? (int) $rawKey : $rawKey;
    }

    /** CSVとDBで共通に、名称の形をそろえる。前後の空白を除き、「\n」の2文字を改行にする。 */
    private static function normalizeLabel(string $label): string
    {
        return str_replace('\\n', "\n", trim($label));
    }
}
