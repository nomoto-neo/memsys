<?php

namespace App\Support;

/**
 * CSV取り込みの検証結果。CsvImportが作り、確認画面とコントローラーのvalidateCsvRows()などで使う。
 */
final class CsvImportResult
{
    // 全部の行。CsvImportRowの一覧
    public array $rows = [];

    // 列の見出し。見出しの無いCSVでは、csvColumns()に書いた見出し
    public array $headings = [];

    // ファイルや見出しのエラー。文字コードが分からない、id列が無いなど
    public array $errors = [];

    // 列の警告。読まなかった列、横展開の列が一部足りないなど
    public array $warnings = [];

    // アップロードされたファイル名と、読み込んだ文字コード
    public function __construct(
        public readonly string $filename,
        public ?string $encoding = null,
    ) {
    }

    // ファイル・見出し・行のどれかにエラーがあるか。あれば取り込みは実行できない
    public function hasErrors(): bool
    {
        return $this->errors !== [] || $this->errorRows() !== [];
    }

    // エラーのある行
    public function errorRows(): array
    {
        return array_values(array_filter($this->rows, fn (CsvImportRow $row) => $row->hasErrors()));
    }

    // 警告のある行
    public function warningRows(): array
    {
        return array_values(array_filter($this->rows, fn (CsvImportRow $row) => $row->warnings !== []));
    }

    // エラーの無い行のうち、'insert'・'update'・'unchanged'・'process'のどれかの動作の行
    public function rowsOf(string $action): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (CsvImportRow $row) => ! $row->hasErrors() && $row->action === $action,
        ));
    }

    // rowsOf()の件数
    public function count(string $action): int
    {
        return count($this->rowsOf($action));
    }
}
