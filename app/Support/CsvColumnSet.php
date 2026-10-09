<?php

namespace App\Support;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * コントローラーのcsvColumns()に書いたCSVの項目の定義を読み、書き出しのCsvDownloadと
 * 取り込みのCsvImportの両方で使う形にする。見出しと値の場所の対応を1か所で読むので、
 * ダウンロードしたCSVをそのまま取り込める。
 *
 * ■ csvColumns()の書き方
 *
 *     '会員ID'       => 'id',                              // カラム
 *     '最終更新者'   => 'editorStaff.name',                // リレーションはドットでたどる
 *     '生年月日'     => 'birthdate|date:Y/m/d',            // 「|」の後ろで書式を変換
 *     '記事ID'       => 'id|format:%06d',
 *     '都道府県'     => ['prefecture', $prefectures],      // [値の場所, 一覧]：値を一覧の表示名に置き換える
 *     'カテゴリー'   => ['categories.*.id', $categories, 'import' => 'category_ids'],
 *                                                          // 「*」を含むと複数。「、」区切りで1セルに
 *     'カテゴリー:*' => ['categories.*.id', $categories, 'import' => 'category_ids'],
 *                                                          // 見出しの末尾「:*」で一覧の件数分の列に横展開
 *     '添付ファイル:*' => 'attach.*.original_name',        // 一覧なしの「:*」は連番の横展開。:1、:2…
 *     '添付:*'       => ['attach', 'group' => [            // 組にした横展開
 *                            '添付ファイル'   => 'original_name',
 *                            '保存ファイル名' => 'filename',
 *                        ]],
 *     '年齢'         => '@age',                            // csvCustomColumn('age', $record)の戻り値
 *     '氏名'         => ['@fullname', 'import' => ['last_name', 'first_name']],
 *                                                          // 取り込むときはcsvCustomImport()で逆向きに変換
 *
 * - 一覧は「値 => 表示名」の配列。コード表ならcode_table('prefectures')、DBなら
 *   Category::orderBy('display_order')->pluck('name', 'id')->all() のように作って渡す
 * - 一覧ありの横展開の見出しは「見出し:表示名」。そのデータが持っている値の列に1を入れ、
 *   ほかは空欄にする
 * - 一覧なしの「:*」の連番の横展開と、組にした横展開の見出しは「見出し:1」「見出し:2」…。
 *   列の数は、書き出す前に同じ条件で数えた対象のデータの中の最大の件数にそろえる。
 *   組の外側の見出し'添付:*'は列にならず、エラーの案内などで組の名前として使う
 * - 「|名前:引数」の書式の変換は、filters()にあるものが使える。最初の「:」までが名前で
 *   後ろが引数。date:Y/m/d H:iの時刻の「:」はそのまま書ける。引数に「|」は書けない。
 *   値がnullか空文字なら変換せずに空欄にする
 * - 知らない変換の名前や形の正しくない定義などの書き間違いは、このクラスを作ったときに例外にする
 *
 * ■ 取り込み先の項目
 * 取り込むときは、列ごとに次の順で取り込み先のフォームの項目名を決める。取り込み先が
 * 決まらない列と、決まってもrules()に無い項目の列は取り込まずに無視する。
 * 1. 'import' => '項目名'と書いてあれば、その項目
 * 2. 値の場所がドットを含まないカラム名なら、そのカラム名
 * 3. 連番か組の横展開で、関連がUPLOAD_FILESの'attach.*'のような複数のアップロードの欄なら、
 *    filenameはattach、original_nameはattach_origin
 */
final class CsvColumnSet
{
    /**
     * 読んだ定義を、書いた順に並べたもの。1件は次のキーを持つ。
     * - heading   定義の見出し。例：'カテゴリー:*'
     * - label     末尾の「:*」を除いた見出し
     * - type      value、list、一覧ありの横展開のexpand、連番の横展開のnumbered、組のgroup、@名前のcustom
     * - path      値の場所。groupでは関連の名前
     * - multiple  値の場所に「*」を含むか
     * - filters   書式の変換。[[名前, 引数], …]
     * - options   listとexpandの一覧
     * - target    value・list・expand・numberedの取り込み先の項目名。取り込めなければnull
     * - upload    numberedの、複数のアップロードの欄への取り込み先。[項目名, 'filename'か'origin']
     * - targets   customの取り込み先の項目名の一覧
     * - key       customの、@の後ろの名前
     * - relation  numberedとgroupの関連の名前
     * - column    numberedの、関連の中の値の場所
     * - group     groupの中身。[見出し => ['column', 'filters', 'upload']]
     */
    private array $specs = [];

    /** 連番と組の横展開の列の数。関連の名前 => 最大の件数で、prepareExport()で数える */
    private array $counts = [];

    /**
     * $uploadFields  AjaxFileUploadの欄。項目名 => 'single'か'repeatable'。使っていなければ空
     * $customExport  @名前の列の書き出し。fn (string $key, Model $record): mixed
     * $customImport  @名前の列の取り込み。fn (string $key, ?string $value, CsvImportRow $row): array
     */
    public function __construct(
        array $definitions,
        private array $uploadFields = [],
        private ?Closure $customExport = null,
        private ?Closure $customImport = null,
    ) {
        foreach ($definitions as $heading => $definition) {
            $this->specs[] = $this->parseDefinition((string) $heading, $definition);
        }
    }

