<?php

/**
 * DBのテーブルの定義を読んで、そのテーブルを管理する画面の下書きを書き出すツール。
 * 書き出すのは、コントローラーのrules()・saveFieldNames()・inputFromModel()と、
 * モデルの$fillable・$casts。列の型や桁数を、人が目で見て書き写す手間を減らすためのもの。
 * 冒頭には、テーブルのCREATE文も参考として書き出す。TODOを直すときに、列の定義を見比べるため。
 *
 *   php tools/table2rules.php t_members              tools/tmp/t_members.rules.txt に書き出す
 *   php tools/table2rules.php t_members t_news       いくつでも並べられる
 *   php tools/table2rules.php --stdout t_members     ファイルに書かず、画面に出す
 *
 * DBは、このプロジェクトの設定（.env）の接続先を読む。Laravelを起動して使うので、
 * プロジェクトの直下から動かす。マイグレーションを実行した後のテーブルが対象。
 *
 * ■ 考え方
 * 書き出すのは下書きで、プロジェクトのファイルは書き換えない。できたテキストから、
 * コントローラーとモデルへ貼り付けて直す。型から決まるルールは全部書き、列の名前から
 * 推測したルールと、人が決めることには「TODO」のコメントを付ける。
 *
 * ■ ルールの決まり
 * - NULLを許さない列はrequired、許す列はnullable
 * - 型ごとのルールは、下の「型から決めるルール」
 * - text型の列は、上限の文字数を決め打ちで書く（ふつうは2000、本文らしい名前の列は20000）
 * - 一意の索引が1列に付いていれば、Rule::unique()
 * - 列の名前と同じ名前の区分表（列挙型・code/のCSV・DBのコード表）があれば、Rule::in(code_keys())
 * - 列の名前から推測するルールは、下の「名前から推測するルール」。ここを足していく
 * - 「列」と「列_origin」の組は、アップロードの欄として扱い、rules()には書かない
 * - 「列_origin」が無くても、photo・imageのような名前の文字の列は、アップロードの欄と推測する
 */

use App\Enums\CodeTableEnum;
use App\Enums\CodeType;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// ---- 設定 ----

// 書き出すディレクトリ。プロジェクトの直下から見た場所
const OUTPUT_DIR = 'tools/tmp';

// 入力する列ではないので、何も書き出さない列
const SKIP_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

// rules()には書かず、理由のコメントだけを書く列。列の名前 => コメント
const NOTE_COLUMNS = [
    'staff_id' => '最後に更新したスタッフのid。入力させず、additionalFields()で入れる',
    'legacy_password' => '既存のシステムから移した古い方式のパスワード。画面からは入力させない',
];

/**
 * 型から決めるルール。型の名前 => ルールの並び。
 * {length}は桁数、{scale}は小数の桁数に置き換わる。
 * 整数の最小と最大は、下のINTEGER_RANGESから別に足す。
 */
const TYPE_RULES = [
    'varchar' => ["'string'", "'max:{length}'"],
    'char' => ["'string'", "'max:{length}'"],
    'tinytext' => ["'string'", "'max:255'"],
    'text' => ["'string'"],
    'mediumtext' => ["'string'"],
    'longtext' => ["'string'"],
    'tinyint' => ["'integer'"],
    'smallint' => ["'integer'"],
    'mediumint' => ["'integer'"],
    'int' => ["'integer'"],
    'bigint' => ["'integer'"],
    'boolean' => ["'boolean'"],
    'decimal' => ["'numeric'", "'decimal:0,{scale}'"],
    'float' => ["'numeric'"],
    'double' => ["'numeric'"],
    'date' => ["'date'"],
    'datetime' => ["'date'"],
    'timestamp' => ["'date'"],
    'time' => ["'date_format:H:i'"],
    'year' => ["'integer'", "'digits:4'"],
    'json' => ["'array'"],
];

// 整数の型に入る値の範囲。型の名前 => [符号ありの最大, 符号なしの最大]。
// 桁あふれでDBのエラーになる前に、検証で止めるために書く。bigintは大きすぎるので書かない
const INTEGER_RANGES = [
    'tinyint' => [127, 255],
    'smallint' => [32767, 65535],
    'mediumint' => [8388607, 16777215],
    'int' => [2147483647, 4294967295],
];

// 文字を入れる型。名前から推測するルールのうち、文字のためのものは、この型の列にだけ当てはめる。
// notice_mail（お知らせメールを受け取るか）のような、名前が似ているだけの数の列を外すため
const STRING_TYPES = ['varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext'];

