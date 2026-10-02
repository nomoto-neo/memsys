<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * CSV取り込みの1行分（App\Support\CsvImportが作る）。
 * コントローラーのフック（validateCsvRows()・afterCsvImportRow()・processCsvRows()など）は、
 * これを受け取って処理する。
 *
 * - line       CSVの何行目か（見出しの行を1行目として数える。Excelの行番号と同じ）。
 * - label      どの行のデータか見分けるための、その行のセルの値（CsvImportSettingsのlabelColumn）。
 *              長いものは先頭の10文字に「...」を付けて縮めてある。空欄・指定なしならnull。
 * - record     対象のレコード。更新ならDBのレコード、追加なら保存するまではnullで、
 *              保存した後（afterCsvImportRow()）は保存したレコード。
 * - cells      CSVのセルの値。見出し => 値（前後の空白を取り除いたもの。空欄はnull）。
 * - values     CSVから読み取った値。項目名 => 値（csvColumns()の変換を戻したもの。CSVにある列の分だけ）。
 * - input      検証した入力（DBの今の値やdefaultInput()に、CSVの値を重ねたもの）。
 * - validated  検証済み・整形済み（prepareInput()）の値。保存にはこれを使う。
 * - action     'insert'（追加）・'update'（更新）・'unchanged'（変更なし）・'process'（処理だけのモード）。
 * - changes    変更の内容。[見出し, 変更前, 変更後] の一覧（確認画面で表示する）。
 * - errors・warnings  [列の見出し（行全体ならnull）, メッセージ] の一覧。
 */
final class CsvImportRow
{
    // labelで見せる文字数の上限（これより長ければ縮めて「...」を付ける）
    private const LABEL_MAX_LENGTH = 10;

    public ?Model $record = null;

    public ?string $label = null;

    public array $cells = [];

    public array $values = [];

    public array $input = [];

    public array $validated = [];

    public string $action = 'insert';

    public array $changes = [];

    public array $errors = [];

    public array $warnings = [];

    public function __construct(public readonly int $line)
    {
    }

    /**
     * 何行目か。labelがあれば後ろに添える（例:「2行目（山田太郎）」）。
     * 確認画面のエラー・警告と、実行を中止したときのメッセージで使う。
     */
    public function lineLabel(): string
    {
        return $this->label === null ? "{$this->line}行目" : "{$this->line}行目（{$this->label}）";
    }

    /**
     * labelを決める。長ければ先頭のLABEL_MAX_LENGTH文字に「...」を付けて縮める。
     */
    public function setLabel(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->label = null;

            return;
        }

        $this->label = mb_strlen($value) > self::LABEL_MAX_LENGTH
            ? mb_substr($value, 0, self::LABEL_MAX_LENGTH).'...'
            : $value;
    }

    /**
     * セルの値（見出しで指定。見出し無しのCSVでは、csvColumns()に書いた見出し）。
     */
    public function cell(string $heading): ?string
    {
        return $this->cells[$heading] ?? null;
    }

    /**
     * この行をエラーにする（エラーが1件でもあると、取り込みを実行できない）。
     */
    public function addError(string $message, ?string $column = null): void
    {
        $this->errors[] = [$column, $message];
    }

    /**
     * この行に警告を付ける（確認画面に出るだけで、取り込みは実行できる）。
     */
    public function addWarning(string $message, ?string $column = null): void
    {
        $this->warnings[] = [$column, $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
