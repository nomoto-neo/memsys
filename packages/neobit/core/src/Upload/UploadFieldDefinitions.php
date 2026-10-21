<?php

namespace Neobit\Core\Upload;

use LogicException;
use ReflectionClass;

/**
 * コントローラーのアップロードの欄の定義（UPLOAD_FILES・WYSIWYG_IMAGE_FIELDS）を読んで、確かめる。
 * AjaxFileUploadと、画面の部品が使うヘルパーのupload_field()の両方がここを通る。
 * 定数の書き方の決まりを、1か所にまとめるため。
 *
 * ■ UPLOAD_FILES
 *   'list_image' => ['type' => UploadType::Image, 'width' => News::LIST_IMAGE_WIDTH],
 *   'attach.*.file' => ['type' => UploadType::File],
 *
 * - type   必ず書く。UploadType::ImageかUploadType::File
 * - width  画像なら必ず書く。この横幅を超えたら縮小する。添付ファイルには書かない
 * - required  trueなら必須。新しいファイルか、消していない今のファイルが要る（Neobit\Core\Rules\UploadRequiredRule）。
 *          組の中の欄では、空でない行のどれにも要る。省略するとfalse
 *
 * 1つのレコードに1つのファイルの欄は、名前をそのまま書く。ファイル名はその列、表示名は「名前_origin」の列に入る。
 * いくつでも足せるファイルは、子テーブル（Neobit\Core\Crud\ChildTables）の中の欄にして、
 * 'attach.*.file'のように「組の名前.*.子テーブルの列」で書く。画面から送る名前はattach_fileになり、
 * 行の数の上限は組の'max'で決める。ファイルの項目が1つだけの組の欄では、ファイルをまとめて選べる（multiple）。
 *
 * ■ WYSIWYG_IMAGE_FIELDS
 *   'body' => Page::BODY_IMAGE_WIDTH,
 * エディタの欄に入れる画像の横幅。エディタに入るのは画像だけなので、種類は書かない。省略できる。
 *
 * 書き方の誤りは、最初に読んだときに例外にする。どの欄の何が誤りかを、文言に書く。
 */
final class UploadFieldDefinitions
{
    /** 定義の定数の名前 */
    private const UPLOAD_FILES = 'UPLOAD_FILES';

    private const WYSIWYG_IMAGE_FIELDS = 'WYSIWYG_IMAGE_FIELDS';

    private const CHILD_TABLES = 'CHILD_TABLES';

    /** UPLOAD_FILESの1つの欄に書けるキー */
    private const KEYS = ['type', 'width', 'required'];

    /** 子テーブルの中のファイルの欄の書き方。1は組の名前、2は子テーブルの列の名前 */
    private const GROUP_FIELD_PATTERN = '/^([a-z][a-z0-9_]*)\.\*\.([a-z][a-z0-9_]*)$/';

    // コントローラーのクラスごとの、読み終えた定義
    /** @var array<string, list<array{field: string, kind: string, type: UploadType, width: ?int, required: bool, group: ?string, column: ?string, multiple: bool}>> */
    private static array $cache = [];

