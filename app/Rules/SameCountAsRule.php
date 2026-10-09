<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

/**
 * 配列の項目が、ほかの項目と同じ要素数かを確かめるルール。添付ファイルの4本の配列が
 * そろっているかの確認に使う。
 *
 *   'attach_tmp' => ['array', new SameCountAsRule(['attach', 'attach_origin', 'attach_del'])]
 *
 * ほかの項目と比べるには送信データ全体が要るので、DataAwareRuleも実装している。
 * 比べる相手が送られてこなかったときは、要素数0として扱う。
 */
class SameCountAsRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    /** $othersは、要素数を比べる相手の項目名 */
    public function __construct(private array $others)
    {
    }

    /** 検証の前に、Laravelが送信データ全体を渡すために呼ぶ */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /** 検証。どれか1つでも要素数が違えば$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = is_array($value) ? count($value) : 0;

        foreach ($this->others as $other) {
            $otherValue = $this->data[$other] ?? [];
            $otherCount = is_array($otherValue) ? count($otherValue) : 0;

            if ($otherCount !== $count) {
                // 画面から普通に操作していれば起きないので、気付けるようにログに残す
                Log::warning('SameCountAsRule: 配列の要素数が一致しません。', [
                    'attribute' => $attribute,
                    'count' => $count,
                    'other' => $other,
                    'other_count' => $otherCount,
                ]);

                $fail('入力内容が壊れています。もう一度やり直してください。');

                return;
            }
        }
    }
}
