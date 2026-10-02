<?php

namespace App\Support;

use RuntimeException;

/**
 * メールテンプレート（resources/mail-templates/配下）の読み込みに失敗した
 * ときの例外。App\Support\CodeTableExceptionと同じ考え方
 * （コード表の読み込み失敗用の専用例外）で、原因をひと目で切り分けられる
 * ようにするための専用クラス。
 */
class MailTemplateException extends RuntimeException
{
}
