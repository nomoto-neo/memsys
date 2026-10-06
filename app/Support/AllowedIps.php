<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * 接続元のIPアドレスが、.envに書いた「入ってよいIPアドレス」に入っているかを確かめる。
 * サイト全体の制限（App\Http\Middleware\RestrictSiteAccess）と、管理画面の制限
 * （App\Http\Middleware\RestrictAdminAccess）が使う。
 *
 * 一覧は、config/app.phpがカンマ区切りの文字を配列にしたもの。192.168.0.0/24のような範囲と、
 * IPv6のアドレスも書ける。一覧が空なら、制限しない。
 * 接続元のIPアドレスは、信頼するプロキシ（TRUSTED_PROXIES）を置いていれば、そのプロキシの
 * ヘッダーから決まる。プロキシを置いたのにTRUSTED_PROXIESを書かないと、全員がプロキシの
 * IPアドレスに見えるので、全員が通るか、全員が止まる。
 */
final class AllowedIps
{
    /**
     * @param  string  $configKey  一覧のある設定の名前。例：'app.site_allowed_ips'
     */
    public static function allows(string $configKey, Request $request): bool
    {
        $allowed = config($configKey, []);

        // 一覧が空なら、制限しない
        if ($allowed === []) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), $allowed);
    }
}
