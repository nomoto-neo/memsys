<?php

namespace App\Support;

use RuntimeException;

/**
 * メールのテンプレートの読み込みに失敗したときの例外。
 * 原因がテンプレートだとひと目で分かるよう、専用の例外にしている。
 */
class MailTemplateException extends RuntimeException
{
}