    // ---- 書式の変換 ----

    /**
     * csvColumns()の「|名前:引数」に書ける書式の変換。
     * 名前 => ['export' => 書き出すときの変換, 'import' => 取り込むときに元に戻す変換]。
     * 変換を増やすときは両方の向きを足す。取り込むときに値の形が正しくなければ、
     * CsvValueExceptionを投げてその行のエラーにする。
     */
    private function filters(): array
    {
        return [
            // 日付を書式で出す。例：date:Y/m/d H:i。引数を省くとY/m/d
            'date' => [
                'export' => fn ($value, ?string $arg) => ($value instanceof DateTimeInterface ? $value : Carbon::parse($value))
                    ->format($arg ?? 'Y/m/d'),
                'import' => fn (string $value, ?string $arg) => $this->importDate($value, $arg),
            ],
            // sprintf()の書式で出す。例：format:%06d
            'format' => [
                'export' => fn ($value, ?string $arg) => sprintf($arg ?? '%s', $value),
                // 数字だけの値は、先頭の0を外す。例：000012は12。ほかはそのまま
                'import' => fn (string $value, ?string $arg) => preg_match('/^-?\d+$/', $value)
                    ? preg_replace('/^(-?)0+(?=\d)/', '$1', $value)
                    : $value,
            ],
            // 3桁ごとに区切る。例：number、小数2桁ならnumber:2
            'number' => [
                'export' => fn ($value, ?string $arg) => number_format((float) $value, (int) ($arg ?? 0)),
                'import' => fn (string $value, ?string $arg) => $this->importNumber($value),
            ],
        ];
    }

    /**
     * 日付を取り込む。受け付けるのは「4桁の年-月-日」と「4桁の年/月/日」だけ。Excelが
     * 「2026/9/29」に書き換えるので月と日は1桁でもよい。書き出しの書式に時刻があれば日時の項目で、
     * 後ろに「時:分」か「時:分:秒」が付いた形も受け付ける。
     * 日付の項目は「Y-m-d」、日時の項目は「Y-m-d H:i:s」にして返す。
     */
    private function importDate(string $value, ?string $format): string
    {
        $withTime = $format !== null && preg_match('/[GgHhis]/', $format) === 1;

        // 形を確かめる
        if (! preg_match('#^(\d{4})([-/])(\d{1,2})\2(\d{1,2})(?: (\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $value, $m)) {
            if ($withTime) {
                throw new CsvValueException('日時は「2026/9/29 13:05」のような形で入力してください。');
            }

            throw new CsvValueException('日付は「2026/9/29」のような形で入力してください。');
        }

        [$year, $month, $day] = [(int) $m[1], (int) $m[3], (int) $m[4]];

        if (! checkdate($month, $day, $year)) {
            throw new CsvValueException('存在しない日付です。');
        }

        $hasTime = isset($m[5]) && $m[5] !== '';

        // 日付の項目
        if (! $withTime) {
            if ($hasTime) {
                throw new CsvValueException('日付だけを入力してください（時刻は入力できません）。');
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // 日時の項目。時刻が無ければ0時0分0秒
        [$hour, $minute, $second] = [(int) ($m[5] ?? 0), (int) ($m[6] ?? 0), (int) ($m[7] ?? 0)];

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new CsvValueException('存在しない時刻です。');
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    /**
     * 3桁ごとに区切った数値を取り込む。先頭が1〜3桁で後ろが「カンマと3桁」の繰り返しの形だけ
     * カンマを外す。小数とマイナスも付けられる。ほかの形でカンマを含むものはエラーにする。
     * カンマを含まない値はそのまま返し、数値かどうかはrules()で確かめる。
     */
    private function importNumber(string $value): string
    {
        if (! str_contains($value, ',')) {
            return $value;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            return str_replace(',', '', $value);
        }

        throw new CsvValueException('数値の形が正しくありません（3桁ごとのカンマの位置を確かめてください）。');
    }

    // ---- 定義の解釈 ----

    /** csvColumns()の1項目を読み、$specsの1件にする。書き方が正しくなければ例外にする。 */
    private function parseDefinition(string $heading, mixed $definition): array
    {
        $expand = str_ends_with($heading, ':*');
        $spec = [
            'heading' => $heading,
            'label' => $expand ? substr($heading, 0, -2) : $heading,
            'multiple' => false,
            'filters' => [],
            'target' => null,
            'upload' => null,
            'targets' => [],
        ];

        // 文字列なら値の場所だけ、配列なら値の場所・一覧・取り込み先・組に分ける
        if (is_string($definition)) {
            [$path, $list, $import, $group] = [$definition, null, null, null];
        } elseif (is_array($definition)) {
            $unknown = array_diff(array_keys($definition), [0, 1, 'import', 'group']);

            if ($unknown !== []) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」に、知らない指定（".implode('・', $unknown).'）があります。');
            }

            [$path, $list, $import, $group] = [
                $definition[0] ?? null, $definition[1] ?? null, $definition['import'] ?? null, $definition['group'] ?? null,
            ];
        } else {
            throw new InvalidArgumentException("CSVの項目「{$heading}」の書き方が正しくありません。");
        }

        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException("CSVの項目「{$heading}」の書き方が正しくありません。");
        }

        if ($list !== null && ! is_array($list)) {
            throw new InvalidArgumentException("CSVの項目「{$heading}」は、[値の場所, 一覧] の形で書いてください。");
        }

        $imports = $import === null ? [] : (array) $import;

        foreach ($imports as $field) {
            if (! is_string($field) || $field === '') {
                throw new InvalidArgumentException("CSVの項目「{$heading}」の'import'には、項目名を書いてください。");
            }
        }

        // 組にした横展開
        if ($group !== null) {
            if (! $expand || $list !== null || $imports !== [] || ! is_array($group) || $group === []
                || ! preg_match('/^\w+$/', $path)) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」（組）は、'見出し:*' => ['関連の名前', 'group' => [見出し => 値の場所, …]] の形で書いてください。");
            }

            $columns = [];
            foreach ($group as $subHeading => $columnDefinition) {
                if (! is_string($columnDefinition) || $columnDefinition === '' || str_contains($columnDefinition, '*')) {
                    throw new InvalidArgumentException("CSVの項目「{$heading}」の「{$subHeading}」の書き方が正しくありません。");
                }

                [$column, $filters] = $this->parsePath($columnDefinition, $heading);
                $columns[(string) $subHeading] = [
                    'column' => $column,
                    'filters' => $filters,
                    'upload' => $this->uploadTarget($path, $column),
                ];
            }

            return $spec + ['type' => 'group', 'path' => $path, 'relation' => $path, 'group' => $columns];
        }

        // @名前
        if (str_starts_with($path, '@')) {
            if ($expand || $list !== null) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」（{$path}）は、横展開や一覧を指定できません。");
            }

            return ['type' => 'custom', 'path' => $path, 'key' => substr($path, 1), 'targets' => $imports] + $spec;
        }

        if (count($imports) > 1) {
            throw new InvalidArgumentException("CSVの項目「{$heading}」の'import'に書ける項目は1つです（複数に取り込むのは@名前の列だけ）。");
        }

        // 取り込み先は、'import'に書いた項目か、ドットを含まないカラム名
        [$path, $filters] = $this->parsePath($path, $heading);
        $target = $imports[0] ?? (preg_match('/^\w+$/', $path) ? $path : null);

        // [値の場所, 一覧]は、値を一覧の表示名に置き換える。横展開なら一覧の件数分の列にする
        if ($list !== null) {
            if ($filters !== []) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」は、一覧と書式の変換を一緒に指定できません。");
            }

            return [
                'type' => $expand ? 'expand' : 'list',
                'path' => $path,
                'multiple' => str_contains($path, '*'),
                'options' => $list,
                'target' => $target,
            ] + $spec;
        }

