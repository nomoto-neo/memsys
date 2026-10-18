<?php

namespace Neobit\Core\Upload;

/**
 * アップロードの欄の種類。コントローラーのUPLOAD_FILESの'type'に書く（AjaxFileUpload）。
 * 受け付ける拡張子は、サイト全体の方針として種類ごとに設定で決める（config/upload.php）。
 */
enum UploadType
{
    /** 画像。'width'の横幅を超えたら縮小する */
    case Image;

    /** 添付ファイル。縮小しない */
    case File;

    /** 受け付ける拡張子。config/upload.phpの種類ごとの一覧 */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Image => (array) config('upload.image_types'),
            self::File => (array) config('upload.attach_types'),
        };
    }
}
