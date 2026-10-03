<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 電話番号の形の検証ルール。数字とハイフンだけで、桁数がそれらしいかを確かめる。
 * 'phone' => ['nullable', 'string', new PhoneNumberRule()] のように使う。
 */
class PhoneNumberRule implements ValidationRule
{
    // 検証。合わなければ$fail()を呼ぶ（合っていれば何もしない）
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ハイフンの有無で、許す長さを変える
        if (str_contains($value, '-')) {
            // ハイフンあり：090-1234-5678・03-1234-5678・0120-1-2345 など、11〜13文字
            $pattern = '/^[0-9\-]{11,13}$/';
        } else {
            // ハイフンなし：09012345678・0312345678 など、9〜11桁
            $pattern = '/^[0-9]{9,11}$/';
        }

        if (! preg_match($pattern, $value)) {
            $fail('電話番号の形式が正しくありません。');
        }
    }
}