/**
 * text・mediumtext・longtextの列に付ける、上限の文字数。型からは決まらないので、決め打ちで書く。
 * 上限が無いと、巨大な文字を送りつけられたり、型に入りきらずにDBのエラーで止まったりするため。
 * text型に必ず入るのは、約16,000文字まで（65,535バイト。1文字が最大4バイト）。
 * - TEXT_MAX_LENGTH       ふつうの欄。メモや備考
 * - LONG_TEXT_MAX_LENGTH  本文のような長い文を入れる欄
 * - LONG_TEXT_NAMES       長い文を入れる欄と推測する、列の名前
 */
const TEXT_MAX_LENGTH = 2000;

const LONG_TEXT_MAX_LENGTH = 20000;

const LONG_TEXT_NAMES = '/(^|_)(body|content|contents|html|text|description|detail|details|article)$/';

// 上限の文字数を決め打ちにした列に付けるコメント
const TEXT_NOTE = 'TODO: 上限の文字数は、型からは決まらないので決め打ち。用途に合わせて直す';

/**
 * アップロードの欄と推測する、列の名前。「列_origin」の無い文字の列に当てはめる。
 * 単語は「_」で区切って見る。profileをfileと取り違えないようにするため。
 * 複数形と末尾の数字は同じ単語として扱う。例：photo・main_image・file2・filename1・attachments
 */
const UPLOAD_NAMES = '/(^|_)(photo|image|file|filename|attach|attachment|picture)s?\d*(_|$)/';

/**
 * 名前から推測するルール。列の名前に当てはまる正規表現 => 設定。上から順に見て、
 * 当てはまったものを全部使う。推測なので、書き出したルールにはTODOのコメントが付く。
 * - types    この型の列にだけ当てはめる。書かなければ、どの型の列にも当てはめる
 * - add      足すルール
 * - remove   型から決めたルールのうち、外すもの
 * - note     コメントに書く説明
 * - use      コントローラーの冒頭に要るuse文
 * 新しい決まりは、ここに1行足す。
 */
const NAME_RULES = [
    '/(^|_)e?mail\d*$/' => [
        'types' => STRING_TYPES,
        'add' => ["'email'"],
        'note' => 'メールアドレスと推測',
    ],
    '/(^|_)kana$/' => [
        'types' => STRING_TYPES,
        'add' => ['new KatakanaRule()'],
        'note' => 'フリガナと推測',
        'use' => ['App\Rules\KatakanaRule'],
    ],
    '/(^|_)(phone|tel|fax|mobile)\d*$/' => [
        'types' => STRING_TYPES,
        'add' => ['new PhoneNumberRule()'],
        'remove' => ["'max:{length}'"],
        'note' => '電話番号と推測',
        'use' => ['App\Rules\PhoneNumberRule'],
    ],
    '/(^|_)(zip|zipcode|postal_code)$/' => [
        'types' => STRING_TYPES,
        'add' => ["'regex:/^\\d{3}-?\\d{4}$/'"],
        'note' => '郵便番号と推測',
    ],
    '/(^|_)url$/' => [
        'types' => STRING_TYPES,
        'add' => ["'url'"],
        'note' => 'URLと推測',
    ],
    '/(^|_)password$/' => [
        'types' => STRING_TYPES,
        'add' => ["'min:8'", "'confirmed'"],
        'remove' => ["'max:{length}'"],
        'note' => 'パスワードと推測。保存はsaveFieldNames()に書かず、additionalFields()でハッシュ値にする',
    ],
    '/_id$/' => [
        'types' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal'],
        'add' => [],
        'note' => 'ほかのテーブルのidと推測。行があるかは、Rule::exists()か、選択肢の一覧で確かめる',
    ],
    '/(^|_)(sort_order|sort|display_order)$/' => [
        'types' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal'],
        'add' => ["'min:0'"],
        'note' => '表示順と推測',
    ],
    '/_(count|num|qty|quantity|price|amount)$/' => [
        'types' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal'],
        'add' => ["'min:0'"],
        'note' => '0以上の数と推測',
    ],
];

// inputFromModel()で、日付の型の列を入力欄に入れるときの書式。型の名前 => 書式
const INPUT_DATE_FORMATS = [
    'date' => 'Y-m-d',
    'datetime' => 'Y-m-d\TH:i',
    'timestamp' => 'Y-m-d\TH:i',
];

