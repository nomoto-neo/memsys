<?php

namespace App\Support;

use RuntimeException;

/**
 * CSV取り込みの実行を中止したときの例外（DBの制約違反など）。メッセージは利用者向けで、
 * App\Support\CsvImportが取り込み画面に表示する。
 */
class CsvImportException extends RuntimeException
{
}
