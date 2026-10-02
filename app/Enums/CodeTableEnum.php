<?php

namespace App\Enums;

/**
 * コード表として使う列挙型の印。これを実装した値を持つ列挙型（BackedEnum）は、
 * code_table('<コード名>')で [値 => label()] の一覧として取り出せる
 * （コード名とクラス名の対応はApp\Support\CodeTable参照。例: 'staff_acl' → StaffAcl）。
 */
interface CodeTableEnum
{
    /**
     * 画面などに出す名前。
     */
    public function label(): string;
}
