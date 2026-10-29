<?php

namespace Neobit\Core\Security;

/**
 * 入口の見張りの判断。IPブロックとアクセス制限で、止めるか・通すかを決める。
 * Laravelを起動する前に動く見張り（packages/neobit/core/gate.php）と、Laravelのミドルウェア
 * （RejectBlockedIp・RestrictSiteAccess・RestrictAdminAccess）の両方が、同じ判断をここで行う。
 *
 * ■ Laravelに頼らない
 * 見張りはLaravelもComposerも読み込まず、このファイルだけをrequireして使う。なので、Laravelの関数・クラスと、
 * SymfonyのIpUtilsは使わない。IPアドレスの照らし合わせと、訪問者のIPアドレスの求め方も、ここで持つ。
 *
 * ■ 状態
 * 判断に使う状態は、GateState::build()が作る配列。見張りは、それを書き出したJSON（storage/app/gate/state.json）を読む。
 * - blocks：止めているIPアドレスかまとまりと、期限のUNIX時刻（期限なしはnull）の並び
 * - site・admin：['restricted' => 制限するか, 'ips' => 許すIPアドレスの一覧]
 * - admin_path：管理画面のURLの頭（app/admin の形）
 * - site_except：サイトの制限を掛けないURL（up・mail/unsubscribe・管理画面の下など。* で続きを表す）
 * - trusted_proxies：信頼するプロキシ（.envのTRUSTED_PROXIES）
 */
final class Gate
{
    // ---- 応答 ----

    /** 通す */
    public const PASS = 0;

    /** 止めているIPアドレスと、管理画面の制限。そこに何かあることを知らせないよう、ふつうの404にする */
    public const NOT_FOUND = 404;

    /** サイトの制限。一時的に止めていることを検索エンジンに伝えるため、503にする */
    public const MAINTENANCE = 503;

    // ---- 見張りが返す画面 ----

    /** 状態のファイルと同じディレクトリに、書き出しのときにLaravelのビューから作って置く画面 */
    public const PAGES = [
        self::NOT_FOUND => '404.html',
        self::MAINTENANCE => 'maintenance.html',
    ];

    // ---- 信頼するプロキシの書き方 ----

    /** 信頼するプロキシに、直接つないできた相手を入れる書き方（LaravelのTrustProxiesと同じ） */
    private const PROXY_REMOTE_ADDR = 'REMOTE_ADDR';

    /** 信頼するプロキシに、プライベートなどの範囲をまとめて入れる書き方（SymfonyのRequestと同じ） */
    private const PROXY_PRIVATE_SUBNETS = ['PRIVATE_SUBNETS', 'private_ranges'];

