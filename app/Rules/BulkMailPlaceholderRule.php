<?php

namespace App\Rules;

use App\Models\BulkMail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 一斉メールの件名と本文の差し込みの検証ルール。使える差し込みは氏名の{{$name}}だけで、
 * ほかの{{…}}があればエラーにする。書き間違いのまま、宛先にそのまま届かないようにするため。
 */
class BulkMailPlaceholderRule implements ValidationRule
{
    /** 検証。合わなければ$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 氏名の印を消して、まだ{{…}}が残っていれば書き間違い
        $rest = preg_replace(BulkMail::NAME_PLACEHOLDER, '', (string) $value);

        if (preg_match(BulkMail::ANY_PLACEHOLDER, $rest, $m)) {
            $fail("「{$m[0]}」は使えません。差し込めるのは氏名の{{\$name}}だけです。");
        }
    }
}
