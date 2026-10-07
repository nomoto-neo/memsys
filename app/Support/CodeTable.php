<?php

namespace App\Support;

use App\Enums\CodeTableEnum;
use App\Enums\CodeType;
use App\Models\Code;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * 値と名称の組の一覧であるコード表を読み込む。画面・検証・CSVのどこからでも、
 * code_table()・code_keys()・code_label()のヘルパーを通して使う。使う側はコード名だけを
 * 知っていればよく、どこにあるコード表かを知らなくてよい。
 *
 * ■ 出どころ
 * コード名から次の3つを探す。2つ以上にあれば、1つにするよう例外を投げる。
 * 1. 列挙型：コード名をクラス名の形にしたApp\Enums\の列挙型。CodeTableEnumを実装していれば
 *    全部の値と名前の一覧にする。スタッフの権限のように、プログラムが値で動きを変えるもの
 * 2. CSV：code/<コード名>.csv。都道府県のように選択肢として並べるだけのもの。
 *    ファイルを直すだけで項目を増やせる
 * 3. DB：App\Enums\CodeTypeにあるコード名なら、t_codesテーブルを表示順に読む。CSVと同じく
 *    並べるだけのもので、管理画面の項目見出し一覧から書き換えられる。値と名称の扱いはCSVと同じ
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

        // 3つの出どころのどこにあるかを調べ、2つ以上にあればエラー
        $enumClass = 'App\\Enums\\'.Str::studly($codeName);
        $isEnum = enum_exists($enumClass)
            && is_subclass_of($enumClass, BackedEnum::class)
            && is_subclass_of($enumClass, CodeTableEnum::class);
        $path = base_path(self::DIRECTORY.'/'.$codeName.'.csv');
        $isDb = CodeType::tryFrom($codeName) !== null;

        $sources = array_keys(array_filter([
            "列挙型（{$enumClass}）" => $isEnum,
            "CSV（{$path}）" => is_file($path),
            'DB（App\\Enums\\CodeType）' => $isDb,
        ]));

        if (count($sources) > 1) {
            throw new CodeTableException("コード表（{$codeName}）が、".implode('と', $sources).'にあります。どれか1つにしてください。');
        }

        // DBのコード表：t_codesを表示順に読む
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

    // CSVとDBで共通に、値の形をそろえる。前後の空白を除き、数字だけならintにする。
    // 前ゼロは除かれるので、'01'と'1'は同じ値1になる
    public static function normalizeKey(string $rawKey): int|string
    {
        $rawKey = trim($rawKey);

        return ctype_digit($rawKey) ? (int) $rawKey : $rawKey;
    }

    // CSVとDBで共通に、名称の形をそろえる。前後の空白を除き、「\n」の2文字を改行にする。
    private static function normalizeLabel(string $label): string
    {
        return str_replace('\\n', "\n", trim($label));
    }
}
