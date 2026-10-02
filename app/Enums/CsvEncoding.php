<?php

namespace App\Enums;

/**
 * CSVの文字コード（App\Support\CsvDownload::downloadCsv()などで指定する）。
 */
enum CsvEncoding
{
    // UTF-8（先頭にBOMを付ける）。Excelでも文字化けせずに開け、絵文字や
    // 環境依存の漢字もそのまま出せる。
    case Utf8Bom;

    // Shift_JIS（Windowsの拡張文字を含むCP932）。Shift_JISしか読めない他システムとの
    // 連携用。CP932に無い文字（絵文字・一部の漢字など）は「?」になる。
    case Sjis;
}
