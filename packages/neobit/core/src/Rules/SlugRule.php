<?php

namespace Neobit\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * slug（URLの名前）の形の検証ルール。半角の英小文字・数字・ハイフン・アンダーバーで、先頭は英小文字か数字。
 * new SlugRule(nested: true)なら「/」で区切ってつなげられる（service/price）。先頭と末尾の「/」と「//」は通さない。
 *
 *   'slug' => ['required', 'string', 'max:100', new SlugRule(nested: true), Rule::unique(...)->ignore(...)]
 *
 * 訪問者の側のルートのwhere()にも同じパターン（PATTERN・NESTED_PATTERN）を使う。食い違うと、
 * 登録できるのに開けないページができるため。エラーの文は、通常のvalidation.regexの文。
 */
class SlugRule implements ValidationRule
{
    /** 1区切りの名前に使える形 */
    public const PATTERN = '[a-z0-9][a-z0-9_-]*';

    /** 「/」で区切ってつなげた名前の形 */
    public const NESTED_PATTERN = self::PATTERN.'(?:/'.self::PATTERN.')*';

    /** $nestedがtrueなら、「/」で区切ってつなげた名前も通す */
    public function __construct(private bool $nested = false)
    {
    }

    /** 検証。形が合わなければ$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = $this->nested ? self::NESTED_PATTERN : self::PATTERN;

        if (! is_string($value) || ! preg_match('#^'.$pattern.'$#', $value)) {
            $fail('validation.regex')->translate();
        }
    }
}
