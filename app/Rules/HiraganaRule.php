<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ひらがなだけで書かれているかの検証ルール。ふりがなの欄に使う。
 * 'kana' => ['nullable', 'string', 'max:255', new HiraganaRule()] のように使う。
 *
 * 通すのは、ひらがなと、長音（ー）・中点（・）、全角と半角の空白。
 * 英数字とハイフン、カタカナは通さない。考え方はKatakanaRuleと同じ。
 * 見本のサイトの欄は全部「フリガナ」（カタカナ）なので、今は使っている所が無い。
 */
class HiraganaRule implements ValidationRule
{
    // 検証。合わなければ$fail()を呼ぶ（合っていれば何もしない）
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[ぁ-んゔー・　 ]+$/u', $value)) {
            $fail('この項目はひらがなで入力してください。');
        }
    }
}
