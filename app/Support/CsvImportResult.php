<?php

namespace App\Support;

/**
 * CSV取り込みの検証結果（App\Support\CsvImportが作る）。確認画面の表示と、
 * コントローラーのフック（validateCsvRows()・processCsvRows()・afterCsvImport()）で使う。
 *
 * - rows            全行（App\Support\CsvImportRow）。
 * - headings        列の見出し（見出し無しのCSVでは、csvColumns()に書いた見出し）。
 * - errors          ファイル・見出しのエラー（文字コードが判別できない、id列が無いなど）。
 * - warnings        列の警告（無視した列、横展開の一部の列が無いなど）。
 * - filename・encoding  アップロードされたファイル名と、読み込んだ文字コード（'UTF-8'・'Shift_JIS'）。
 */
final class CsvImportResult
{
    public array $rows = [];

    public array $headings = [];

    public array $errors = [];

    public array $warnings = [];

    public function __construct(
        public readonly string $filename,
        public ?string $encoding = null,
    ) {
    }

    /**
     * エラーが1件でもあるか（ファイル・見出し・行のどれか）。あれば取り込みを実行できない。
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [] || $this->errorRows() !== [];
    }

    public function errorRows(): array
    {
        return array_values(array_filter($this->rows, fn (CsvImportRow $row) => $row->hasErrors()));
    }

    public function warningRows(): array
    {
        return array_values(array_filter($this->rows, fn (CsvImportRow $row) => $row->warnings !== []));
    }

    /**
     * エラーの無い行のうち、指定した動作（'insert'・'update'・'unchanged'・'process'）の行。
     */
    public function rowsOf(string $action): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (CsvImportRow $row) => ! $row->hasErrors() && $row->action === $action,
        ));
    }

    public function count(string $action): int
    {
        return count($this->rowsOf($action));
    }
}