    /**
     * コントローラーの全部の欄の定義。
     * kindは、single（1つだけの欄）・wysiwyg（エディタの欄）・group（子テーブルの中のファイルの欄。
     * groupとcolumnに組の名前と子テーブルの列の名前が入る）。requiredは必須の欄か、multipleはファイルをまとめて選べる欄か。
     *
     * @return list<array{field: string, kind: string, type: UploadType, width: ?int, required: bool, group: ?string, column: ?string, multiple: bool}>
     */
    public static function of(string $controllerClass): array
    {
        if (isset(self::$cache[$controllerClass])) {
            return self::$cache[$controllerClass];
        }

        $class = new ReflectionClass($controllerClass);
        $fields = [];

        // UPLOAD_FILESが無いコントローラーでも読まれる（子テーブルの定義を読むとき）ので、無ければ空にする
        $uploadFiles = $class->hasConstant(self::UPLOAD_FILES) ? (array) $class->getConstant(self::UPLOAD_FILES) : [];

        // 組ごとの、ファイルの欄の数。1つだけの組の欄では、ファイルをまとめて選べる
        $groupFileCounts = [];
        foreach (array_keys($uploadFiles) as $rawField) {
            if (preg_match(self::GROUP_FIELD_PATTERN, (string) $rawField, $m)) {
                $groupFileCounts[$m[1]] = ($groupFileCounts[$m[1]] ?? 0) + 1;
            }
        }

        foreach ($uploadFiles as $rawField => $spec) {
            // 子テーブルの中のファイルの欄。'attach.*.file'は、画面から送る名前がattach_fileになる
            if (preg_match(self::GROUP_FIELD_PATTERN, (string) $rawField, $m)) {
                $fields[] = self::checkGroupField($class, $m[1], $m[2], $spec, $groupFileCounts[$m[1]] === 1);

                continue;
            }

            // いくつでも足せる欄の古い書き方。子テーブルの書き方を案内する
            if (str_ends_with((string) $rawField, '.*')) {
                throw new LogicException("{$controllerClass}のUPLOAD_FILESの「{$rawField}」は書けません。いくつでも足せるファイルは、"
                    .'CHILD_TABLESに組を書き、UPLOAD_FILESに「組の名前.*.列」で書いてください。');
            }

            $fields[] = self::check($controllerClass, (string) $rawField, $spec) + ['kind' => 'single', 'group' => null, 'column' => null, 'multiple' => false];
        }

        // WYSIWYG_IMAGE_FIELDSは省略できるので、あるときだけ読む
        if ($class->hasConstant(self::WYSIWYG_IMAGE_FIELDS)) {
            foreach ((array) $class->getConstant(self::WYSIWYG_IMAGE_FIELDS) as $field => $width) {
                $fields[] = ['field' => $field, 'kind' => 'wysiwyg', 'type' => UploadType::Image, 'width' => (int) $width, 'required' => false, 'group' => null, 'column' => null, 'multiple' => false];
            }
        }

        return self::$cache[$controllerClass] = $fields;
    }

    /** 1つの欄の定義。$fieldは画面から送る名前（組の中の欄はattach_file）で、定義に無ければnull */
    public static function find(string $controllerClass, string $field): ?array
    {
        foreach (self::of($controllerClass) as $definition) {
            if ($definition['field'] === $field) {
                return $definition;
            }
        }

        return null;
    }

    /** UPLOAD_FILESの1つの欄の種類・横幅・必須を確かめて、field・type・width・requiredを返す */
    private static function check(string $controllerClass, string $field, mixed $spec): array
    {
        $where = "{$controllerClass}のUPLOAD_FILESの「{$field}」";

        if (! is_array($spec) || ! (($spec['type'] ?? null) instanceof UploadType)) {
            throw new LogicException("{$where}に、'type' => UploadType::ImageかUploadType::Fileを書いてください。");
        }

        $unknown = array_diff(array_keys($spec), self::KEYS);
        if ($unknown !== []) {
            throw new LogicException("{$where}に、使えないキー（".implode('・', $unknown).'）があります。');
        }

        $isImage = $spec['type'] === UploadType::Image;
        if ($isImage !== isset($spec['width'])) {
            throw new LogicException($isImage
                ? "{$where}は画像なので、縮小する横幅を'width'に書いてください。"
                : "{$where}は添付ファイルなので、'width'は書きません。");
        }

        $required = $spec['required'] ?? false;
        if (! is_bool($required)) {
            throw new LogicException("{$where}の'required'は、trueかfalseで書いてください。");
        }

        return [
            'field' => $field,
            'type' => $spec['type'],
            'width' => $isImage ? (int) $spec['width'] : null,
            'required' => $required,
        ];
    }

    /**
     * UPLOAD_FILESの、子テーブルの中のファイルの欄を確かめて、定義の形にする。
     * 組がCHILD_TABLESにあり、列がその組のfieldsにあること。行の数は組の'max'で決めるので、'max'は書かない。
     * 画面から送る名前（field）は「組の名前_列」で、ファイル名はその列、表示名は「列_origin」の列に入る。
     */
    private static function checkGroupField(ReflectionClass $class, string $group, string $column, mixed $spec, bool $multiple): array
    {
        $field = "{$group}_{$column}";
        $where = "{$class->getName()}のUPLOAD_FILESの「{$group}.*.{$column}」";

        $groups = $class->hasConstant(self::CHILD_TABLES) ? (array) $class->getConstant(self::CHILD_TABLES) : [];
        if (! in_array($column, (array) ($groups[$group]['fields'] ?? []), true)) {
            throw new LogicException("{$where}の組と項目を、CHILD_TABLESの「{$group}」の'fields'にも書いてください。");
        }

        if (is_array($spec) && array_key_exists('max', $spec)) {
            throw new LogicException("{$where}は子テーブルの中の欄なので、'max'は書きません。行の数の上限は組の'max'に書きます。");
        }

        return self::check($class->getName(), $field, $spec) + ['kind' => 'group', 'group' => $group, 'column' => $column, 'multiple' => $multiple];
    }
}
