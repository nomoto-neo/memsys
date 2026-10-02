<?php

namespace App\Support;

/**
 * ブラウザのUser-Agentから、「Windows・Chrome」のような短い端末名を作る。
 * パスキーの名前と、最後に使った端末の表示に使う。
 *
 * 利用者に「どの端末のことか」を思い出してもらうための目安なので、OSと
 * ブラウザの名前だけにして、バージョンは付けない（バージョンは更新のたびに
 * 変わり、同じ端末なのに別の名前に見えてしまうため）。
 * User-Agentは送る側が自由に書き換えられる値なので、表示以外の判定には使わない。
 */
class UserAgentLabel
{
    /** OS。上から順に調べ、最初に当てはまったものを使う。 */
    private const OS_PATTERNS = [
        '/iPhone/' => 'iPhone',
        '/iPad/' => 'iPad',
        '/Android/' => 'Android',
        '/Windows/' => 'Windows',
        '/CrOS/' => 'ChromeOS',
        '/Macintosh|Mac OS X/' => 'Mac',
        '/Linux/' => 'Linux',
    ];

    /**
     * ブラウザ。上から順に調べ、最初に当てはまったものを使う。
     * EdgeやOperaのUser-Agentには「Chrome」も含まれ、ChromeのUser-Agentには
     * 「Safari」も含まれるので、派生したものほど上に置く。
     */
    private const BROWSER_PATTERNS = [
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|Opera/' => 'Opera',
        '/SamsungBrowser\//' => 'Samsung Internet',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\//' => 'Chrome',
        '/Safari\//' => 'Safari',
    ];

    public static function of(?string $userAgent): string
    {
        $userAgent ??= '';

        $os = self::match(self::OS_PATTERNS, $userAgent);
        $browser = self::match(self::BROWSER_PATTERNS, $userAgent);

        $parts = array_filter([$os, $browser]);

        return $parts === [] ? '不明な端末' : implode('・', $parts);
    }

    private static function match(array $patterns, string $userAgent): ?string
    {
        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $label;
            }
        }

        return null;
    }
}
