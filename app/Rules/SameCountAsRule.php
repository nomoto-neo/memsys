<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

// 配列の項目が、指定した他の項目と同じ要素数かどうかを確かめるルール。
//
// 使い方（attachの4本の並行配列が同じ要素数かどうか）:
//   'attach_tmp' => ['array', new SameCountAsRule(['attach', 'attach_origin', 'attach_del'])]
//
// 普通のルール（ValidationRule）は、チェック対象の項目の値しか受け取れない。
// DataAwareRuleも実装しておくと、Laravelがチェックの前にsetData()で
// 送信データ全体を渡してくれるので、他の項目と比べられるようになる。
//
// 比べる相手の項目が送られてきていない場合は、要素数0として扱う。
class SameCountAsRule implements ValidationRule, DataAwareRule
{
    private array $data = [];

    /**
     * $others … 要素数を比べる相手の項目名の配列
     */
    public function __construct(private array $others)
    {
    }

    /**
     * Laravelが、チェックの前に送信データ全体を渡すために呼ぶ。
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = is_array($value) ? count($value) : 0;

        foreach ($this->others as $other) {
            $otherValue = $this->data[$other] ?? [];
            $otherCount = is_array($otherValue) ? count($otherValue) : 0;

            if ($otherCount !== $count) {
                // 画面から普通に操作していれば起きない（hiddenの書き換えなど）ので、
                // 気づけるようにログに残す。
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
