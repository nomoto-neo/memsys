<?php

namespace Neobit\Core\Crud;

use LogicException;
use Neobit\Core\Upload\AjaxFileUpload;
use Neobit\Core\Upload\UploadFieldDefinitions;
use ReflectionClass;

/**
 * コントローラーの子テーブルの定義（CHILD_TABLES）を読んで、確かめる。
 * ChildTablesトレイトと、画面の部品（_repeatable_group）の両方がここを通る。
 *
 *   private const CHILD_TABLES = [
 *       'photos' => [
 *           'relation' => 'photos',
 *           'fields' => ['image', 'caption'],
 *           'order' => 'seq',
 *           'max' => 10,
 *       ],
 *   ];
 *
 * - relation  必ず書く。子テーブルへのHasManyのリレーションの名前。1:多のテーブルだけが書ける
 *             （多対多は、コントローラーのafterSave()でsync()する）
 * - fields    必ず書く。組の項目で、子テーブルの列の名前。画面から送る名前は「組の名前_項目」になる
 *             （photos_caption[]）。ファイルの項目は、UPLOAD_FILESにも'photos.*.image'のように書く
 * - order     子テーブルの並び順の列。書けば、画面の並びの順の番号を入れる。省略できる
 * - delete_insert  trueなら、保存のたびに子テーブルの行を全部消し、画面の順に入れ直す。行のidは保たない。
 *             idの順が画面の順になるので、並び順の列が要らない。省略するとfalse（行のidを保って揃える）
 * - max       必ず書く。行の数の上限
 * - min       行の数の下限。省略すると0
 *
 * 書き方の誤りは、最初に読んだときに例外にする。どの組の何が誤りかを、文言に書く。
 */
final class ChildTableDefinitions
{
    /** 定義の定数の名前 */
    private const CONSTANT = 'CHILD_TABLES';

    /** 1つの組に書けるキー */
    private const KEYS = ['relation', 'fields', 'order', 'delete_insert', 'max', 'min'];

    /** 組の名前と項目の名前に使える形。画面から送る名前とDBの列の名前になるので、英小文字・数字・「_」だけ */
    private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    // コントローラーのクラスごとの、読み終えた定義
    /** @var array<string, array<string, array{group: string, relation: string, texts: list<string>, files: list<string>, order: ?string, delete_insert: bool, max: int, min: int}>> */
    private static array $cache = [];

    /**
     * コントローラーの全部の組の定義。組の名前 => 定義。
     * textsはファイルではない項目、filesはファイルの項目（UPLOAD_FILESに書いたもの）。
     * CHILD_TABLESが無いコントローラーでは空の配列。
     *
     * @return array<string, array{group: string, relation: string, texts: list<string>, files: list<string>, order: ?string, delete_insert: bool, max: int, min: int}>
     */
    public static function of(string $controllerClass): array
    {
        if (isset(self::$cache[$controllerClass])) {
            return self::$cache[$controllerClass];
        }

        $class = new ReflectionClass($controllerClass);
        $groups = [];

        if ($class->hasConstant(self::CONSTANT)) {
            // ファイルの項目は、UPLOAD_FILESの組の欄から決める
            $fileColumns = [];
            foreach (UploadFieldDefinitions::of($controllerClass) as $upload) {
                if ($upload['kind'] === 'group') {
                    $fileColumns[$upload['group']][] = $upload['column'];
                }
            }

            foreach ((array) $class->getConstant(self::CONSTANT) as $group => $spec) {
                $groups[$group] = self::check($controllerClass, (string) $group, $spec, $fileColumns[$group] ?? []);
            }
        }

        return self::$cache[$controllerClass] = $groups;
    }

    /** 1つの組の定義。定義に無ければnull */
    public static function find(string $controllerClass, string $group): ?array
    {
        return self::of($controllerClass)[$group] ?? null;
    }

    /**
     * 組の画面から送る名前の一覧。行の並びをそろえる配列の全部で、id・ファイルではない項目・
     * ファイルの項目の4本（名前・_tmp・_origin・_del）の順。
     *
     * @return list<string>
     */
    public static function inputKeys(array $definition): array
    {
        $group = $definition['group'];
        $keys = ["{$group}_id"];

        foreach ($definition['texts'] as $field) {
            $keys[] = "{$group}_{$field}";
        }

        foreach ($definition['files'] as $field) {
            $base = "{$group}_{$field}";
            array_push($keys, $base, "{$base}_tmp", "{$base}_origin", "{$base}_del");
        }

        return $keys;
    }

    /** 組が送られたことを示すhiddenの名前。行が1つも無いときも、組を空にすることを伝えるため */
    public static function markerKey(string $group): string
    {
        return "{$group}_group";
    }

    /** CHILD_TABLESの1つの組の書き方を確かめて、定義の形にする */
    private static function check(string $controllerClass, string $group, mixed $spec, array $fileColumns): array
    {
        $where = "{$controllerClass}のCHILD_TABLESの「{$group}」";

        if (! preg_match(self::NAME_PATTERN, $group) || ! is_array($spec)) {
            throw new LogicException("{$where}は、英小文字・数字・「_」の名前に、定義の配列を書いてください。");
        }

        $unknown = array_diff(array_keys($spec), self::KEYS);
        if ($unknown !== []) {
            throw new LogicException("{$where}に、使えないキー（".implode('・', $unknown).'）があります。');
        }

        if (! is_string($spec['relation'] ?? null) || $spec['relation'] === '') {
            throw new LogicException("{$where}に、子テーブルへのリレーションの名前を'relation'に書いてください。");
        }

        $fields = $spec['fields'] ?? null;
        if (! is_array($fields) || $fields === [] || array_filter($fields, fn ($f) => ! is_string($f) || ! preg_match(self::NAME_PATTERN, $f))) {
            throw new LogicException("{$where}に、組の項目（子テーブルの列の名前）を'fields'の配列に書いてください。");
        }

        if (! is_int($spec['max'] ?? null) || $spec['max'] < 1) {
            throw new LogicException("{$where}に、行の数の上限を'max'に1以上の数で書いてください。");
        }

        $min = $spec['min'] ?? 0;
        if (! is_int($min) || $min < 0 || $min > $spec['max']) {
            throw new LogicException("{$where}の'min'は、0から'max'までの数で書いてください。");
        }

        $order = $spec['order'] ?? null;
        if ($order !== null && (! is_string($order) || in_array($order, $fields, true))) {
            throw new LogicException("{$where}の'order'には、組の項目ではない並び順の列の名前を書いてください。");
        }

        $deleteInsert = $spec['delete_insert'] ?? false;
        if (! is_bool($deleteInsert)) {
            throw new LogicException("{$where}の'delete_insert'は、trueかfalseで書いてください。");
        }

        // ファイルの項目はアップロードの部品が受け取って移すので、コントローラーにAjaxFileUploadが要る
        if ($fileColumns !== [] && ! in_array(AjaxFileUpload::class, class_uses_recursive($controllerClass), true)) {
            throw new LogicException("{$where}にはファイルの項目があるので、コントローラーでAjaxFileUploadを使ってください。");
        }

        return [
            'group' => $group,
            'relation' => $spec['relation'],
            'texts' => array_values(array_diff($fields, $fileColumns)),
            'files' => array_values(array_intersect($fields, $fileColumns)),
            'order' => $order,
            'delete_insert' => $deleteInsert,
            'max' => $spec['max'],
            'min' => $min,
        ];
    }
}
