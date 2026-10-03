<?php

namespace App\Support;

use RuntimeException;

/**
 * CSV取り込みの実行を、DBの制約違反などで中止したときの例外。
 * メッセージは利用者向けで、取り込み画面に表示する。
 */
class CsvImportException extends RuntimeException
{
}
