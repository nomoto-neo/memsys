<?php

namespace App\Support;

use RuntimeException;

/**
 * CSV取り込みで、セルの値を読み取れなかったときの例外。日付や数値の形が正しくないなど。
 * App\Support\CsvColumnSetの中だけで使い、その行のエラーに置き換える。
 */
class CsvValueException extends RuntimeException
{
}
