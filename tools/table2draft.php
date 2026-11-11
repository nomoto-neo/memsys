<?php

/**
 * DBのテーブルの定義を読んで、そのテーブルを管理するコーナーの下書きを書き出すツール。
 * 書き出すのは、コントローラー・モデル・入力欄の画面（_fields.blade.php）の3つ。
 * 列の型や桁数、子テーブルや多対多のつながりを、人が目で見て書き写す手間を減らすためのもの。
 *
 *   php tools/table2draft.php t_members              tools/tmp/ に t_members.controller.php・
 *                                                    t_members.model.php・t_members.fields.blade.php を書き出す
 *   php tools/table2draft.php t_members t_news       いくつでも並べられる
 *   php tools/table2draft.php --stdout t_members     ファイルに書かず、画面に出す
 *
 * DBは、このプロジェクトの設定（.env）の接続先を読む。Laravelを起動して使うので、
 * プロジェクトの直下から動かす。マイグレーションを実行した後のテーブルが対象。
 *
 * ■ 考え方
 * 書き出すのは下書きで、プロジェクトのファイルは書き換えない。コントローラーとモデルは、
 * クラスの形で書き出す。エディタで色分けが効き、メソッドを丸ごと選んで貼れるようにするため。
 * 型から決まるものは全部書き、列の名前から推測したものと、人が決めることには「TODO」のコメントを付ける。
 * 項目の名前は、列のCOMMENTから取る。COMMENTの無い列は、列の名前のままにしてTODOを付ける。
 *
 * ■ ルールの決まり
 * - NULLを許さない列はrequired、許す列はnullable
 * - 型ごとのルールは、下の「型から決めるルール」
 * - text型の列は、上限の文字数を決め打ちで書く（ふつうは2000、本文らしい名前の列は20000）
 * - 一意の索引が1列に付いていれば、Rule::unique()
 * - 列の名前と同じ名前の区分表（列挙型・code/のCSV・DBのコード表）があれば、Rule::in(code_keys())
 * - 列の名前から推測するルールは、下の「名前から推測するルール」。ここを足していく
 * - DBの既定値がある列は、defaultValues()に書く
 *
 * ■ アップロードの決まり
 * - 「列」と「列_origin」の組は、アップロードの欄として扱い、rules()ではなくUPLOAD_FILESに書く
 * - 「列_origin」が無くても、photo・imageのような名前の文字の列は、アップロードの欄と推測する
 * - photo・image・pictureの名前は画像、file・attachの名前は添付ファイル。どちらでもなければ画像としてTODOを付ける
 * - 画像の横幅は、モデルに「列_WIDTH」の定数として仮の値で書き、UPLOAD_FILESから参照する
 *
 * ■ ほかのテーブルとのつながりの決まり
 * - 子テーブル：t_で始まるほかのテーブルのうち、「このテーブルの単数形_id」の列（t_newsならnews_id）を持つもの。
 *   CHILD_TABLESと組の項目のルール、親のモデルのhasManyを書く。組の名前は、子テーブルの名前からt_と親の名前を外したもの
 * - 中間テーブル：id・created_at・updated_atを除いた列が、_idで終わる2つだけのテーブル。
 *   子テーブルにはせず、多対多として、選んだidの配列のルール・afterSave()のsync()・モデルのbelongsToManyを書く
 * - 「xxx_id」の列で、t_xxxs（xxxの複数形）のテーブルがあれば、モデルにbelongsToを書く
 * - deleted_atの列があれば、モデルにSoftDeletesを付ける
 *
 * ■ 一覧の決まり
 * - name・title・kana・subjectで終わる文字の列は、フリーワード検索の対象
 * - 区分表のある列は、配列で受けてIN()。真偽の列は完全一致
 * - 並び順は、更新日の新しい順・古い順。日付の列があれば、最初の1つの新しい順・古い順も足す
 * - 列にCOMMENTが1つでもあれば、CSVダウンロードの項目（csvColumns()）も書く
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Neobit\Core\Enums\CodeTableEnum;

// ---- 設定 ----

// 書き出すディレクトリ。プロジェクトの直下から見た場所
const OUTPUT_DIR = 'tools/tmp';

// 入力する列ではないので、何も書き出さない列
const SKIP_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

// rules()には書かず、理由のコメントだけを書く列。列の名前 => コメント
const NOTE_COLUMNS = [
    'staff_id' => '最後に更新したスタッフのid。入力させず、saveValues()で入れる',
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

// 数の型。区分表の値やidの列を見分けるのに使う
const NUMBER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal'];

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

// アップロードの欄のうち、画像と見なす名前と、添付ファイルと見なす名前。どちらでもなければ画像としてTODOを付ける
const IMAGE_NAMES = '/(^|_)(photo|image|picture)s?\d*(_|$)/';

const FILE_NAMES = '/(^|_)(file|filename|attach|attachment)s?\d*(_|$)/';

// 画像の横幅の仮の値(px)。モデルの定数に書き、データ項目の仕様に合わせて人が直す
const DEFAULT_IMAGE_WIDTH = 1000;

// 並び順の列と見なす名前。名前から推測するルールと、子テーブルの並び順の列の両方に使う
const ORDER_NAMES = '/(^|_)(seq|sort_order|sort|display_order)$/';

// 子テーブルの行の数の上限の仮の値
const DEFAULT_CHILD_MAX = 10;

// フリーワード検索の対象と見なす、文字の列の名前
const FREE_WORD_NAMES = '/(^|_)(name|title|kana|subject)$/';

// 一覧の1ページの件数
const PER_PAGE = 20;

/**
 * 名前から推測するルール。列の名前に当てはまる正規表現 => 設定。上から順に見て、
 * 当てはまったものを全部使う。推測なので、書き出したルールにはTODOのコメントが付く。
 * - types    この型の列にだけ当てはめる。書かなければ、どの型の列にも当てはめる
 * - add      足すルール
 * - remove   型から決めたルールのうち、外すもの
 * - note     コメントに書く説明
 * - use      コントローラーの冒頭に要るuse文
 * - input    入力欄の画面で使う<input>のtype。書かなければtext
 * 新しい決まりは、ここに1行足す。
 */