// モデルの$castsに書く型。型の名前 => キャスト。無い型は書かない
const CAST_TYPES = [
    'tinyint' => 'integer',
    'smallint' => 'integer',
    'mediumint' => 'integer',
    'int' => 'integer',
    'bigint' => 'integer',
    'boolean' => 'boolean',
    'decimal' => 'decimal:{scale}',
    'float' => 'float',
    'double' => 'float',
    'date' => 'date',
    'datetime' => 'datetime',
    'timestamp' => 'datetime',
    'json' => 'array',
];

// ---- 入口 ----

// Laravelを起動する。DBの接続と、区分表を探すのに使う
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

exit(main($argv));

function main(array $argv): int
{
    $stdout = false;
    $tables = [];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--stdout') {
            $stdout = true;
        } else {
            $tables[] = $arg;
        }
    }

    if ($tables === []) {
        fwrite(STDERR, "使い方: php tools/table2rules.php [--stdout] テーブル名 [テーブル名 …]\n");

        return 1;
    }

    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            fwrite(STDERR, "テーブルがありません: {$table}\n");

            return 1;
        }

        $draft = (new TableDraft($table))->render();

        if ($stdout) {
            echo $draft;

            continue;
        }

        $directory = base_path(OUTPUT_DIR);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.$table.'.rules.txt';
        file_put_contents($path, $draft);
        fwrite(STDERR, "{$table} → ".OUTPUT_DIR."/{$table}.rules.txt\n");
    }

    return 0;
}

/**
 * 1つのテーブルの下書きを作る。コンストラクタで列ごとの情報をそろえ、render()で書き出す。
 */
final class TableDraft
{
    /** モデルのクラス名（名前空間なし）と、コントローラーで使う変数の名前 */
    private string $model;

    private string $variable;

    /**
     * 書き出す列。列の名前 => 情報。
     * - type・length・scale・unsigned・nullable・default  DBの定義
     * - rules   rules()に書くルールの並び。nullなら、rules()に書かない列
     * - notes   その列に付けるコメント
     * - upload  アップロードの欄か
     * - origin  「列_origin」の列があるか
     */
    private array $columns = [];

    /** コントローラーの冒頭に要るuse文 */
    private array $uses = [];

    public function __construct(private readonly string $table)
    {
        // t_membersならMember。モデルがあるかどうかに関係なく、名前の決まりから作る
        $this->model = Str::studly(Str::singular(preg_replace('/^t_/', '', $table)));
        $this->variable = '$'.Str::camel($this->model);

        $columns = [];
        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }

