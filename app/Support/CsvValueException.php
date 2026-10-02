<?php

namespace App\Support;

use RuntimeException;

/**
 * CSV取り込みで、セルの値を読み取れなかったとき（日付・数値の形が正しくないなど）の例外。
 * App\Support\CsvColumnSetの中だけで使い、その行のエラーに置き換える。
 */
class CsvValueException extends RuntimeException
{
}