const NAME_RULES = [
    '/(^|_)e?mail\d*$/' => [
        'types' => STRING_TYPES,
        'add' => ["'email'"],
        'note' => 'メールアドレスと推測',
        'input' => 'email',
    ],
    '/(^|_)kana$/' => [
        'types' => STRING_TYPES,
        'add' => ['new KatakanaRule()'],
        'note' => 'フリガナと推測',
        'use' => ['Neobit\Core\Rules\KatakanaRule'],
    ],
    '/(^|_)(phone|tel|fax|mobile)\d*$/' => [
        'types' => STRING_TYPES,
        'add' => ['new PhoneNumberRule()'],
        'remove' => ["'max:{length}'"],
        'note' => '電話番号と推測',
        'use' => ['Neobit\Core\Rules\PhoneNumberRule'],
        'input' => 'tel',
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
        'input' => 'url',
    ],
    '/(^|_)password$/' => [
        'types' => STRING_TYPES,
        'add' => ["'min:8'", "'confirmed'"],
        'remove' => ["'max:{length}'"],
        'note' => 'パスワードと推測。保存はsaveValues()でハッシュ値にする',
        'input' => 'password',
    ],
    '/_id$/' => [
        'types' => NUMBER_TYPES,
        'add' => [],
        'note' => 'ほかのテーブルのidと推測。行があるかは、Rule::exists()か、選択肢の一覧で確かめる',
    ],
    ORDER_NAMES => [
        'types' => NUMBER_TYPES,
        'add' => ["'min:0'"],
        'note' => '表示順と推測',
    ],
    '/_(count|num|qty|quantity|price|amount)$/' => [
        'types' => NUMBER_TYPES,
        'add' => ["'min:0'"],
        'note' => '0以上の数と推測',
    ],
];

// formValues()で、日付の型の列を入力欄に入れるときの書式。型の名前 => 書式
const INPUT_DATE_FORMATS = [
    'date' => 'Y-m-d',
    'datetime' => 'Y-m-d\TH:i',
    'timestamp' => 'Y-m-d\TH:i',
];

// 入力欄の画面で、型ごとに使う<input>のtype。無い型は、文字の欄（text）にする
const INPUT_TYPES = [
    'date' => 'date',
    'datetime' => 'datetime-local',
    'timestamp' => 'datetime-local',
    'time' => 'time',
    'tinyint' => 'number',
    'smallint' => 'number',
    'mediumint' => 'number',
    'int' => 'number',
    'bigint' => 'number',
    'decimal' => 'number',
    'float' => 'number',
    'double' => 'number',
    'year' => 'number',
];

// CSVに出すときの、日付の型の書式。型の名前 => 書式
const CSV_DATE_FORMATS = [
    'date' => 'Y/m/d',
    'datetime' => 'Y/m/d H:i',
    'timestamp' => 'Y/m/d H:i',
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
        fwrite(STDERR, "使い方: php tools/table2draft.php [--stdout] テーブル名 [テーブル名 …]\n");

        return 1;
    }

    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            fwrite(STDERR, "テーブルがありません: {$table}\n");

            return 1;
        }

        $draft = new TableDraft($table);

        // 書き出すファイル。テーブル名の後ろは「.」で区切る。「_」だと、テーブル名の切れ目が分からないため
        $files = [
            "{$table}.controller.php" => $draft->renderController(),
            "{$table}.model.php" => $draft->renderModel(),
            "{$table}.fields.blade.php" => $draft->renderFields(),
        ];

        // 画面に出すときは、ファイルの名前を見出しにして続けて出す
        if ($stdout) {
            foreach ($files as $name => $content) {
                echo "==== {$name} ====\n{$content}\n";
            }

            continue;
        }

        $directory = base_path(OUTPUT_DIR);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        foreach ($files as $name => $content) {
            file_put_contents("{$directory}/{$name}", $content);
            fwrite(STDERR, "{$table} → ".OUTPUT_DIR."/{$name}\n");
        }
    }

    return 0;
}

/**
 * 1つのテーブルの下書きを作る。コンストラクタで列ごとの情報と、ほかのテーブルとのつながりをそろえ、
 * render…()で書き出す。子テーブルの列も、このクラスで読む（そのときは、つながりは探さない）。
 */
final class TableDraft
{
    /** モデルのクラス名（名前空間なし）と、コントローラーで使う変数の名前 */
    public readonly string $model;

    private string $variable;

    /** テーブルの名前からt_を外した単数形。t_newsならnews。子テーブルの「親_id」と、組の名前を作るのに使う */
    private string $singular;

    /** ルート名とURLに使う名前。t_bulk_mailsならbulk-mails */
    private string $routeName;

    /**
     * 書き出す列。列の名前 => 情報。
     * - type・length・scale・unsigned・nullable・default  DBの定義
     * - comment  列のCOMMENT。無ければnull
     * - rules   rules()に書くルールの並び。nullなら、rules()に書かない列
     * - notes   その列に付けるコメント
     * - upload  アップロードの欄か
     * - origin  「列_origin」の列があるか
     * - code    区分表の名前。無ければnull
     * - input   入力欄の<input>のtype（名前から推測したもの）。無ければnull
     */
    public array $columns = [];

    /** コントローラーの冒頭に要るuse文 */
    public array $uses = [];

    /** テーブルの全部の列の名前。書き出さない列（id・updated_at・deleted_atなど）も入る */
    private array $allColumns = [];

    /**
     * 子テーブル。組の名前 => 情報。
     * - table・model・foreignKey・order  子テーブルの名前・モデル・親を指す列・並び順の列（無ければnull）
     * - fields  組の項目の列の名前
     * - draft   子テーブルの列の情報（TableDraft）
     */
    private array $children = [];

    /**
     * 多対多。リレーションの名前 => 情報。
     * - pivot・foreignKey・relatedKey  中間テーブルと、その中の親を指す列・相手を指す列
     * - relatedTable・relatedModel・input  相手のテーブル・モデル・入力の名前（category_ids）
     */
    private array $pivots = [];

    /** belongsTo。リレーションの名前 => [列の名前, 相手のモデル] */
    private array $belongsTo = [];

    public function __construct(public readonly string $table, bool $withRelations = true)
    {
        // t_membersならMember。モデルがあるかどうかに関係なく、名前の決まりから作る
        $base = preg_replace('/^t_/', '', $table);
        $this->singular = Str::singular($base);
        $this->model = Str::studly($this->singular);
        $this->variable = '$'.Str::camel($this->model);
        $this->routeName = str_replace('_', '-', $base);

        $columns = [];
        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }
        $this->allColumns = array_keys($columns);

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

        // 子テーブルの列を読むときは、その先のつながりは探さない
        if ($withRelations) {
            $this->findBelongsTo();
            $this->findChildrenAndPivots();
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
            'comment' => $column['comment'] ?: null,
            'rules' => null,
            'notes' => $column['comment'] ? [$column['comment']] : [],
            'upload' => $upload,
            'origin' => $hasOrigin,
            'code' => null,
            'input' => null,
        ];

