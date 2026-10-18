<?php

namespace Neobit\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * いくつでも足せるアップロードの欄（AjaxFileUploadの「.*」の欄）で、ファイルのある枠の数が上限を
 * 超えていないかを確かめるルール。上限はコントローラーのUPLOAD_FILESの'max'。
 *
 *   'attach' => ['array', new MaxUploadCountRule('attach', 5)]
 *
 * 枠は、空の枠や「削除する」を押した枠も配列に残るので、要素数ではなく、ファイルのある枠を数える。
 * ファイルのある枠は、今のファイル名か一時ファイルの名前があり、削除の印（{field}_del）が立っていない枠。
 * ほかの項目を見るには送信データ全体が要るので、DataAwareRuleも実装している。
 */
class MaxUploadCountRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    /** $fieldは「.*」を付けない欄の名前、$maxは枠の数の上限 */
    public function __construct(private string $field, private int $max)
    {
    }

    /** 検証の前に、Laravelが送信データ全体を渡すために呼ぶ */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /** 検証。ファイルのある枠が上限より多ければ$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $names = (array) ($this->data[$this->field] ?? []);
        $tmps = (array) ($this->data[$this->field.'_tmp'] ?? []);
        $dels = (array) ($this->data[$this->field.'_del'] ?? []);

        $count = 0;
        foreach (array_keys($names + $tmps) as $index) {
            $hasFile = ($names[$index] ?? '') !== '' || ($tmps[$index] ?? '') !== '';

            if ($hasFile && (string) ($dels[$index] ?? '') !== '1') {
                $count++;
            }
        }

        if ($count > $this->max) {
            $fail("ファイルは{$this->max}件までにしてください。");
        }
    }
}
