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
 *   'attach.*' => ['type' => UploadType::File, 'max' => 5],
 *
 * - type   必ず書く。UploadType::ImageかUploadType::File
 * - width  画像なら必ず書く。この横幅を超えたら縮小する。添付ファイルには書かない
 * - max    名前の末尾が「.*」の、いくつでも足せる欄だけに必ず書く。枠の数の上限
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

    /** UPLOAD_FILESの1つの欄に書けるキー */
    private const KEYS = ['type', 'width', 'max'];

    // コントローラーのクラスごとの、読み終えた定義
    /** @var array<string, list<array{field: string, kind: string, type: UploadType, width: ?int, max: ?int}>> */
    private static array $cache = [];

    /**
     * コントローラーの全部の欄の定義。
     * kindは、single（1つだけの欄）・repeatable（「.*」の欄）・wysiwyg（エディタの欄）
     *
     * @return list<array{field: string, kind: string, type: UploadType, width: ?int, max: ?int}>
     */
    public static function of(string $controllerClass): array
    {
        if (isset(self::$cache[$controllerClass])) {
            return self::$cache[$controllerClass];
        }

        $class = new ReflectionClass($controllerClass);
        $fields = [];

        foreach ((array) $class->getConstant(self::UPLOAD_FILES) as $rawField => $spec) {
            $isRepeatable = str_ends_with($rawField, '.*');
            $field = $isRepeatable ? substr($rawField, 0, -2) : $rawField;
            $fields[] = self::check($controllerClass, $field, $isRepeatable, $spec);
        }

        // WYSIWYG_IMAGE_FIELDSは省略できるので、あるときだけ読む
        if ($class->hasConstant(self::WYSIWYG_IMAGE_FIELDS)) {
            foreach ((array) $class->getConstant(self::WYSIWYG_IMAGE_FIELDS) as $field => $width) {
                $fields[] = ['field' => $field, 'kind' => 'wysiwyg', 'type' => UploadType::Image, 'width' => (int) $width, 'max' => null];
            }
        }

        return self::$cache[$controllerClass] = $fields;
    }

    /** 1つの欄の定義。$fieldは「.*」を付けない名前で、定義に無ければnull */
    public static function find(string $controllerClass, string $field): ?array
    {
        foreach (self::of($controllerClass) as $definition) {
            if ($definition['field'] === $field) {
                return $definition;
            }
        }

        return null;
    }

    /** UPLOAD_FILESの1つの欄の書き方を確かめて、定義の形にする */
    private static function check(string $controllerClass, string $field, bool $isRepeatable, mixed $spec): array
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

        if ($isRepeatable !== isset($spec['max'])) {
            throw new LogicException($isRepeatable
                ? "{$where}はいくつでも足せる欄なので、枠の数の上限を'max'に書いてください。"
                : "{$where}は1つだけの欄なので、'max'は書きません。");
        }

        return [
            'field' => $field,
            'kind' => $isRepeatable ? 'repeatable' : 'single',
            'type' => $spec['type'],
            'width' => $isImage ? (int) $spec['width'] : null,
            'max' => $isRepeatable ? (int) $spec['max'] : null,
        ];
    }
}