        // アップロードの欄は、rules()に書かない
        if ($upload) {
            // 名前から推測した欄は、元のファイル名の列が無いことも伝える
            if ($guessedUpload) {
                $info['notes'][] = "TODO: 名前からアップロードの欄と推測。違うなら、ふつうの欄としてルールを書く。元のファイル名を入れる {$name}_origin の列が無いので、マイグレーションで足す";
            }

            $info['notes'][] = 'アップロードの欄。UPLOAD_FILESに書き、ルールはajaxUploadRules()が足す';

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
            $info['code'] = $codeName;
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
            $info['input'] ??= $guess['input'] ?? null;

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
                || isset(config('neobit.code_table.db')[$candidate])
                || isset(config('neobit.code_table.method')[$candidate]);

            if ($exists) {
                return $candidate;
            }
        }

        return null;
    }

    /** 項目の名前。列のCOMMENTがあればそれ、無ければ列の名前 */
    public function labelOf(string $name): string
    {
        return $this->columns[$name]['comment'] ?? $name;
    }

    /** アップロードの欄の種類。画像ならtrue。名前で決まらなければnull */
    private function isImage(string $name): ?bool
    {
        if (preg_match(IMAGE_NAMES, $name)) {
            return true;
        }

        if (preg_match(FILE_NAMES, $name)) {
            return false;
        }

        return null;
    }

    // ---- ほかのテーブルとのつながり ----

    /** 「xxx_id」の列のうち、t_xxxsのテーブルがあるものを、belongsToにする */
    private function findBelongsTo(): void
    {
        foreach ($this->columns as $name => $info) {
            if (! str_ends_with($name, '_id') || ! in_array($info['type'], NUMBER_TYPES, true)) {
                continue;
            }

            // staffのように複数形が同じ形になる語は、sを付けた名前（t_staffs）も探す
            $base = substr($name, 0, -3);
            foreach (array_unique(['t_'.Str::plural($base), "t_{$base}s"]) as $candidate) {
                if (Schema::hasTable($candidate)) {
                    $this->belongsTo[Str::camel($base)] = [$name, Str::studly($base)];
                    break;
                }
            }
        }
    }

    /**
     * 「このテーブルの単数形_id」の列を持つ、t_で始まるほかのテーブルを探す。
     * 中間テーブルは多対多に、それ以外は子テーブルにする。
     */
    private function findChildrenAndPivots(): void
    {
        $foreignKey = "{$this->singular}_id";

        foreach (Schema::getTables() as $tableInfo) {
            $other = $tableInfo['name'];
            if (! str_starts_with($other, 't_') || $other === $this->table) {
                continue;
            }

            $columns = array_column(Schema::getColumns($other), 'name');
            if (! in_array($foreignKey, $columns, true)) {
                continue;
            }

            // id・登録日時・更新日時を除いて、_idの列が2つだけなら中間テーブル
            $rest = array_values(array_diff($columns, ['id', 'created_at', 'updated_at']));
            $idColumns = array_filter($rest, fn ($column) => str_ends_with($column, '_id'));

            if (count($rest) === 2 && count($idColumns) === 2) {
                $relatedKey = current(array_diff($rest, [$foreignKey]));
                $relatedBase = substr($relatedKey, 0, -3);

                $this->pivots[Str::camel(Str::plural($relatedBase))] = [
                    'pivot' => $other,
                    'foreignKey' => $foreignKey,
                    'relatedKey' => $relatedKey,
                    'relatedTable' => 't_'.Str::plural($relatedBase),
                    'relatedModel' => Str::studly($relatedBase),
                    'input' => "{$relatedBase}_ids",
                ];

                continue;
            }

            // 子テーブル。組の名前は、t_と親の名前を外したもの（t_news_photosならphotos）
            $base = preg_replace('/^t_/', '', $other);
            $group = str_starts_with($base, "{$this->singular}_") ? substr($base, strlen($this->singular) + 1) : $base;

            $draft = new self($other, withRelations: false);
            $order = current(array_filter($columns, fn ($column) => preg_match(ORDER_NAMES, $column) === 1)) ?: null;

            // 組の項目は、親を指す列・並び順の列・入力させない列を除いたもの
            $fields = array_values(array_filter(
                array_keys($draft->columns),
                fn ($name) => $name !== $foreignKey && $name !== $order && ! isset(NOTE_COLUMNS[$name]),
            ));

            $this->children[$group] = [
                'table' => $other,
                'model' => Str::studly(Str::singular($base)),
                'foreignKey' => $foreignKey,
                'order' => $order,
                'fields' => $fields,
                'draft' => $draft,
            ];

            foreach ($draft->uses as $use => $true) {
                $this->uses[$use] = true;
            }
        }
    }

    // ---- 書き出しで使う情報 ----

    /** アップロードの欄。UPLOAD_FILESのキー => [列の名前, 情報, 横幅の定数の名前, 画像か] */
    private function uploadFields(): array
    {
        $fields = [];

        foreach ($this->columns as $name => $info) {
            if ($info['upload']) {
                $fields[$name] = [$name, $info, strtoupper($name).'_WIDTH', $this->isImage($name)];
            }
        }

        foreach ($this->children as $group => $child) {
            foreach ($child['fields'] as $name) {
                $info = $child['draft']->columns[$name];
                if ($info['upload']) {
                    $fields["{$group}.*.{$name}"] = [$name, $info, strtoupper("{$group}_{$name}").'_WIDTH', $this->isImage($name)];
                }
            }
        }

        return $fields;
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

    /** rules()に書いた、パスワードの列の名前 */
    private function passwordColumns(): array
    {
        return array_keys(array_filter(
            $this->columns,
            fn ($info, $name) => $info['rules'] !== null && preg_match('/(^|_)password$/', $name),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** 列にCOMMENTが1つでもあるか。CSVの項目の見出しに使えるかどうか */
    private function hasComments(): bool
    {
        return array_filter($this->columns, fn ($info) => $info['comment'] !== null) !== [];
    }

    /** ルールの並びを、rules()の1項目の行にする。Rule::…や new …() があれば1行ずつに分ける */
    private function ruleLines(string $key, array $rules, string $indent): array
    {
        $complex = array_values(array_filter($rules, fn ($rule) => ! str_starts_with($rule, "'")));
        $simple = array_values(array_filter($rules, fn ($rule) => str_starts_with($rule, "'")));

        if ($complex === []) {
            return ["{$indent}'{$key}' => [".implode(', ', $simple).'],'];
        }

        $lines = ["{$indent}'{$key}' => ["];
        $lines[] = "{$indent}    ".implode(', ', $simple).',';
        foreach ($complex as $rule) {
            $lines[] = "{$indent}    {$rule},";
        }
        $lines[] = "{$indent}],";

        return $lines;
    }

    // ---- 書き出し：コントローラー ----

    public function renderController(): string
    {
        $hasUpload = $this->uploadFields() !== [];
        $hasCsv = $this->hasComments();

        // 冒頭のuse文。トレイトと、ルール・保存で使うクラス
        $uses = array_keys($this->uses);
        $uses[] = 'App\Http\Controllers\Controller';
        $uses[] = "App\\Models\\{$this->model}";
        $uses[] = 'Illuminate\Database\Eloquent\Builder';
        $uses[] = 'Neobit\Core\Crud\FormFlow';
        $uses[] = 'Neobit\Core\Crud\SearchableList';
        if ($hasUpload) {
            $uses[] = 'Neobit\Core\Upload\AjaxFileUpload';
            $uses[] = 'Neobit\Core\Upload\UploadType';
        }
        if ($this->children !== []) {
            $uses[] = 'Neobit\Core\Crud\ChildTables';
        }
        if ($hasCsv) {
            $uses[] = 'Neobit\Core\Csv\CsvDownload';
        }
        if ($this->pivots !== []) {
            $uses[] = 'Illuminate\Validation\Rule';
        }
        if (isset($this->columns['staff_id'])) {
            $uses[] = 'Illuminate\Support\Facades\Auth';
        }
        if ($this->passwordColumns() !== []) {
            $uses[] = 'Illuminate\Support\Facades\Hash';
        }
        $uses = array_unique($uses);
        sort($uses);

        $lines = [
            '<?php',
            '',
            '/*',
            " * {$this->table} のコントローラーの下書き（tools/table2draft.php が書き出したもの）。",
            " * モデルは {$this->model}、変数は {$this->variable} として書いている。",
            ' * 下書きなので、コントローラーへ貼り付けて、TODOの付いた所を直す。入口（index・create など）は、実例を写して書く。',
            ' *',
            ' * 参考：テーブルのCREATE文。TODOを直すときに、列の定義を見比べるためのもの',
            ' *',
            ...array_map(fn ($line) => rtrim(' * '.$line), $this->createTableLines()),
            ' */',
            '',
            'namespace App\Http\Controllers\Admin;',
            '',
            ...array_map(fn ($use) => "use {$use};", $uses),
            '',
            "class {$this->model}Controller extends Controller",
            '{',
            ...$this->traitLines($hasUpload, $hasCsv),
            ...$this->listSettingLines(),
        ];

        if ($hasUpload) {
            array_push($lines, ...$this->uploadSettingLines());
        }
        if ($this->children !== []) {
            array_push($lines, ...$this->childSettingLines());
        }

        $lines = [
            ...$lines,
            '    // ---- このコーナーの項目の定義 ----',
            '',
            ...$this->rulesLines($hasUpload),
            '',
            ...$this->formValuesLines(),
            '',
            ...$this->defaultValuesLines(),
            '',
            ...$this->searchLines(),
        ];

        if ($hasCsv) {
            array_push($lines, '', ...$this->csvColumnsLines());
        }

        $lines = [...$lines, '', ...$this->saveLines(), '}'];

        return implode("\n", $lines)."\n";
    }

    /** テーブルのCREATE文の行。MySQLとMariaDBだけで取り出せる */
    private function createTableLines(): array
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return ['（このDBでは、CREATE文を取り出せません）'];
        }

        $row = (array) DB::selectOne('SHOW CREATE TABLE '.DB::connection()->getQueryGrammar()->wrapTable($this->table));

        // DBが返した文のまま書く。連番の次の値（AUTO_INCREMENT=）も、行の数の目安になるので残す。
        // コメントの終わりの印が入っていると、PHPのコメントが途中で切れるので崩す
        return explode("\n", str_replace('*/', '* /', $row['Create Table'].';'));
    }

    private function traitLines(bool $hasUpload, bool $hasCsv): array
    {
        $lines = [
            '    // ---- 共通処理（トレイト） ----',
            '',
            '    // 一覧・検索の共通処理。クラス側は必要な定数と searchRules() applyCustomSearch() を用意する。',
            '    use SearchableList;',
            '',
            '    // 入力→確認→保存の共通処理。クラス側は rules() formValues() saveValues() と、必要なら afterSave() などを用意する。',
            '    use FormFlow;',
            '',
        ];

        if ($hasUpload) {
            array_push(
                $lines,
                '    // アップロードの共通処理。クラス側は UPLOAD_FILES を用意し、アップロード先のルート（'."{$this->routeName}.ajaxUpload".'）を1本足す。',
                '    use AjaxFileUpload;',
                '',
            );
        }

        if ($this->children !== []) {
            array_push(
                $lines,
                '    // 子テーブルの値の組み立て・保存・削除。クラス側は CHILD_TABLES と、rules()の組の項目のルールを用意する。',
                '    use ChildTables;',
                '',
            );
        }

        if ($hasCsv) {
            array_push(
                $lines,
                '    // CSVダウンロードの共通処理。クラス側は csvColumns() と、入口の csv() を用意する。',
                '    use CsvDownload;',
                '',
            );
        }

        return $lines;
    }

    private function listSettingLines(): array
    {
        $freeWords = array_keys(array_filter(
            $this->columns,
            fn ($info, $name) => in_array($info['type'], STRING_TYPES, true) && preg_match(FREE_WORD_NAMES, $name) === 1,
            ARRAY_FILTER_USE_BOTH,
        ));

        $lines = [
            '    // ---- 一覧・検索（SearchableList）の設定 ----',
            '',
            '    /**',
            '     * 一覧画面のルート名。セッションキー名の識別子としても使用。',
            '     * 登録・更新・削除の後の戻り先（?back付きの一覧）にも使う。',
            '     */',
            "    private const INDEX_ROUTE = 'admin.{$this->routeName}.index';",
            '',
            '    /** フリーワード検索の検索対象とするカラムの一覧。使わなければ空配列 */',
            '    // TODO: 列の名前から推測',
            '    private const FREE_WORD_COLUMNS = ['.implode(', ', array_map(fn ($name) => "'{$name}'", $freeWords)).'];',
            '',
            '    /** 1ページに表示する件数。 */',
            '    private const PER_PAGE = '.PER_PAGE.';',
            '',
            '    /** 一覧の並び順の選択肢。先頭が既定 */',
            '    private const ORDER_OPTIONS = [',
        ];

        // 更新日の順。更新日の列が無ければ、登録の順（idの順）
        if (in_array('updated_at', $this->allColumns, true)) {
            $orders = [['updated', '更新日', 'updated_at']];
        } else {
            $orders = [['id', '登録', 'id']];
        }

        // 日付の列があれば、最初の1つの順も足す
        foreach ($this->columns as $name => $info) {
            if (isset(INPUT_DATE_FORMATS[$info['type']])) {
                $orders[] = [$name, $this->labelOf($name), $name];
                break;
            }
        }

        foreach ($orders as [$key, $label, $column]) {
            if ($label === $column) {
                $lines[] = '        // TODO: 選択肢の名前は列の名前のまま。COMMENTが無い';
            }

            foreach (['desc' => 'が新しい順', 'asc' => 'が古い順'] as $direction => $suffix) {
                $orderBy = $column === 'id'
                    ? "[['id', '{$direction}']]"
                    : "[['{$column}', '{$direction}'], ['id', '{$direction}']]";

                array_push(
                    $lines,
                    "        '{$key}_{$direction}' => [",
                    "            'label' => '{$label}{$suffix}',",
                    "            'orderBy' => {$orderBy},",
                    '        ],',
                );
            }
        }

        array_push($lines, '    ];', '');

        return $lines;
    }

    private function uploadSettingLines(): array
    {
        $lines = [
            '    // ---- アップロード（AjaxFileUpload）の設定 ----',
            '',
            '    /**',
            '     * AjaxFileUploadが要求する設定。欄の名前 => 種類・横幅・必須（書き方はUploadFieldDefinitions）。',
            '     * 「photos.*.image」のような名前は、子テーブル（CHILD_TABLES）の中の欄。',
            '     */',
            '    private const UPLOAD_FILES = [',
            '        // TODO: 画像か添付ファイルかは、列の名前から推測。横幅は、モデルの定数の仮の値',
        ];

        foreach ($this->uploadFields() as $key => [$name, $info, $widthConstant, $isImage]) {
            if ($isImage === null) {
                $lines[] = "        // TODO: {$name} は、名前から画像か添付ファイルかが決まらないので、画像として書いた";
            }

            $item = $isImage === false
                ? "['type' => UploadType::File"
                : "['type' => UploadType::Image, 'width' => {$this->model}::{$widthConstant}";
            $item .= $info['nullable'] ? ']' : ", 'required' => true]";

            $lines[] = "        '{$key}' => {$item},";
        }

        array_push($lines, '    ];', '');

        return $lines;
    }

    private function childSettingLines(): array
    {
        $lines = [
            '    // ---- 子テーブル（ChildTables）の設定 ----',
            '',
            '    /** 親と同じ画面で入力する子テーブル。ほかのコーナーで更新するテーブルには使わない。書き方はChildTableDefinitions */',
            '    private const CHILD_TABLES = [',
        ];

        foreach ($this->children as $group => $child) {
            $lines[] = "        // {$child['table']}（{$child['foreignKey']} の列から、{$this->table} の子と推測）。TODO: 行の数の上限は仮の値";
            $lines[] = "        '{$group}' => [";
            $lines[] = "            'relation' => '".Str::camel($group)."',";
            $lines[] = "            'fields' => [".implode(', ', array_map(fn ($name) => "'{$name}'", $child['fields'])).'],';

            // 並び順の列が無ければ、保存のたびに作り直して、idの順を画面の順にする
            if ($child['order'] !== null) {
                $lines[] = "            'order' => '{$child['order']}',";
            } else {
                $lines[] = "            'delete_insert' => true,   // 並び順の列が無いので、保存のたびに作り直す";
            }

            $lines[] = "            'max' => ".DEFAULT_CHILD_MAX.',';
            $lines[] = '        ],';
        }

        array_push($lines, '    ];', '');

        return $lines;
    }

    private function rulesLines(bool $hasUpload): array
    {
        $lines = [
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

            array_push($lines, ...$this->ruleLines($name, $info['rules'], '            '));
        }

        // 子テーブルの組の項目。「組の名前_列.*」に書く。ファイルの項目はUPLOAD_FILESに書いてある
        foreach ($this->children as $group => $child) {
            $childLines = [];

            foreach ($child['fields'] as $name) {
                $info = $child['draft']->columns[$name];
                if ($info['rules'] === null) {
                    continue;
                }

                foreach ($info['notes'] as $note) {
                    $childLines[] = "            // {$note}";
                }
                array_push($childLines, ...$this->ruleLines("{$group}_{$name}.*", $info['rules'], '            '));
            }

            // ファイルの項目だけの組は、ここに書くものが無い
            if ($childLines !== []) {
                $lines[] = "            // 子テーブル（{$child['table']}）の組の項目は「組の名前_列.*」に書く";
                array_push($lines, ...$childLines);
            }
        }

        // 多対多。選んだidの配列を受ける
        foreach ($this->pivots as $relation => $pivot) {
            $lines[] = "            // 多対多（{$pivot['pivot']}）。選んだ id の配列を受け、afterSave() で保存する";
            $lines[] = "            '{$pivot['input']}' => ['nullable', 'array'],";
            $lines[] = "            '{$pivot['input']}.*' => ['integer', Rule::exists('{$pivot['relatedTable']}', 'id')],";
        }

        // アップロードと子テーブルのルールは、トレイトが足す
        $append = ($hasUpload ? ' + $this->ajaxUploadRules()' : '').($this->children !== [] ? ' + $this->childTableRules()' : '');
        $lines[] = '        ]'.$append.';';
        $lines[] = '    }';

        return $lines;
    }

    private function formValuesLines(): array
    {
        $lines = [
            '    /** モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う） */',
            "    private function formValues({$this->model} {$this->variable}): array",
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

        // 多対多は、選んでいる相手のidの配列
        foreach ($this->pivots as $relation => $pivot) {
            $lines[] = "            '{$pivot['input']}' => {$this->variable}->{$relation}()->pluck('{$pivot['relatedTable']}.id')->all(),";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return $lines;
    }

    private function defaultValuesLines(): array
    {
        $lines = [
            '    /** 新規登録フォームの初期値。DBの既定値から書いている */',
            '    private function defaultValues(): array',
            '    {',
            '        return [',
        ];

        foreach ($this->savedColumns() as $name) {
            $default = $this->columns[$name]['default'];

            // CURRENT_TIMESTAMPのような式の既定値は、画面の初期値にしない
            if ($default === null || str_contains($default, '(') || stripos($default, 'current_') === 0) {
                continue;
            }

            $lines[] = "            '{$name}' => '".addslashes($default)."',";
        }

        foreach ($this->pivots as $pivot) {
            $lines[] = "            '{$pivot['input']}' => [],";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return $lines;
    }

    private function searchLines(): array
    {
        $lines = [
            '    // ---- 一覧・検索 ----',
            '',
            '    /**',
            '     * 検索対象項目の検証ルール（SearchableListが要求する）。',
            '     * integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件',
            '     */',
            '    private function searchRules(): array',
            '    {',
            '        // TODO: 検索に使う項目だけを残す',
            '        return [',
        ];

        foreach ($this->columns as $name => $info) {
            // 区分表のある列は、選んだ値の配列でIN()
            if ($info['code'] !== null) {
                $type = in_array($info['type'], NUMBER_TYPES, true) ? 'integer' : 'string';
                $lines[] = "            '{$name}' => ['nullable', 'array'],";
                $lines[] = "            '{$name}.*' => ['{$type}', Rule::in(code_keys('{$info['code']}'))],";

                continue;
            }

            // 真偽の列は、完全一致
            if ($info['type'] === 'boolean' && $info['rules'] !== null) {
                $lines[] = "            '{$name}' => ['nullable', 'boolean'],";
            }
        }

        array_push(
            $lines,
            '        ];',
            '    }',
            '',
            '    /**',
            '     * イレギュラーな検索条件の追加処理',
            '     * DB項目と単純に比較できないものは先にここでwhere条件を追加し、処理済み(true)を返す。',
            '     */',
            '    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool',
            '    {',
            '        return false;',
            '    }',
        );

        return $lines;
    }

    private function csvColumnsLines(): array
    {
        $lines = [
            '    // ---- CSVダウンロード ----',
            '',
            '    /** CSVに出す項目。見出し => 値の場所（書き方はNeobit\Core\Csv\CsvColumnSet参照）。見出しは列のCOMMENT */',
            '    private function csvColumns(): array',
            '    {',
            '        return [',
            "            'ID' => 'id',",
        ];

        foreach ($this->columns as $name => $info) {
            // パスワードと、入力させない列は出さない
            if (preg_match('/(^|_)password$/', $name) || ($info['rules'] === null && ! $info['upload'])) {
                continue;
            }

            $label = $this->labelOf($name);
            if ($info['comment'] === null) {
                $lines[] = '            // TODO: 見出しは列の名前のまま。COMMENTが無い';
            }

            // 値の場所。区分表は名前に、真偽は言葉に、日付は書式を付けて出す
            if ($info['code'] !== null) {
                $value = "['{$name}', code_table('{$info['code']}')]";
            } elseif ($info['type'] === 'boolean') {
                $value = "['{$name}', [1 => 'はい', 0 => 'いいえ']]";
            } elseif (isset(CSV_DATE_FORMATS[$info['type']])) {
                $value = "'{$name}|date:".CSV_DATE_FORMATS[$info['type']]."'";
            } else {
                $value = "'{$name}'";
            }

            $lines[] = "            '{$label}' => {$value},";

            // アップロードの欄は、元のファイル名も並べる
            if ($info['upload']) {
                $lines[] = "            '{$label}の表示名' => '{$name}_origin',";
            }
        }

        foreach ($this->pivots as $relation => $pivot) {
            $lines[] = "            // TODO: 多対多（{$pivot['pivot']}）は、['{$relation}.*.id', 選択肢, 'import' => '{$pivot['input']}'] の形で書く";
        }

        foreach ($this->children as $group => $child) {
            $lines[] = "            // TODO: 子テーブル（{$child['table']}）は、'見出し:*' => ['{$group}', 'group' => [...]] の形で横に並べられる";
        }

        // 取り込みのとき、ダウンロードした後に画面から変更された行を見分けるのに使う
        if (in_array('updated_at', $this->allColumns, true)) {
            $lines[] = "            '更新日時' => 'updated_at|date:Y/m/d H:i:s',";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return $lines;
    }

    /** saveValues()と、多対多があればafterSave()・beforeDelete() */
    private function saveLines(): array
    {
        $names = $this->savedColumns();

        $lines = [
            '    // ---- 保存・削除処理の補助（FormFlow） ----',
            '',
            '    /** 保存する値（列 => 値）。ここで返した列だけを保存する */',
            "    private function saveValues(array \$validated, {$this->model} {$this->variable}): array",
            '    {',
            '        // 入力値をそのまま保存する列。送られてこなかった列は null になる',
            '        $saveFieldNames = [',
            ...array_map(fn ($name) => "            '{$name}',", $names),
            '        ];',
            '',
        ];

        // 加工して保存する列が無ければ、取り出した値をそのまま返す
        $passwords = $this->passwordColumns();
        if (! isset($this->columns['staff_id']) && $passwords === []) {
            $lines[] = '        return $this->pickValues($validated, $saveFieldNames);';
            $lines[] = '    }';
        } else {
            $lines[] = '        $values = $this->pickValues($validated, $saveFieldNames);';

            if (isset($this->columns['staff_id'])) {
                $lines[] = '';
                $lines[] = '        // 最後に更新した操作者（スタッフ）';
                $lines[] = "        \$values['staff_id'] = Auth::guard('admin')->id();";
            }

            foreach ($passwords as $name) {
                $lines[] = '';
                $lines[] = '        // パスワードは入力があったときだけハッシュ変換して保存する。空欄なら今のまま変えない';
                $lines[] = "        if (! empty(\$validated['{$name}'])) {";
                $lines[] = "            \$values['{$name}'] = Hash::make(\$validated['{$name}']);";
                $lines[] = '        }';
            }

            $lines[] = '';
            $lines[] = '        return $values;';
            $lines[] = '    }';
        }

        if ($this->pivots === []) {
            return $lines;
        }

        // 多対多は、保存の直後に選んだidへそろえ、削除の直前に外す
        array_push(
            $lines,
            '',
            '    /** 保存の直後に呼ばれる。多対多の選んだ id をそろえる */',
            "    private function afterSave({$this->model} {$this->variable}, array \$validated): void",
            '    {',
        );
        foreach ($this->pivots as $relation => $pivot) {
            $lines[] = "        {$this->variable}->{$relation}()->sync(\$validated['{$pivot['input']}'] ?? []);";
        }
        array_push(
            $lines,
            '    }',
            '',
            '    /** 削除の直前に呼ばれる。多対多のつながりを外す */',
            "    private function beforeDelete({$this->model} {$this->variable}): void",
            '    {',
        );
        foreach ($this->pivots as $relation => $pivot) {
            $lines[] = "        {$this->variable}->{$relation}()->detach();";
        }
        $lines[] = '    }';

        return $lines;
    }

    // ---- 書き出し：モデル ----

    public function renderModel(): string
    {
        $softDeletes = in_array('deleted_at', $this->allColumns, true);

        $uses = ['Illuminate\Database\Eloquent\Model'];
        if ($softDeletes) {
            $uses[] = 'Illuminate\Database\Eloquent\SoftDeletes';
        }
        if ($this->belongsTo !== []) {
            $uses[] = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        }
        if ($this->children !== []) {
            $uses[] = 'Illuminate\Database\Eloquent\Relations\HasMany';
        }
        if ($this->pivots !== []) {
            $uses[] = 'Illuminate\Database\Eloquent\Relations\BelongsToMany';
        }
        sort($uses);

        $lines = [
            '<?php',
            '',
            '/*',
            " * {$this->table} のモデルの下書き（tools/table2draft.php が書き出したもの）。",
            ' * 下書きなので、モデルへ貼り付けて、TODOの付いた所を直す。',
            ' */',
            '',
            'namespace App\Models;',
            '',
            ...array_map(fn ($use) => "use {$use};", $uses),
            '',
            '/** TODO: 何のデータかを書く */',
            "class {$this->model} extends Model",
            '{',
        ];

        if ($softDeletes) {
            array_push($lines, '    // 論理削除。deleted_atの列がある', '    use SoftDeletes;', '');
        }

        // 画像の横幅。データ項目の仕様なので、モデルに持つ
        $widths = array_filter($this->uploadFields(), fn ($field) => $field[3] !== false);
        if ($widths !== []) {
            $lines[] = '    // TODO: 横幅は仮の値。データ項目の仕様に合わせて直す';
            foreach ($widths as $key => [$name, $info, $widthConstant]) {
                $lines[] = "    /** {$key} の画像の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。 */";
                $lines[] = "    public const {$widthConstant} = ".DEFAULT_IMAGE_WIDTH.';';
                $lines[] = '';
            }
        }

        array_push($lines, "    protected \$table = '{$this->table}';", '', '    protected $fillable = [');

        foreach ($this->columns as $name => $info) {
            $lines[] = "        '{$name}',";

            // アップロードの欄は、元のファイル名の列も入れる。列がまだ無ければ、TODOを付ける
            if ($info['upload']) {
                $lines[] = "        '{$name}_origin',".($info['origin'] ? '' : ' // TODO: 列が無い。マイグレーションで足す');
            }
        }

        array_push($lines, '    ];', '', '    protected $casts = [');

        foreach ($this->columns as $name => $info) {
            if (! isset(CAST_TYPES[$info['type']]) || preg_match('/(^|_)password$/', $name)) {
                continue;
            }

            $lines[] = "        '{$name}' => '".$this->fill(CAST_TYPES[$info['type']], $info)."',";
        }

        $lines[] = '    ];';

        $lines = [...$lines, ...$this->relationLines(), '}'];

        return implode("\n", $lines)."\n";
    }

    /** リレーション。belongsTo・子テーブルのhasMany・多対多のbelongsToMany */
    private function relationLines(): array
    {
        $lines = [];

        // 相手のモデルがまだ無ければ、作る旨のTODOを付ける
        $todoIfMissing = fn (string $model) => class_exists("App\\Models\\{$model}") ? [] : ["        // TODO: モデル {$model} が無ければ作る"];

        foreach ($this->belongsTo as $relation => [$column, $model]) {
            $lines = [
                ...$lines,
                '',
                "    /** {$column} の指す相手 */",
                "    public function {$relation}(): BelongsTo",
                '    {',
                ...$todoIfMissing($model),
                "        return \$this->belongsTo({$model}::class, '{$column}');",
                '    }',
            ];
        }

        foreach ($this->children as $group => $child) {
            $order = $child['order'] !== null ? "->orderBy('{$child['order']}')->orderBy('id')" : "->orderBy('id')";
            $note = $child['order'] !== null ? "並び順の列 {$child['order']} の順に並べる" : 'idの順に並べる';

            $lines = [
                ...$lines,
                '',
                "    /** 子テーブル（{$child['table']}）。{$note} */",
                '    public function '.Str::camel($group).'(): HasMany',
                '    {',
                ...$todoIfMissing($child['model']),
                "        return \$this->hasMany({$child['model']}::class, '{$child['foreignKey']}'){$order};",
                '    }',
            ];
        }

        foreach ($this->pivots as $relation => $pivot) {
            $lines = [
                ...$lines,
                '',
                "    /** 多対多（{$pivot['pivot']} を通して {$pivot['relatedTable']}） */",
                "    public function {$relation}(): BelongsToMany",
                '    {',
                ...$todoIfMissing($pivot['relatedModel']),
                "        return \$this->belongsToMany({$pivot['relatedModel']}::class, '{$pivot['pivot']}', '{$pivot['foreignKey']}', '{$pivot['relatedKey']}');",
                '    }',
            ];
        }

        return $lines;
    }

    // ---- 書き出し：入力欄の画面 ----

    public function renderFields(): string
    {
        $lines = [
            '{{--',
            "    {$this->table} の入力欄の下書き（tools/table2draft.php が書き出したもの）。",
            '    create・edit・confirm・show の4画面から、同じタグをそのまま呼び出す。',
            '    項目の名前は列のCOMMENT。COMMENTの無い列は列の名前のままなので、TODOの所を直す。',
            '',
            '    呼び出し側が用意する変数：',
            '    - $input     画面に表示する値の配列',
            '    - $model     対象の行。新規登録の画面ではnull。アップロード欄で、保存済みファイルのURLを求めるのに使う',
            "    - \$readonly  text/date系inputに付ける文字列（' readonly'または''）",
            "    - \$disabled  ラジオボタン・チェックボックス・セレクトに付ける文字列（' disabled'または''）",
            '    - $required  必須マークのHTML配列。閲覧専用画面では[]でよい',
            '--}}',
        ];

        // アップロードの欄があれば、アップロード先のURLを用意する
        if ($this->uploadFields() !== []) {
            array_push($lines, '@php', "    \$uploadUrl = route('admin.{$this->routeName}.ajaxUpload');", '@endphp');
        }

        foreach ($this->columns as $name => $info) {
            // 入力させない列は出さない
            if ($info['rules'] === null && ! $info['upload']) {
                continue;
            }

            $lines[] = '';
            if ($info['comment'] === null) {
                $lines[] = '{{-- TODO: 項目の名前 --}}';
            }
            array_push($lines, ...$this->fieldLines($name, $info));
        }

        foreach ($this->pivots as $relation => $pivot) {
            array_push($lines, '', ...$this->pivotFieldLines($relation, $pivot));
        }

        foreach ($this->children as $group => $child) {
            array_push($lines, '', ...$this->childFieldLines($group, $child));
        }

        return implode("\n", $lines)."\n";
    }

    /** 1つの列の入力欄。型と区分表で、入力欄の種類を決める */
    private function fieldLines(string $name, array $info): array
    {
        $label = $this->labelOf($name);
        $required = "{!! \$required['{$name}'] ?? '' !!}";
        $error = "    <div class=\"invalid-feedback\" data-item=\"{$name}\">{{ \$errors->first('{$name}') }}</div>";

        // アップロードの欄
        if ($info['upload']) {
            return [
                '<div class="mb-3">',
                "    <label class=\"form-label d-block\">{$label} {$required}</label>",
                "    @include('neobit::_ajax_upload_block', [",
                "        'model' => \$model,",
                "        'input' => \$input,",
                "        'field' => '{$name}',",
                "        'readonly' => \$readonly,",
                "        'uploadUrl' => \$uploadUrl,",
                '    ])',
                '</div>',
            ];
        }

        // 区分表のある列は、選択肢から選ぶ
        if ($info['code'] !== null) {
            return [
                '<div class="mb-3">',
                "    <label for=\"{$name}\" class=\"form-label\">{$label} {$required}</label>",
                "    <select id=\"{$name}\" name=\"{$name}\" class=\"form-select\"{{ \$disabled }}>",
                '        <option value="">選択してください</option>',
                "        {{ code_options('{$info['code']}', \$input['{$name}'] ?? null) }}",
                '    </select>',
                $error,
                '</div>',
            ];
        }

        // 真偽の列は、どちらかを必ず選ぶラジオボタン
        if ($info['type'] === 'boolean') {
            $lines = [
                '<div class="mb-3">',
                "    <div class=\"form-label d-block\">{$label} {$required}</div>",
                '    {{-- TODO: 選択肢の名前 --}}',
            ];
            foreach (['1' => 'はい', '0' => 'いいえ'] as $value => $text) {
                array_push(
                    $lines,
                    '    <div class="form-check form-check-inline">',
                    "        <input id=\"{$name}_{$value}\" type=\"radio\" name=\"{$name}\" value=\"{$value}\"",
                    '               class="form-check-input"',
                    "               @checked((\$input['{$name}'] ?? '') == '{$value}'){{ \$disabled }}>",
                    "        <label for=\"{$name}_{$value}\" class=\"form-check-label\">{$text}</label>",
                    '    </div>',
                );
            }
            array_push($lines, $error, '</div>');

            return $lines;
        }

        // 長い文の型は、複数行の入力欄
        if (in_array($info['type'], ['text', 'mediumtext', 'longtext'], true)) {
            $rows = preg_match(LONG_TEXT_NAMES, $name) ? 10 : 4;

            return [
                '<div class="mb-3">',
                "    <label for=\"{$name}\" class=\"form-label\">{$label} {$required}</label>",
                "    <textarea id=\"{$name}\" name=\"{$name}\"",
                "              class=\"form-control\" rows=\"{$rows}\"{{ \$readonly }}>{{ \$input['{$name}'] ?? '' }}</textarea>",
                $error,
                '</div>',
            ];
        }

        // そのほかは1行の入力欄。typeは、名前から推測したもの、型から決まるもの、textの順
        $type = $info['input'] ?? INPUT_TYPES[$info['type']] ?? 'text';
        $value = $type === 'password' ? '' : "\n           value=\"{{ \$input['{$name}'] ?? '' }}\"";

        return [
            '<div class="mb-3">',
            "    <label for=\"{$name}\" class=\"form-label\">{$label} {$required}</label>",
            "    <input id=\"{$name}\" type=\"{$type}\" name=\"{$name}\"",
            '           class="form-control"'.$value.'{{ $readonly }}>',
            $error,
            '</div>',
        ];
    }

    /** 多対多の入力欄。選択肢のチェックボックスを並べる */
    private function pivotFieldLines(string $relation, array $pivot): array
    {
        $input = $pivot['input'];
        $item = Str::camel($pivot['relatedModel']);

        return [
            '<div class="mb-3">',
            "    <div class=\"form-label d-block\">{$relation}（複数選択可） {!! \$required['{$input}'] ?? '' !!}</div>",
            "    {{-- TODO: 項目の名前と、選択肢の渡し方。コントローラーから \${$relation} として渡す想定。表示する列も直す --}}",
            "    @foreach (\${$relation} as \${$item})",
            '        <div class="form-check">',
            "            <input id=\"{$input}_{{ \${$item}->id }}\" type=\"checkbox\"",
            "                   name=\"{$input}[]\" value=\"{{ \${$item}->id }}\"",
            '                   class="form-check-input"',
            "                   @checked(in_array(\${$item}->id, \$input['{$input}'] ?? [])){{ \$disabled }}>",
            "            <label for=\"{$input}_{{ \${$item}->id }}\" class=\"form-check-label\">",
            "                {{ \${$item}->name }}",
            '            </label>',
            '        </div>',
            '    @endforeach',
            "    <div class=\"invalid-feedback\" data-item=\"{$input}\">{{ \$errors->first('{$input}') }}</div>",
            '</div>',
        ];
    }

    /** 子テーブルの入力欄。行の中身は、コーナーの部分ビューに書く */
    private function childFieldLines(string $group, array $child): array
    {
        // 組にファイルの項目があるときだけ、アップロード先のURLを渡す
        $hasUpload = array_filter($child['fields'], fn ($name) => $child['draft']->columns[$name]['upload']) !== [];

        return [
            "{{-- 子テーブル（{$child['table']}）。行の中身は、部分ビュー _{$group}_row に書く（組の項目：".implode('・', $child['fields']).'）。',
            '     TODO: 項目の名前と、行の部分ビューの名前 --}}',
            '<div class="mb-3">',
            "    <label class=\"form-label d-block\">{$group}</label>",
            "    @include('neobit::_repeatable_group', [",
            "        'group' => '{$group}',",
            "        'row' => 'admin.{$this->routeName}._{$group}_row',",
            "        'model' => \$model,",
            "        'input' => \$input,",
            "        'readonly' => \$readonly,",
            ...($hasUpload ? ["        'uploadUrl' => \$uploadUrl,"] : []),
            "        'addLabel' => '行を追加',",
            '    ])',
            "    <div class=\"invalid-feedback\" data-item=\"{$group}_id\">{{ \$errors->first('{$group}_id') }}</div>",
            '</div>',
        ];
    }
}