        // 1列だけの一意の索引が付いている列
        $unique = [];
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && ! $index['primary'] && count($index['columns']) === 1) {
                $unique[$index['columns'][0]] = true;
            }
        }

        foreach ($columns as $name => $column) {
            if (in_array($name, SKIP_COLUMNS, true) || $column['auto_increment']) {
                continue;
            }

            // 「列_origin」は、アップロードの欄の元のファイル名。組になる列の側でまとめて扱う
            if (str_ends_with($name, '_origin') && isset($columns[substr($name, 0, -7)])) {
                continue;
            }

            $this->columns[$name] = $this->describe($column, isset($unique[$name]), isset($columns[$name.'_origin']));
        }
    }

    // ---- 列ごとの情報 ----

    private function describe(array $column, bool $unique, bool $hasOrigin): array
    {
        $name = $column['name'];
        $type = $this->typeOf($column);
        [$length, $scale] = $this->sizeOf($column['type']);

        // アップロードの欄か。「列_origin」があれば確実。無ければ、文字の列の名前から推測する
        $guessedUpload = ! $hasOrigin
            && ! isset(NOTE_COLUMNS[$name])
            && in_array($type, STRING_TYPES, true)
            && preg_match(UPLOAD_NAMES, $name) === 1;
        $upload = $hasOrigin || $guessedUpload;

        $info = [
            'type' => $type,
            'length' => $length,
            'scale' => $scale,
            'unsigned' => str_contains($column['type'], 'unsigned'),
            'nullable' => $column['nullable'],
            'default' => $this->defaultOf($column),
            'rules' => null,
            'notes' => $column['comment'] ? [$column['comment']] : [],
            'upload' => $upload,
            'origin' => $hasOrigin,
        ];

        // アップロードの欄は、rules()に書かない
        if ($upload) {
            // 名前から推測した欄は、元のファイル名の列が無いことも伝える
            if ($guessedUpload) {
                $info['notes'][] = "TODO: 名前からアップロードの欄と推測。違うなら、ふつうの欄としてルールを書く。元のファイル名を入れる {$name}_origin の列が無いので、マイグレーションで足す";
            }

            $info['notes'][] = 'アップロードの欄。UPLOAD_FILESに書き、rules()には + $this->ajaxUploadRules() で足す';

            return $info;
        }

        // 入力させない列
        if (isset(NOTE_COLUMNS[$name])) {
            $info['notes'][] = NOTE_COLUMNS[$name];

            return $info;
        }

        // 必須かどうか。NULLを許さない列は、既定値があっても必須にする。
        // 保存では、送られてこなかった項目をnullで保存しようとして、DBのエラーになるため
        $rules = [$column['nullable'] ? "'nullable'" : "'required'"];

        // 型から決まるルール
        if (! isset(TYPE_RULES[$type]) && $type !== 'enum' && $type !== 'set') {
            $info['notes'][] = "TODO: {$column['type']} 型のルールは決まりがありません";
        }

        foreach (TYPE_RULES[$type] ?? [] as $rule) {
            $rules[] = $this->fill($rule, $info);
        }

        // 長い文の型は、上限の文字数を決め打ちで足す。本文らしい名前の列は大きくする
        if (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            $isLong = preg_match(LONG_TEXT_NAMES, $name) === 1;
            $rules[] = "'max:".($isLong ? LONG_TEXT_MAX_LENGTH : TEXT_MAX_LENGTH)."'";
            $info['notes'][] = TEXT_NOTE.($isLong ? '（本文のような長い文の欄と推測）' : '');
        }

        // 整数の範囲
        if (isset(INTEGER_RANGES[$type])) {
            if ($info['unsigned']) {
                $rules[] = "'min:0'";
            }
            $rules[] = "'max:".INTEGER_RANGES[$type][$info['unsigned'] ? 1 : 0]."'";
        } elseif ($info['unsigned'] && in_array($type, ['bigint', 'decimal', 'float', 'double'], true)) {
            $rules[] = "'min:0'";
        }

        // 小数は、整数の部分の桁数から最大の値を決める
        if ($type === 'decimal' && $length !== null) {
            $rules[] = "'max:".str_repeat('9', max(1, $length - (int) $scale)).($scale ? '.'.str_repeat('9', $scale) : '')."'";
        }

        // enum・setは、定義にある値だけ
        if ($type === 'enum' || $type === 'set') {
            preg_match_all("/'((?:[^']|'')*)'/", $column['type'], $matches);
            $values = implode(', ', array_map(fn ($v) => "'".str_replace("''", "\\'", $v)."'", $matches[1]));
            $rules[] = "Rule::in([{$values}])";
            $this->uses['Illuminate\Validation\Rule'] = true;
        }

        // 列の名前と同じ名前の区分表があれば、その値だけにする。範囲のルールは要らなくなる
        $codeName = $this->findCodeTable($name);

        if ($codeName !== null) {
            $rules = array_values(array_filter($rules, fn ($rule) => ! preg_match("/^'(min|max):/", $rule)));
            $rules[] = "Rule::in(code_keys('{$codeName}'))";
            $info['notes'][] = "区分表（{$codeName}）の値と一致すること";
            $this->uses['Illuminate\Validation\Rule'] = true;
        }

        // 名前から推測するルール
        foreach (NAME_RULES as $pattern => $guess) {
            if (! preg_match($pattern, $name)) {
                continue;
            }

            // 型の合わない列には当てはめない
            if (isset($guess['types']) && ! in_array($type, $guess['types'], true)) {
                continue;
            }

            // 区分表が見つかった列は、idの推測を重ねない
            if ($codeName !== null && $pattern === '/_id$/') {
                continue;
            }

            $remove = array_map(fn ($rule) => $this->fill($rule, $info), $guess['remove'] ?? []);
            $rules = array_values(array_diff($rules, $remove));
            array_push($rules, ...$guess['add']);
            $info['notes'][] = 'TODO: '.$guess['note'];

            foreach ($guess['use'] ?? [] as $use) {
                $this->uses[$use] = true;
            }
        }

        // 一意の索引
        if ($unique) {
            $rules[] = "Rule::unique({$this->model}::class, '{$name}')->ignore({$this->variable}?->id)";
            $info['notes'][] = '自分を除いて、ほかの行と重ならないこと';
            $this->uses['Illuminate\Validation\Rule'] = true;
        }

        if ($info['default'] !== null) {
            $info['notes'][] = "DBの既定値は {$info['default']}。新規登録の初期値にするなら、defaultInput()に書く";
        }

        $info['rules'] = array_values(array_unique($rules));

        return $info;
    }

    /** 型の名前。tinyint(1)は、真偽値として扱う */
    private function typeOf(array $column): string
    {
        if (preg_match('/^tinyint\(1\)/', $column['type'])) {
            return 'boolean';
        }

        return match ($column['type_name']) {
            'integer' => 'int',
            'bool' => 'boolean',
            'numeric' => 'decimal',
            'character varying' => 'varchar',
            default => $column['type_name'],
        };
    }

    /** varchar(255)の255や、decimal(10,2)の10と2 */
    private function sizeOf(string $type): array
    {
        if (preg_match('/\((\d+)(?:,\s*(\d+))?\)/', $type, $m)) {
            return [(int) $m[1], isset($m[2]) ? (int) $m[2] : null];
        }

        return [null, null];
    }

    /** DBの既定値。無ければnull。MariaDBは、既定値なしを'NULL'という文字で返す */
    private function defaultOf(array $column): ?string
    {
        $default = $column['default'];

        if ($default === null || strtoupper((string) $default) === 'NULL') {
            return null;
        }

        return trim((string) $default, "'");
    }

    /** {length}・{scale}を、その列の値に置き換える */
    private function fill(string $rule, array $info): string
    {
        return str_replace(['{length}', '{scale}'], [(string) $info['length'], (string) (int) $info['scale']], $rule);
    }

    /**
     * 列の名前に当たる区分表を探して、code_table()に渡す名前を返す。無ければnull。
     * 列の名前そのまま、複数形、「テーブルの名前_列の名前」の順に探す。
     * 例：prefectureはprefectures、t_companiesのstatusはcompany_status
     */
    private function findCodeTable(string $column): ?string
    {
        $singularTable = Str::snake($this->model);

        foreach ([$column, Str::plural($column), "{$singularTable}_{$column}"] as $candidate) {
            $enumClass = 'App\\Enums\\'.Str::studly($candidate);

            $exists = (enum_exists($enumClass) && is_subclass_of($enumClass, CodeTableEnum::class))
                || is_file(base_path("code/{$candidate}.csv"))
                || CodeType::tryFrom($candidate) !== null;

            if ($exists) {
                return $candidate;
            }
        }

        return null;
    }

    // ---- 書き出し ----

    public function render(): string
    {
        $parts = [
            $this->renderHeader(),
            $this->renderCreateTable(),
            $this->renderUses(),
            $this->renderRules(),
            $this->renderSaveFieldNames(),
            $this->renderInputFromModel(),
            $this->renderFillable(),
            $this->renderCasts(),
        ];

        return implode("\n", $parts);
    }

    private function renderHeader(): string
    {
        $lines = [
            "{$this->table} の下書き（tools/table2rules.php が書き出したもの）",
            '',
            "モデルは {$this->model}、コントローラーの変数は {$this->variable} として書いています。",
            'このファイルは下書きです。コントローラーとモデルへ貼り付けて、TODOの付いた所を直します。',
        ];

        return implode("\n", $lines)."\n";
    }

    /** テーブルのCREATE文。貼り付けるものではなく、TODOを直すときに列の定義を見比べるための参考 */
    private function renderCreateTable(): string
    {
        $lines = [
            '================================================================',
            '参考：テーブルのCREATE文',
            '================================================================',
        ];

        // SHOW CREATE TABLEは、MySQLとMariaDBだけで使える
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $lines[] = '（このDBでは、CREATE文を取り出せません）';

            return implode("\n", $lines)."\n";
        }

        $row = (array) DB::selectOne('SHOW CREATE TABLE '.DB::connection()->getQueryGrammar()->wrapTable($this->table));

        // DBが返した文のまま書く。連番の次の値（AUTO_INCREMENT=）も、行の数の目安になるので残す
        $lines[] = $row['Create Table'].';';

        return implode("\n", $lines)."\n";
    }

    private function renderUses(): string
    {
        $lines = [
            '================================================================',
            'コントローラーの冒頭に要るuse文',
            '================================================================',
            "use App\\Models\\{$this->model};",
        ];

        $uses = array_keys($this->uses);
        sort($uses);
        foreach ($uses as $use) {
            $lines[] = "use {$use};";
        }

        return implode("\n", $lines)."\n";
    }

    private function renderRules(): string
    {
        $lines = [
            '================================================================',
            'コントローラー：rules()',
            '================================================================',
            '    /** 入力の検証ルール。'.$this->variable.'は、新規登録ならnull、更新なら対象の行 */',
            "    private function rules(?{$this->model} {$this->variable}): array",
            '    {',
            '        return [',
        ];

        foreach ($this->columns as $name => $info) {
            foreach ($info['notes'] as $note) {
                $lines[] = "            // {$note}";
            }

            // rules()に書かない列は、コメントだけを残す
            if ($info['rules'] === null) {
                $lines[] = "            // '{$name}' は、ここには書かない";

                continue;
            }

            // Rule::…や new …() があるルールは、1行ずつに分ける
            $complex = array_values(array_filter($info['rules'], fn ($rule) => ! str_starts_with($rule, "'")));
            $simple = array_values(array_filter($info['rules'], fn ($rule) => str_starts_with($rule, "'")));

            if ($complex === []) {
                $lines[] = "            '{$name}' => [".implode(', ', $simple).'],';

                continue;
            }

            $lines[] = "            '{$name}' => [";
            $lines[] = '                '.implode(', ', $simple).',';
            foreach ($complex as $rule) {
                $lines[] = "                {$rule},";
            }
            $lines[] = '            ],';
        }

        $hasUpload = array_filter($this->columns, fn ($info) => $info['upload']) !== [];
        $lines[] = '        ]'.($hasUpload ? ' + $this->ajaxUploadRules()' : '').';';
        $lines[] = '    }';

        return implode("\n", $lines)."\n";
    }

    /** rules()に書いた列の名前。パスワードは、入力値をそのまま保存しないので外す */
    private function savedColumns(): array
    {
        return array_keys(array_filter(
            $this->columns,
            fn ($info, $name) => $info['rules'] !== null && ! preg_match('/(^|_)password$/', $name),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function renderSaveFieldNames(): string
    {
        $names = implode(', ', array_map(fn ($name) => "'{$name}'", $this->savedColumns()));

        return implode("\n", [
            '================================================================',
            'コントローラー：saveFieldNames()',
            '================================================================',
            '    /** 保存する項目。ここに書いた項目だけを保存する */',
            "    private function saveFieldNames(array \$validated, {$this->model} {$this->variable}): array",
            '    {',
            "        return [{$names}];",
            '    }',
        ])."\n";
    }

    private function renderInputFromModel(): string
    {
        $lines = [
            '================================================================',
            'コントローラー：inputFromModel()',
            '================================================================',
            '    /** モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う） */',
            "    private function inputFromModel({$this->model} {$this->variable}): array",
            '    {',
            '        return [',
        ];

        foreach ($this->savedColumns() as $name) {
            $type = $this->columns[$name]['type'];

            // 日付の型は、入力欄の書式の文字にする
            $value = isset(INPUT_DATE_FORMATS[$type])
                ? "{$this->variable}->{$name}?->format('".INPUT_DATE_FORMATS[$type]."')"
                : "{$this->variable}->{$name}";

            // 真偽値は、ラジオボタンの値と比べられるよう、'1'か'0'にする
            if ($type === 'boolean') {
                $value = "{$this->variable}->{$name} ? '1' : '0'";
            }

            $lines[] = "            '{$name}' => {$value},";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return implode("\n", $lines)."\n";
    }

    private function renderFillable(): string
    {
        $lines = [
            '================================================================',
            'モデル：$fillable',
            '================================================================',
            "    protected \$table = '{$this->table}';",
            '',
            '    protected $fillable = [',
        ];

        foreach ($this->columns as $name => $info) {
            $lines[] = "        '{$name}',";

            // アップロードの欄は、元のファイル名の列も入れる。列がまだ無ければ、TODOを付ける
            if ($info['upload']) {
                $lines[] = "        '{$name}_origin',".($info['origin'] ? '' : ' // TODO: 列が無い。マイグレーションで足す');
            }
        }

        $lines[] = '    ];';

        return implode("\n", $lines)."\n";
    }

    private function renderCasts(): string
    {
        $lines = [
            '================================================================',
            'モデル：$casts',
            '================================================================',
            '    protected $casts = [',
        ];

        foreach ($this->columns as $name => $info) {
            if (! isset(CAST_TYPES[$info['type']]) || preg_match('/(^|_)password$/', $name)) {
                continue;
            }

            $lines[] = "        '{$name}' => '".$this->fill(CAST_TYPES[$info['type']], $info)."',";
        }

        $lines[] = '    ];';

        return implode("\n", $lines)."\n";
    }
}
