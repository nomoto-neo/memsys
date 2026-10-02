<?php

namespace App\Support;

use App\Enums\CodeTableEnum;
use App\Enums\CodeType;
use App\Models\Code;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * コード表（値・名称の組の一覧）の共通読み込みクラス。
 * 以前のフレームワークのGetCodeArray(コード名)に相当する。
 * 画面・検証・CSVなど、どこから使うときもcode_table()・code_keys()・code_label()
 * （app/helpers.php）を通す。使う側はコード名だけを知っていればよく、一覧の出どころ
 * （下記の3つ）を知らなくてよい。
 *
 * ■ 出どころ
 * コード名（例: 'staff_acl'）から、次の3つを探す。
 * 1. 列挙型：App\Enums\<コード名をクラス名の形にしたもの>（例: App\Enums\StaffAcl）が、
 *    値を持つ列挙型（BackedEnum）で、App\Enums\CodeTableEnumを実装していれば、
 *    その全部の値（cases()）から [値 => label()] を作る。プログラムが値によって動きを
 *    変えるもの（スタッフの権限など）はこちら。
 * 2. CSV：code/<コード名>.csv。選択肢として並べるだけのもの（都道府県など）はこちら。
 *    プログラマー以外でも、ファイルを直すだけで項目を増やせる。
 * 3. DB：App\Enums\CodeTypeにコード名があれば、t_codesテーブルのその種類の行を
 *    表示順に読む。CSVと同じく選択肢として並べるだけのもので、管理画面
 *    （項目見出し一覧、Admin\CodeController）から書き換えられる。値・名称の
 *    扱い（数字だけの値はint型、名称の「\n」は改行）もCSVと同じ。
 * 2つ以上にあるとCodeTableExceptionを投げる（出どころを1つに保つため）。
 *
 * ■ CSVの形式
 * "値,名称" を1行1件。
 * - 名称にカンマが含まれてもよいよう、最初のカンマだけで区切る
 *   （fgetcsv()のような引用符エスケープを編集者に要求しない）。
 * - 先頭が#の行はコメントとして無視する。
 * - 空行は読み飛ばす。
 * - 値が数字だけで構成されていればint型に変換する（前ゼロを除去し、
 *   DBの数値型カラムとの比較にそのまま使えるようにするため）。
 *   数字以外の文字を含む値は文字列のまま扱う（コード表によっては
 *   キーが文字列の場合もあるため、行ごとに自動判定する）。
 * - 名称中に "\n"（バックスラッシュ＋n の2文字）があれば、実際の
 *   改行コードに変換する。標準的なCSVのダブルクォートによる複数行
 *   エスケープは、ファイルを見たときに「1行＝1レコード」という
 *   前提が崩れてメンテナンス時に読みにくくなるため、あえて採用していない。
 * - ファイルが無い・開けない・行の形式が不正な場合はCodeTableExceptionを
 *   投げる。コード表を書き換えた人が、それを使っている画面を開いた
 *   瞬間にエラーとして気づけるようにするため
 */
class CodeTable
{
    private const DIRECTORY = 'code';

    /** @var array<string, array<int|string, string>> コード名ごとの読み込み済みキャッシュ */
    private static array $cache = [];

    /**
     * 指定したコード名の一覧を [値 => 名称] の配列で返す。
     *
     * @param  string  $codeName  例: 'prefectures'（code/prefectures.csvを読む）、'staff_acl'（App\Enums\StaffAclから作る）
     * @return array<int|string, string>
     */
    public static function get(string $codeName): array
    {
        if (array_key_exists($codeName, self::$cache)) {
            return self::$cache[$codeName];
        }

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

        if ($isDb) {
            $options = [];
            $rows = Code::where('type', $codeName)->orderBy('sort_order')->orderBy('id')->get(['code', 'name']);
            foreach ($rows as $row) {
                $options[self::normalizeKey($row->code)] = self::normalizeLabel($row->name);
            }

            return self::$cache[$codeName] = $options;
        }

        if ($isEnum) {
            // 列挙型の全部の値から [値 => 名前] を作る
            $options = [];
            foreach ($enumClass::cases() as $case) {
                $options[$case->value] = $case->label();
            }

            return self::$cache[$codeName] = $options;
        }

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

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                // Excelなどで保存したCSVの先頭に付くBOM（見えない印）を除去する
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            $line = trim($line, "\r\n ");

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode(',', $line, 2);

            if (count($parts) < 2) {
                fclose($handle);

                $message = "コード表{$codeName}の{$lineNumber}行目の形式が不正です: {$line}";
                throw new CodeTableException($message);
            }

            [$rawKey, $label] = $parts;

            $options[self::normalizeKey($rawKey)] = self::normalizeLabel($label);
        }

        fclose($handle);

        return self::$cache[$codeName] = $options;
    }

    /**
     * 値の形を揃える（CSV・DB共通）。前後の空白を除き、数字だけならint型にする
     * （前ゼロは除かれる。'01'と'1'は同じ値1になる）。
     */
    public static function normalizeKey(string $rawKey): int|string
    {
        $rawKey = trim($rawKey);

        return ctype_digit($rawKey) ? (int) $rawKey : $rawKey;
    }

    /** 名称の形を揃える（CSV・DB共通）。前後の空白を除き、「\n」の2文字を改行にする。 */
    private static function normalizeLabel(string $label): string
    {
        return str_replace('\\n', "\n", trim($label));
    }
}
