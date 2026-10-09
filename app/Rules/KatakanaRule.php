<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 全角カタカナだけで書かれているかの検証ルール。フリガナの欄に使う。
 * 'kana' => ['nullable', 'string', 'max:255', new KatakanaRule()] のように使う。
 *
 * 通すのは、全角カタカナと、長音（ー）・中点（・）、全角と半角の空白。
 * 英数字とハイフンは通さない。長音の代わりにハイフンを入れた値は、入力をそろえる処理
 * （App\Support\InputNormalizer）で半角のハイフンになるので、ここでエラーになる。
 * 半角カナは、検証の前に全角カナになっているので通る。
 * ひらがなは変換しないので、ここでエラーにして、入力し直してもらう。
 */
class KatakanaRule implements ValidationRule
{
    /** 検証。合わなければ$fail()を呼ぶ（合っていれば何もしない） */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ァ-ヾの範囲に、長音（ー）と中点（・）も入っている
        if (! is_string($value) || ! preg_match('/^[ァ-ヾ　 ]+$/u', $value)) {
            $fail('この項目は全角カタカナで入力してください。');
        }
    }
}
