<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * CSV取り込みの1行分。CsvImportが作り、コントローラーのvalidateCsvRows()や
 * afterCsvImportRow()などが受け取って処理する。
 */
final class CsvImportRow
{
    /** labelで見せる文字数の上限。これより長ければ縮めて「...」を付ける */
    private const LABEL_MAX_LENGTH = 10;

    /** 対象のレコード。更新ならDBのレコード。追加なら保存するまではnullで、保存した後は保存したレコード */
    public ?Model $record = null;

    /** どの行か見分けるための、設定のlabelColumnの列の値。空欄か指定なしならnull */
    public ?string $label = null;

    /** セルの値。見出し => 値で、前後の空白を除き空欄はnull */
    public array $cells = [];

    /** CSVから読み取った値。項目名 => 値で、csvColumns()の変換を戻したもの。CSVにある列の分だけ入る */
    public array $values = [];

    /** 検証した入力。DBの今の値か初期値に、CSVの値を重ねたもの */
    public array $input = [];

    /** 検証と整形を済ませた値。保存にはこれを使う */
    public array $validated = [];

    /** 追加のinsert、更新のupdate、変更の無いunchanged、処理だけのprocessのどれか */
    public string $action = 'insert';

    /** 変更の内容。[見出し, 変更前, 変更後]の一覧で、確認画面に出す */
    public array $changes = [];

    /** エラーと警告。[列の見出し, メッセージ]の一覧で、行全体のものは見出しがnull */
    public array $errors = [];

    public array $warnings = [];

    /** $lineはCSVの何行目か。見出しの行を1行目として数えるので、Excelの行番号と同じ */
    public function __construct(public readonly int $line)
    {
    }

    /**
     * 何行目か。labelがあれば「2行目（山田太郎）」のように後ろに添える。
     * 確認画面のエラーと警告、実行を中止したときのメッセージで使う。
     */
    public function lineLabel(): string
    {
        return $this->label === null ? "{$this->line}行目" : "{$this->line}行目（{$this->label}）";
    }

    /** labelを決める。長ければ、先頭のLABEL_MAX_LENGTH文字に「...」を付けて縮める */
    public function setLabel(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->label = null;

            return;
        }

        if (mb_strlen($value) > self::LABEL_MAX_LENGTH) {
            $this->label = mb_substr($value, 0, self::LABEL_MAX_LENGTH).'...';
        } else {
            $this->label = $value;
        }
    }

    /** 見出しで指定したセルの値。見出しの無いCSVでは、csvColumns()に書いた見出しで指定する */
    public function cell(string $heading): ?string
    {
        return $this->cells[$heading] ?? null;
    }

    /** この行をエラーにする。エラーが1件でもあると取り込みを実行できない。 */
    public function addError(string $message, ?string $column = null): void
    {
        $this->errors[] = [$column, $message];
    }

    /** この行に警告を付ける。確認画面に出るだけで、取り込みは実行できる。 */
    public function addWarning(string $message, ?string $column = null): void
    {
        $this->warnings[] = [$column, $message];
    }

    /** この行にエラーがあるか */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
