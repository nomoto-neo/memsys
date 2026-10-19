<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * メールアドレスのドメインが、DNSにあるかの検証ルール。メールを受け取る設定（MX）か、
 * サーバーの住所（A・AAAA）があれば通す。exmaple.comのようなドメインの打ち間違いを、送る前に見つけるため。
 * アカウントが無い（User unknown）ことは、送ってみないと分からないので、ここでは確かめない。
 *
 * 同じドメインは1回だけ引き、結果を覚えておく。一斉メールの宛先には同じドメインが多いため。
 * 形が正しいかは、先にemailのルールで確かめておく。
 */
class MailDomainRule implements ValidationRule
{
    /** 引いたドメインの結果。ドメイン => あったか */
    private static array $checked = [];

    /** 検証。合わなければ$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr((string) $value, '@') ?: '', 1));

        // ドメインが無いのは形の誤りなので、emailのルールに任せる
        if ($domain === '') {
            return;
        }

        if (! (self::$checked[$domain] ??= self::exists($domain))) {
            $fail("メールアドレスのドメイン（{$domain}）が見つかりません。打ち間違いがないか確かめてください。");
        }
    }

    /** ドメインに、MX・A・AAAAのどれかがあるか */
    private static function exists(string $domain): bool
    {
        // 日本語のドメインは、DNSで引ける形（xn--…）に直す。直せる部品が無いサーバーでは、そのまま引く
        if (function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        // 末尾に「.」を付けて、サーバーの検索ドメインを足さずに引く
        $fqdn = rtrim($domain, '.').'.';

        return checkdnsrr($fqdn, 'MX') || checkdnsrr($fqdn, 'A') || checkdnsrr($fqdn, 'AAAA');
    }
}
