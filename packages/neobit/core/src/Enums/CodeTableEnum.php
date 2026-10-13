<?php

namespace Neobit\Core\Enums;

/**
 * コード表として使う列挙型の印。これを実装した列挙型は、code_table('<コード名>')で
 * 値と名前の一覧として取り出せる。コード名とクラス名の対応は、Neobit\Core\Code\CodeTableを参照。
 */
interface CodeTableEnum
{
    /** 画面などに出す名前。 */
    public function label(): string;
}
