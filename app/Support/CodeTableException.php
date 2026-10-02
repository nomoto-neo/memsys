<?php

namespace App\Support;

use RuntimeException;

/**
 * コード表（code/<コード名>.csv）の読み込みに失敗したときの例外。
 *
 * ファイルが無い・開けない・行の形式が不正、といった問題をこの例外として
 * 投げることで、そのコード表を使っている画面を開いた瞬間にエラーとして
 * 表面化させる。config/のPHPファイルが構文エラーで壊れたときのように
 * 無関係な画面まで含めてサイト全体が止まるわけではなく、あくまで
 * そのコード表を実際に使っているリクエストだけがエラーになる。
 */
class CodeTableException extends RuntimeException
{
}
