<?php

namespace App\Support;

use RuntimeException;

/**
 * コード表の読み込みに失敗したときの例外。ファイルが無い、形が正しくないなど。
 * そのコード表を使う画面を開いたときにエラーになり、ほかの画面には影響しない。
 */
class CodeTableException extends RuntimeException
{
}
