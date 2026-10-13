<?php

namespace Neobit\Core\Csv;

use RuntimeException;

/**
 * CSV取り込みで、セルの値を読み取れなかったときの例外。日付や数値の形が正しくないなど。
 * Neobit\Core\Csv\CsvColumnSetの中だけで使い、その行のエラーに置き換える。
 */
class CsvValueException extends RuntimeException
{
}