    /** PRIVATE_SUBNETSが表す範囲。SymfonyのIpUtils::PRIVATE_SUBNETSと同じ */
    private const PRIVATE_SUBNETS = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '192.168.0.0/16',
        '192.0.2.0/24',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '172.16.0.0/12',
        '169.254.0.0/16',
        '198.18.0.0/15',
        '0.0.0.0/8',
        '240.0.0.0/4',
        '100.64.0.0/10',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
        '::ffff:0:0/96',
        '::/128',
        '::/96',
        '2002::/16',
        '2001::/32',
        '2001:db8::/32',
        '2001:0002::/48',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
    ];

    // ---- 見張り ----

    /**
     * 見張りの本体。gate.phpが呼ぶ。止めるなら応答を送って終わり、通すなら何もせずに戻る。
     * 状態のファイルを読めないときは、何もせずに通す。後ろのLaravelのミドルウェアが判断するため。
     */
    public static function guard(string $stateFile): void
    {
        // コマンドから動いたときは、見る相手がいない
        if (! isset($_SERVER['REMOTE_ADDR'])) {
            return;
        }

        $json = @file_get_contents($stateFile);
        $state = $json === false ? null : json_decode($json, true);
        if (! is_array($state)) {
            return;
        }

        $ip = self::clientIp((array) ($state['trusted_proxies'] ?? []), (string) $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null);
        $path = self::path((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        $status = self::decide($state, $ip, $path, time());
        if ($status === self::PASS) {
            return;
        }

        // 止める。Laravelと同じ見た目の画面を返す。画面のファイルが無ければ、短い文だけ
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        $page = @file_get_contents(dirname($stateFile).DIRECTORY_SEPARATOR.self::PAGES[$status]);
        echo $page === false ? ($status === self::NOT_FOUND ? 'Not Found' : 'Service Unavailable') : $page;
        exit;
    }

    // ---- 判断 ----

    /** 止めるか・通すか。PASS・NOT_FOUND・MAINTENANCEのどれかを返す */
    public static function decide(array $state, string $ip, string $path, int $now): int
    {
        if (self::isBlocked((array) ($state['blocks'] ?? []), $ip, $now)) {
            return self::NOT_FOUND;
        }

        // 管理画面の下は、管理画面の制限だけを見る。サイトの制限とは互いに影響しない
        $admin = (string) ($state['admin_path'] ?? '');
        if ($admin !== '' && ($path === $admin || str_starts_with($path, $admin.'/'))) {
            return self::allows((array) ($state['admin'] ?? []), $ip) ? self::PASS : self::NOT_FOUND;
        }

        if (self::opensSite($state, $path, $ip)) {
            return self::PASS;
        }

        return self::MAINTENANCE;
    }

    /** 止めているIPアドレスか。期限の過ぎたものは見ない。$blocksは[IPアドレスかまとまり, 期限のUNIX時刻かnull]の並び */
    public static function isBlocked(array $blocks, string $ip, int $now): bool
    {
        if ($ip === '') {
            return false;
        }

        foreach ($blocks as [$range, $until]) {
            if (($until === null || $until > $now) && self::inRange($ip, [$range])) {
                return true;
            }
        }

        return false;
    }

    /** サイト（訪問者画面）を開いてよいか。制限を掛けないURLか、サイトの制限で許しているIPアドレスなら開ける */
    public static function opensSite(array $state, string $path, string $ip): bool
    {
        return self::matchesPath((array) ($state['site_except'] ?? []), $path) || self::allows((array) ($state['site'] ?? []), $ip);
    }

    /** 制限の範囲（site・admin）を、そのIPアドレスで開いてよいか。制限していなければ誰でもよい */
    public static function allows(array $area, string $ip): bool
    {
        return empty($area['restricted']) || self::inRange($ip, (array) ($area['ips'] ?? []));
    }

    // ---- IPアドレス ----

    /**
     * IPアドレスが、一覧のどれかに当たるか。一覧には、IPアドレスと、192.0.2.0/24のようなまとまりを書ける。
     * IPv4とIPv6は、互いに当たらない。正しくない形のものは、当たらないものとして読み飛ばす。
     */
    public static function inRange(string $ip, array $ranges): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach ($ranges as $range) {
            [$address, $prefix] = str_contains((string) $range, '/') ? explode('/', (string) $range, 2) : [(string) $range, null];
            $network = @inet_pton($address);
            if ($network === false || strlen($network) !== strlen($packed)) {
                continue;
            }

            // まとまりの長さ。書いていなければ、そのIPアドレスだけ
            $bits = strlen($packed) * 8;
            if ($prefix === null) {
                $prefix = $bits;
            } elseif (ctype_digit($prefix) && (int) $prefix <= $bits) {
                $prefix = (int) $prefix;
            } else {
                continue;
            }

            // 頭から$prefixのビットが同じなら当たる。まず丸ごとのバイトを比べ、残りのビットをマスクで比べる
            $bytes = intdiv($prefix, 8);
            if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            $rest = $prefix % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 訪問者のIPアドレス。LaravelのTrustProxiesを通した後の$request->ip()と同じ答えを返す。
     * 直接つないできた相手が信頼するプロキシのときだけ、X-Forwarded-Forを右から見て、
     * 信頼するプロキシでない最初のIPアドレスを訪問者とする。このヘッダーは送る側が自由に書けるので、
     * 信頼するプロキシのほかから届いたものは使わない。
     */
    public static function clientIp(array $trustedProxies, string $remoteAddr, ?string $forwardedFor): string
    {
        // 信頼するプロキシの書き方を、IPアドレスの一覧に直す
        $proxies = [];
        foreach ($trustedProxies as $proxy) {
            $proxy = trim((string) $proxy);
            if ($proxy === self::PROXY_REMOTE_ADDR) {
                $proxies[] = $remoteAddr;
            } elseif (in_array($proxy, self::PROXY_PRIVATE_SUBNETS, true)) {
                array_push($proxies, ...self::PRIVATE_SUBNETS);
            } elseif ($proxy !== '') {
                $proxies[] = $proxy;
            }
        }

        // 直接つないできた相手が信頼するプロキシでなければ、その相手が訪問者
        if ($forwardedFor === null || trim($forwardedFor) === '' || ! self::inRange($remoteAddr, $proxies)) {
            return $remoteAddr;
        }

        // ヘッダーの並びに、直接つないできた相手を足し、信頼するプロキシと正しくない形のものを外す
        $chain = [...array_map(trim(...), explode(',', $forwardedFor)), $remoteAddr];
        $firstTrusted = null;
        $untrusted = [];
        foreach ($chain as $entry) {
            if (strpos($entry, '.')) {
                // IPv4の後ろの:ポートを外す
                $colon = strpos($entry, ':');
                if ($colon) {
                    $entry = substr($entry, 0, $colon);
                }
            } elseif (str_starts_with($entry, '[')) {
                // IPv6の[]と:ポートを外す
                $entry = substr($entry, 1, (int) strpos($entry, ']', 1) - 1);
            }

            if (! filter_var($entry, FILTER_VALIDATE_IP)) {
                continue;
            }
            if (self::inRange($entry, $proxies)) {
                $firstTrusted ??= $entry;

                continue;
            }
            $untrusted[] = $entry;
        }

        // 右端の信頼しないIPアドレスが訪問者。全部が信頼するプロキシなら、その最初のもの
        return $untrusted !== [] ? (string) end($untrusted) : (string) $firstTrusted;
    }

    // ---- URL ----

    /** 要求のURIを、Laravelの$request->decodedPath()と同じ形のパスにする。頭と末尾の/を外し、最上位は「/」 */
    public static function path(string $requestUri): string
    {
        $path = trim(rawurldecode((string) parse_url($requestUri, PHP_URL_PATH)), '/');

        return $path === '' ? '/' : $path;
    }

    /** パスが、一覧のどれかに当たるか。* は、/も含めて何文字でも当たる（Laravelの$request->is()と同じ） */
    public static function matchesPath(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;
            if ($pattern === $path) {
                return true;
            }
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'\z#u';
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