        // 一覧なしの「:*」は、連番の横展開
        if ($expand) {
            if (! preg_match('/^(\w+)\.\*\.([\w.]+)$/', $path, $m)) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」を連番で横展開するには、値の場所を「関連.*.項目」の形で書いてください。");
            }

            return [
                'type' => 'numbered',
                'path' => $path,
                'relation' => $m[1],
                'column' => $m[2],
                'filters' => $filters,
                'target' => $imports[0] ?? null,
                'upload' => $imports === [] ? $this->uploadTarget($m[1], $m[2]) : null,
            ] + $spec;
        }

        // それ以外は、値をそのまま出す
        return [
            'type' => 'value',
            'path' => $path,
            'multiple' => str_contains($path, '*'),
            'filters' => $filters,
            'target' => $target,
        ] + $spec;
    }

    /** 「値の場所|変換:引数|…」を、値の場所と変換の一覧に分ける。 */
    private function parsePath(string $definition, string $heading): array
    {
        $parts = explode('|', $definition);
        $path = array_shift($parts);
        $filters = [];

        foreach ($parts as $part) {
            [$name, $arg] = array_pad(explode(':', $part, 2), 2, null);

            if (! isset($this->filters()[$name])) {
                throw new InvalidArgumentException("CSVの項目「{$heading}」の変換「{$name}」はありません。");
            }

            $filters[] = [$name, $arg];
        }

        return [$path, $filters];
    }

    /** 関連がAjaxFileUploadの複数の項目なら、取り込み先 [項目名, 'filename'|'origin'] を返す。 */
    private function uploadTarget(string $relation, string $column): ?array
    {
        if (($this->uploadFields[$relation] ?? null) !== 'repeatable') {
            return null;
        }

        return match ($column) {
            'filename' => [$relation, 'filename'],
            'original_name' => [$relation, 'origin'],
            default => null,
        };
    }

    /** 見出しが「:*」で終わる横展開の列があれば、例外にする。見出し無しのCSVでは使えないため。 */
    public function assertNoExpansion(): void
    {
        foreach ($this->specs as $spec) {
            if (str_ends_with($spec['heading'], ':*')) {
                throw new InvalidArgumentException("見出し無しのCSVでは、横展開の列（「{$spec['heading']}」）は使えません。");
            }
        }
    }

    // ---- 書き出し ----

    /**
     * 書き出しの準備。連番・組の横展開の列の数を、同じ条件のデータの最大件数で決める。
     * $queryには、一覧の検索条件をかけ終わったものを渡す。
     */
    public function prepareExport(Builder $query, bool $header): void
    {
        if (! $header) {
            $this->assertNoExpansion();
        }

        foreach ($this->specs as $spec) {
            if ($spec['type'] === 'custom' && $this->customExport === null) {
                throw new InvalidArgumentException("CSVの項目「{$spec['heading']}」（{$spec['path']}）を出すには、csvCustomColumn()を用意してください。");
            }

            // 関連の件数をレコードごとに数え、その最大を列の数にする
            if (in_array($spec['type'], ['numbered', 'group'], true) && ! isset($this->counts[$spec['relation']])) {
                $relation = $spec['relation'];
                $counted = (clone $query)->reorder()->withCount($relation);
                $this->counts[$relation] = (int) DB::query()->fromSub($counted, 'csv_counts')->max("{$relation}_count");
            }
        }
    }

    /** 書き出すCSVの見出しの行。横展開は、一覧の件数か連番の数だけ列を並べる。 */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->specs as $spec) {
            switch ($spec['type']) {
                case 'expand':
                    foreach ($spec['options'] as $label) {
                        $headings[] = "{$spec['label']}:{$label}";
                    }
                    break;
                case 'numbered':
                    for ($i = 1; $i <= $this->counts[$spec['relation']]; $i++) {
                        $headings[] = "{$spec['label']}:{$i}";
                    }
                    break;
                case 'group':
                    for ($i = 1; $i <= $this->counts[$spec['relation']]; $i++) {
                        foreach (array_keys($spec['group']) as $subHeading) {
                            $headings[] = "{$subHeading}:{$i}";
                        }
                    }
                    break;
                default:
                    $headings[] = $spec['heading'];
            }
        }

        return $headings;
    }

    /** 1件分のセルの並び。 */
    public function exportRow(Model $record): array
    {
        $cells = [];

        foreach ($this->specs as $spec) {
            switch ($spec['type']) {
                // 値を変換して出す。複数なら「、」でつなぐ
                case 'value':
                    $cells[] = implode('、', array_map(
                        fn ($value) => $this->exportValue($value, $spec['filters']),
                        $this->values($record, $spec['path']),
                    ));
                    break;
                    // 値を一覧の表示名にする
                case 'list':
                    $cells[] = implode('、', array_map(
                        fn ($value) => $spec['options'][$value] ?? '',
                        $this->values($record, $spec['path']),
                    ));
                    break;
                    // 持っている値の列に1、ほかは空欄
                case 'expand':
                    $values = array_map('strval', $this->values($record, $spec['path']));
                    foreach (array_keys($spec['options']) as $key) {
                        $cells[] = in_array((string) $key, $values, true) ? '1' : '';
                    }
                    break;
                    // 前から順に入れ、足りない列は空欄
                case 'numbered':
                    $values = $this->values($record, $spec['path']);
                    for ($i = 0; $i < $this->counts[$spec['relation']]; $i++) {
                        $cells[] = $this->exportValue($values[$i] ?? null, $spec['filters']);
                    }
                    break;
                    // 関連の1件ごとに、組の列を並べる
                case 'group':
                    $items = array_values(iterator_to_array(data_get($record, $spec['relation']) ?? []));
                    for ($i = 0; $i < $this->counts[$spec['relation']]; $i++) {
                        foreach ($spec['group'] as $column) {
                            $value = isset($items[$i]) ? data_get($items[$i], $column['column']) : null;
                            $cells[] = $this->exportValue(is_bool($value) ? (int) $value : $value, $column['filters']);
                        }
                    }
                    break;
                    // コントローラーのcsvCustomColumn()の戻り値
                case 'custom':
                    $cells[] = ($this->customExport)($spec['key'], $record);
                    break;
            }
        }

        return $cells;
    }

    /** 値に書式の変換をかける。空の値は変換せず空欄にする。 */
    private function exportValue(mixed $value, array $filters): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        foreach ($filters as [$name, $arg]) {
            $value = $this->filters()[$name]['export']($value, $arg);
        }

        return $value;
    }

    /**
     * 値の場所から値を取り出し、いつも配列で返す。「*」を含む場所は複数、含まなければ1つ。
     * 一覧のキーと突き合わせられるよう、trueとfalseは1と0にする。
     */
    private function values(Model $record, string $path): array
    {
        $value = data_get($record, $path);
        $values = str_contains($path, '*') ? array_values((array) $value) : [$value];

        return array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $values);
    }

    // ---- 取り込み ----

    /** 定義に書いた見出しの一覧。見出し無しのCSVを読むときと、確認画面の見出しに使う。 */
    public function definedHeadings(): array
    {
        return array_column($this->specs, 'heading');
    }

    /** idなどのキーの列の見出し。定義に無ければnull。 */
    public function keyHeading(string $keyName): ?string
    {
        $index = $this->keySpecIndex($keyName);

        return $index === null ? null : $this->specs[$index]['heading'];
    }

    /** キーの列の定義の番号。値の場所がキーのカラムそのものの定義で、無ければnull。 */
    private function keySpecIndex(string $keyName): ?int
    {
        foreach ($this->specs as $index => $spec) {
            if ($spec['type'] === 'value' && $spec['path'] === $keyName) {
                return $index;
            }
        }

        return null;
    }

    /**
     * CSVの見出しを定義と突き合わせる。
     *
     * $importable  取り込める項目名。rules()のキーのうち、ドットを含まないもの
     * $refPaths    取り込まないが、確かめるために読む列の値の場所。更新日時など
     * 戻り値       ['map' => [列の番号 => [定義の番号, 横展開の部分]], 'key' => キーの列の番号かnull,
     *              'refs' => [値の場所 => 列の番号], 'errors' => [...], 'warnings' => [...]]
     */
    public function mapHeadings(array $headings, array $importable, string $keyName, array $refPaths = []): array
    {
        $map = [];
        $key = null;
        $refs = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $keyIndex = $this->keySpecIndex($keyName);

        foreach ($headings as $column => $heading) {
            if ($heading === '') {
                // Excelで末尾に付くことがある、見出しの無い列は黙って読み飛ばす
                continue;
            }

            // 同じ見出しが2つあればエラー
            if (isset($seen[$heading])) {
                $errors[] = "見出し「{$heading}」の列が2つあります。";

                continue;
            }
            $seen[$heading] = true;

            // 定義に無い列は無視する
            $found = $this->findSpec($heading);

            if ($found === null) {
                $warnings[] = "「{$heading}」は項目の定義に無い列なので、無視しました。";

                continue;
            }

            [$specIndex, $part] = $found;

            // キーの列と確かめるために読む列は、取り込む列とは別に覚える
            if ($specIndex === $keyIndex) {
                $key = $column;

                continue;
            }

            if ($this->specs[$specIndex]['type'] === 'value' && in_array($this->specs[$specIndex]['path'], $refPaths, true)) {
                $refs[$this->specs[$specIndex]['path']] = $column;

                continue;
            }

            // 取り込み先が無いか、rules()に無い項目の列は無視する
            if (! $this->isImportable($this->specs[$specIndex], $part, $importable)) {
                $warnings[] = "「{$heading}」は取り込めない列なので、無視しました。";

                continue;
            }

            $map[$column] = [$specIndex, $part];
        }

        // 複数のアップロードの欄で、保存ファイル名の列が無く表示名の列だけあるときは、
        // どのファイルの表示名か決められないので無視する
        $slots = [];
        foreach ($map as [$specIndex, $part]) {
            if ($upload = $this->uploadOf($this->specs[$specIndex], $part)) {
                $slots[$upload[0]][$upload[1]] = true;
            }
        }
        foreach ($map as $column => [$specIndex, $part]) {
            $upload = $this->uploadOf($this->specs[$specIndex], $part);
            if ($upload && ! isset($slots[$upload[0]]['filename'])) {
                $warnings[] = "「{$headings[$column]}」は、保存ファイル名の列が無いので無視しました。";
                unset($map[$column]);
            }
        }

        // 一覧ありの横展開で一部の列が無ければ、その選択は今のままにすると知らせる
        $present = [];
        foreach ($map as [$specIndex, $part]) {
            if ($this->specs[$specIndex]['type'] === 'expand') {
                $present[$specIndex][(string) $part] = true;
            }
        }
        foreach ($present as $specIndex => $keys) {
            $spec = $this->specs[$specIndex];
            $missing = array_filter($spec['options'], fn ($k) => ! isset($keys[(string) $k]), ARRAY_FILTER_USE_KEY);

            if ($missing !== []) {
                $names = '「'.implode('」「', array_slice($missing, 0, 5)).'」'.(count($missing) > 5 ? 'など'.count($missing).'個' : '');
                $warnings[] = "「{$spec['label']}」の横展開に{$names}の列が無いので、その選択は今のままにします。";
            }
        }

        return ['map' => $map, 'key' => $key, 'refs' => $refs, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * 見出し無しのCSVの列と定義の対応。定義に書いた順に全部の列が並んでいる前提。
     * 取り込めない列は、その位置を読み飛ばす。戻り値の形はmapHeadings()と同じ。
     */
    public function mapByOrder(array $importable, string $keyName, array $refPaths = []): array
    {
        $this->assertNoExpansion();

        $map = [];
        $refs = [];
        $keyIndex = $this->keySpecIndex($keyName);

        foreach ($this->specs as $index => $spec) {
            if ($index === $keyIndex) {
                continue;
            }

            if ($spec['type'] === 'value' && in_array($spec['path'], $refPaths, true)) {
                $refs[$spec['path']] = $index;
            } elseif ($this->isImportable($spec, null, $importable)) {
                $map[$index] = [$index, null];
            }
        }

        return ['map' => $map, 'key' => $keyIndex, 'refs' => $refs, 'errors' => [], 'warnings' => []];
    }

    /**
     * 日時のセルを読む。受け付ける形は、日付の取り込みと同じ。
     * [「Y-m-d H:i:s」の文字列, 'second'・'minute'・'day'のどこまで書いてあるか]。読めなければnull。
     */
    public function readDateTime(string $value): ?array
    {
        try {
            $dateTime = $this->importDate($value, 'Y/m/d H:i:s');
        } catch (CsvValueException) {
            return null;
        }

        $precision = match (true) {
            preg_match('/ \d{1,2}:\d{2}:\d{2}$/', $value) === 1 => 'second',
            preg_match('/ \d{1,2}:\d{2}$/', $value) === 1 => 'minute',
            default => 'day',
        };

        return [$dateTime, $precision];
    }

    /** レコードの値を、その値の場所の列と同じ形で書き出した文字列。更新日時を比べるのに使う。 */
    public function exportCell(Model $record, string $path): string
    {
        foreach ($this->specs as $spec) {
            if ($spec['type'] === 'value' && $spec['path'] === $path) {
                return (string) $this->exportValue(data_get($record, $path), $spec['filters']);
            }
        }

        return '';
    }

    /**
     * 見出しに当たる定義を探す。[定義の番号, 横展開の部分]で、見つからなければnull。
     * 横展開の部分は、一覧ありなら一覧の値、連番なら番号、組なら[番号, 組の中の見出し]。
     */
    private function findSpec(string $heading): ?array
    {
        // 見出しがそのまま一致する定義を先に探す
        foreach ($this->specs as $index => $spec) {
            if (in_array($spec['type'], ['value', 'list', 'custom'], true) && $spec['heading'] === $heading) {
                return [$index, null];
            }
        }

        // 無ければ、横展開の「見出し:表示名」「見出し:番号」を探す
        foreach ($this->specs as $index => $spec) {
            if ($spec['type'] === 'expand' && str_starts_with($heading, $spec['label'].':')) {
                $key = array_search(substr($heading, strlen($spec['label']) + 1), array_map('strval', $spec['options']), true);

                if ($key !== false) {
                    return [$index, $key];
                }
            }

            if ($spec['type'] === 'numbered' && preg_match('/^'.preg_quote($spec['label'], '/').':([1-9]\d*)$/u', $heading, $m)) {
                return [$index, (int) $m[1]];
            }

            if ($spec['type'] === 'group') {
                foreach (array_keys($spec['group']) as $subHeading) {
                    if (preg_match('/^'.preg_quote($subHeading, '/').':([1-9]\d*)$/u', $heading, $m)) {
                        return [$index, [(int) $m[1], $subHeading]];
                    }
                }
            }
        }

        return null;
    }

    /** 列を取り込めるか。取り込み先の項目が、全部rules()にあるときだけ取り込める。 */
    private function isImportable(array $spec, mixed $part, array $importable): bool
    {
        // アップロードの欄は、ファイル名と表示名の両方
        if ($upload = $this->uploadOf($spec, $part)) {
            return in_array($upload[0], $importable, true) && in_array("{$upload[0]}_origin", $importable, true);
        }

        // @名前の列は、'import'に書いた全部の項目とcsvCustomImport()がそろっていること
        if ($spec['type'] === 'custom') {
            return $spec['targets'] !== [] && $this->customImport !== null
                && array_diff($spec['targets'], $importable) === [];
        }

        return $spec['target'] !== null && in_array($spec['target'], $importable, true);
    }

    /** 複数のアップロードの欄への取り込み先。[項目名, 'filename'か'origin']で、そうでなければnull。 */
    private function uploadOf(array $spec, mixed $part): ?array
    {
        return match ($spec['type']) {
            'numbered' => $spec['upload'],
            'group' => $spec['group'][$part[1]]['upload'] ?? null,
            default => null,
        };
    }

    /** キーの列のセルを、定義の変換を戻した値にする。例：'000012'は'12'。 */
    public function readKey(?string $cell, string $keyName): ?string
    {
        $index = $this->keySpecIndex($keyName);

        return ($cell === null || $index === null) ? $cell : $this->importValue($cell, $this->specs[$index]['filters']);
    }

    /**
     * 1行分のセルから、取り込む値を項目名 => 値で読み取る。読み取れないセルは$rowのエラーにする。
     *
     * $cells    列の番号 => セルの値。前後の空白を除き、空欄はnull
     * $map      mapHeadings()かmapByOrder()の'map'
     * $current  項目名 => 今の値。一覧ありの横展開で一部の列が無いときと、
     *           アップロードの欄の表示名の列が無いときに使う
     */
    public function readRow(array $cells, array $map, array $current, CsvImportRow $row): array
    {
        $candidates = [];   // 項目名 => [[見出し, 値], …]
        $expands = [];      // 定義の番号 => ['present' => [...], 'selected' => [...]]
        $numbered = [];     // 定義の番号 => [番号 => 値]
        $uploads = [];      // 項目名 => [番号 => ['filename' => [見出し, 値], 'origin' => [見出し, 値]]]
        $customs = [];      // 定義の番号 => 値

        // セルを1つずつ読み、種類ごとに集める。横展開と@名前の列は、全部の列を見た後でまとめる
        foreach ($map as $column => [$specIndex, $part]) {
            $spec = $this->specs[$specIndex];
            $cell = $cells[$column] ?? null;
            $heading = $this->cellHeading($spec, $part);

            try {
                switch ($spec['type']) {
                    case 'value':
                        $candidates[$spec['target']][] = [$heading, $this->readValue($cell, $spec)];
                        break;
                    case 'list':
                        $candidates[$spec['target']][] = [$heading, $this->readList($cell, $spec)];
                        break;
                    case 'expand':
                        $expands[$specIndex]['present'][(string) $part] = true;
                        if ($cell === '1') {
                            $expands[$specIndex]['selected'][(string) $part] = true;
                        } elseif ($cell !== null) {
                            throw new CsvValueException('1か空欄で入力してください。');
                        }
                        break;
                    case 'numbered':
                        $value = $cell === null ? null : $this->importValue($cell, $spec['filters']);
                        if ($spec['upload']) {
                            $this->putUploadSlot($uploads, $spec['upload'], $part, $heading, $value, $row);
                        } else {
                            $numbered[$specIndex][$part] = $value;
                        }
                        break;
                    case 'group':
                        [$number, $subHeading] = $part;
                        $member = $spec['group'][$subHeading];
                        $value = $cell === null ? null : $this->importValue($cell, $member['filters']);
                        $this->putUploadSlot($uploads, $member['upload'], $number, $heading, $value, $row);
                        break;
                    case 'custom':
                        $customs[$specIndex] = $cell;
                        break;
                }
            } catch (CsvValueException $e) {
                $row->addError($e->getMessage(), $heading);
            }
        }

        // 一覧ありの横展開は1の列の値を集める。列が無い値は今の選択のままにする
        foreach ($expands as $specIndex => $state) {
            $spec = $this->specs[$specIndex];
            // 配列のキーにした値は数字だけなら整数に変わっているので、文字列に戻して比べる
            $selected = array_map('strval', array_keys($state['selected'] ?? []));
            $missing = array_filter(array_keys($spec['options']), fn ($k) => ! isset($state['present'][(string) $k]));
            $missing = array_map('strval', $missing);

            if ($spec['multiple']) {
                // 複数選べる項目は、1の列と、列が無くて今選ばれている値
                $kept = array_intersect(array_map('strval', (array) ($current[$spec['target']] ?? [])), $missing);
                $value = array_values(array_filter(
                    array_map('strval', array_keys($spec['options'])),
                    fn ($k) => in_array($k, $selected, true) || in_array($k, $kept, true),
                ));
            } elseif (count($selected) > 1) {
                // 1つだけ選ぶ項目で、1が2つ以上
                $row->addError('1にできるのは1つの列だけです。', $spec['label']);

                continue;
            } elseif ($selected !== []) {
                // 1つだけ選ぶ項目で、1の列がある
                $value = (string) $selected[0];
            } else {
                // 1の列が無ければ、今の値の列が無いときだけ今の値のまま
                $now = $current[$spec['target']] ?? null;
                $value = ($now !== null && in_array((string) $now, $missing, true)) ? (string) $now : null;
            }

            $candidates[$spec['target']][] = [$spec['heading'], $value];
        }

        // 連番の横展開は番号の順に並べて空欄を詰める
        foreach ($numbered as $specIndex => $values) {
            ksort($values);
            $spec = $this->specs[$specIndex];
            $candidates[$spec['target']][] = [$spec['heading'], array_values(array_filter($values, fn ($v) => $v !== null))];
        }

        // @名前の列は、コントローラーのcsvCustomImport()で変換する
        foreach ($customs as $specIndex => $cell) {
            $spec = $this->specs[$specIndex];
            $values = ($this->customImport)($spec['key'], $cell, $row);
            $unknown = array_diff(array_keys($values), $spec['targets']);

            if ($unknown !== []) {
                throw new LogicException("csvCustomImport('{$spec['key']}')が、'import'に書いていない項目（".implode('・', $unknown).'）を返しました。');
            }

            foreach ($values as $field => $value) {
                $candidates[$field][] = [$spec['heading'], $value];
            }
        }

        // 同じ項目に対する列が複数あれば、内容が同じかを確かめる
        $values = [];
        foreach ($candidates as $field => $list) {
            [$firstHeading, $first] = $list[0];

            foreach (array_slice($list, 1) as [$heading, $value]) {
                if (self::normalize($value) !== self::normalize($first)) {
                    $row->addError("「{$firstHeading}」と「{$heading}」の内容が食い違っています。", $heading);
                }
            }

            $values[$field] = $first;
        }

        // 複数のアップロードの欄は番号の順に組を並べ、両方空欄の組を詰める
        foreach ($uploads as $field => $slots) {
            ksort($slots);

            // 表示名の列が無ければ、今のファイルの表示名を使う
            $currentOrigins = array_combine(
                array_map('strval', (array) ($current[$field] ?? [])),
                array_pad((array) ($current["{$field}_origin"] ?? []), count((array) ($current[$field] ?? [])), null),
            );
            $hasOrigin = false;
            foreach ($map as [$specIndex, $part]) {
                if ($this->uploadOf($this->specs[$specIndex], $part) === [$field, 'origin']) {
                    $hasOrigin = true;
                }
            }

            $filenames = [];
            $origins = [];
            foreach ($slots as $number => $slot) {
                [$filenameHeading, $filename] = $slot['filename'] ?? [null, null];
                [$originHeading, $origin] = $slot['origin'] ?? [null, null];

                if ($filename === null && $origin === null) {
                    continue;
                }

                if ($filename === null) {
                    $row->addError('保存ファイル名が空欄です。', $originHeading);

                    continue;
                }

                if (in_array($filename, $filenames, true)) {
                    $row->addError("同じ保存ファイル名（{$filename}）が2回あります。", $filenameHeading);

                    continue;
                }

                $filenames[] = $filename;
                $origins[] = $hasOrigin ? $origin : ($currentOrigins[$filename] ?? null);
            }

            $values[$field] = $filenames;
            $values["{$field}_origin"] = $origins;
        }

        return $values;
    }

    /** アップロードの欄の値を、番号の組に入れる。同じ組の同じ値に違う内容が来たらエラー。 */
    private function putUploadSlot(array &$uploads, array $upload, int $number, string $heading, ?string $value, CsvImportRow $row): void
    {
        [$field, $slot] = $upload;

        if (isset($uploads[$field][$number][$slot]) && $uploads[$field][$number][$slot][1] !== $value) {
            $row->addError("「{$uploads[$field][$number][$slot][0]}」と「{$heading}」の内容が食い違っています。", $heading);

            return;
        }

        $uploads[$field][$number][$slot] = [$heading, $value];
    }

    /** その列のCSVの見出し。横展開なら「見出し:表示名」や「見出し:1」の形。 */
    private function cellHeading(array $spec, mixed $part): string
    {
        return match ($spec['type']) {
            'expand' => "{$spec['label']}:{$spec['options'][$part]}",
            'numbered' => "{$spec['label']}:{$part}",
            'group' => "{$part[1]}:{$part[0]}",
            default => $spec['heading'],
        };
    }

    /** 値の列を読んで書式の変換を戻す。複数なら「、」で分ける。 */
    private function readValue(?string $cell, array $spec): string|array|null
    {
        if ($spec['multiple']) {
            return array_map(fn ($piece) => $this->importValue($piece, $spec['filters']), $this->split($cell));
        }

        return $cell === null ? null : $this->importValue($cell, $spec['filters']);
    }

    /** 一覧の表示名を、一覧の値に戻す。一覧に無い表示名はエラー。 */
    private function readList(?string $cell, array $spec): string|array|null
    {
        $toKey = function (string $label) use ($spec) {
            $key = array_search($label, array_map('strval', $spec['options']), true);

            if ($key === false) {
                throw new CsvValueException("「{$label}」は選択肢にありません。");
            }

            return (string) $key;
        };

        if ($spec['multiple']) {
            return array_map($toKey, $this->split($cell));
        }

        return $cell === null ? null : $toKey($cell);
    }

    /** 「、」で区切った1セルを値の一覧にする。空の要素は除く。 */
    private function split(?string $cell): array
    {
        if ($cell === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('、', $cell)), fn ($v) => $v !== ''));
    }

    /** 書式の変換を、書き出しと逆の順に戻す。 */
    private function importValue(string $value, array $filters): string
    {
        foreach (array_reverse($filters) as [$name, $arg]) {
            $value = $this->filters()[$name]['import']($value, $arg);
        }

        return $value;
    }

    // ---- 確認画面の表示 ----

    /**
     * 渡した項目に取り込む列の番号。[列の番号 => true]で返す。
     * 全角と半角をそろえない項目の列を、CSVを読む側が見分けるのに使う。
     *
     * @param  array  $map  mapHeadings()・mapByOrder()が返す、列の番号 => [定義の番号, 部分]
     * @param  string[]  $fields  項目の名前
     */
    public function columnsOfFields(array $map, array $fields): array
    {
        $columns = [];

        if ($fields === []) {
            return $columns;
        }

        foreach ($map as $column => [$specIndex]) {
            $spec = $this->specs[$specIndex];

            // @名前の列は取り込む項目を複数持ち、ほかの列は1つ持つ
            $targets = $spec['type'] === 'custom' ? $spec['targets'] : [$spec['target']];

            if (array_intersect($targets, $fields) !== []) {
                $columns[$column] = true;
            }
        }

        return $columns;
    }

    /** 確認画面の変更内容に出す項目の名前。その項目に取り込む最初の列の見出し。 */
    public function fieldLabel(string $field): string
    {
        foreach ($this->specs as $spec) {
            $targets = match ($spec['type']) {
                'custom' => $spec['targets'],
                'group' => array_merge(...array_map(
                    fn ($c) => $c['upload'] ? [$c['upload'][0], "{$c['upload'][0]}_origin"] : [],
                    array_values($spec['group']),
                )),
                'numbered' => $spec['upload'] ? [$spec['upload'][0], "{$spec['upload'][0]}_origin"] : [$spec['target']],
                default => [$spec['target']],
            };

            if (in_array($field, $targets, true)) {
                return $spec['label'];
            }
        }

        return $field;
    }

    /** 値を表示用の文字列にする。一覧から取り込んだ項目は、値を一覧の表示名に戻す。 */
    public function displayValue(string $field, mixed $value): string
    {
        $options = null;
        foreach ($this->specs as $spec) {
            if (in_array($spec['type'], ['list', 'expand'], true) && $spec['target'] === $field) {
                $options = $spec['options'];
                break;
            }
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_map(fn ($v) => $options !== null && $v !== null ? ($options[$v] ?? (string) $v) : (string) $v, $values);

        return implode('、', array_filter($values, fn ($v) => $v !== ''));
    }

    /**
     * 比べるための形にそろえる。nullと空文字は''、真偽値は'1'か'0'、ほかは文字列。
     * 配列は要素をそろえて並べ替える。並び順も比べるときは、$sortをfalseにする。
     */
    public static function normalize(mixed $value, bool $sort = true): string|array
    {
        if (is_array($value)) {
            $values = array_map(fn ($v) => self::normalize($v, $sort), array_values($value));

            if ($sort) {
                sort($values);
            }

            return $values;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
