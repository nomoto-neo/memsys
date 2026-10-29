<?php

namespace Neobit\Core\Security;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\IpBlock;
use Neobit\Core\Operations\OperationRecorder;
use Symfony\Component\HttpFoundation\Response;

/**
 * IPブロック管理。不正なURLを続けて叩くBOTを、IPアドレスで止める。
 * WAFやfail2banを入れられない環境で、PHPの側で身を守るためのもの。
 *
 * ■ 止め方
 * 全体のミドルウェア（Neobit\Core\Http\Middleware\RejectBlockedIp）が、どのアクセスでも最初に
 * isBlocked()を呼び、止めているIPアドレスなら404を返す。ルートの無いURLも、ルートを探す前に
 * ここを通る。止めている一覧はキャッシュに置き、毎回DBは読まない。登録・解除のたびに作り直す。
 *
 * ■ 自動で止める
 * 応答が404・405になったアクセスを、IPアドレスごとに数える（countMiss()）。
 * config('neobit.ip_block_watch_minutes')の分の中に、config('neobit.ip_block_watch_count')回を
 * 超えたら、config('neobit.ip_block_hours')の時間止める。config('neobit.ip_block_auto')がfalseなら
 * 数えず、管理画面からの登録だけになる。IPv6は、1人の契約者に/64が割り当てられることが多いので、
 * /64のまとまりで数えて止める。
 *
 * ■ 止めないIPアドレス
 * アクセス制限（AccessRestriction）で通しているIPアドレス（制限しないIPアドレスと、管理画面のIPアドレス）は、
 * 自動でも手動でも止めない。管理者が、自分のアクセスを止めてしまわないようにするため。
 *
 * ■ 記録
 * 止めたこと・解除したことは、操作ログに残す。いつ誰が止めて解除したかは、操作ログで追う。
 * 解除した行と、期限が過ぎた行は消す。期限が過ぎた行は、毎時の片付け（TemporaryDataCleaner）が消す。
 *
 * ■ 入口の見張り
 * 止めるかの判断は、入口の見張りと同じもの（Neobit\Core\Security\Gate）を使う。止めている一覧を変えたら、
 * 見張りの状態も書き出す（GateState）。見張りは、Laravelを起動する前に、同じ一覧で止める。
 */
final class IpBlocker
{
    // ---- 止めている一覧 ----

    /** 止めている一覧を置くキャッシュのキー。登録・解除のたびに消して、次の判定で作り直す */
    private const CACHE_KEY = 'neobit-ip-blocks';

    // ---- 自動で止める ----

    /** 数える応答の状態。存在しないURLと、許されていないメソッド */
    private const MISS_STATUSES = [404, 405];

    /** アクセスの回数を数えるキャッシュのキーの頭 */
    private const MISS_KEY = 'neobit-ip-block-miss:';

    /** 自動で止める理由 */
    private const AUTO_REASON = '存在しないURLへのアクセスが多い';

    /** 自動で止めるときの、IPv6のまとまりの長さ */
    private const IPV6_PREFIX = 64;

    /**
     * 設定が無いときの値。設定のキャッシュが古くて項目がまだ無いときにも使う。
     * 自動で止めるかも、無いときは止める側にする。config:cacheを忘れたときに、黙って働かなくならないようにするため
     */
    private const DEFAULT_AUTO = true;

    private const DEFAULT_WATCH_MINUTES = 10;

    private const DEFAULT_WATCH_COUNT = 30;

    private const DEFAULT_HOURS = 24;

    // ---- 手動で登録できるまとまり ----

    /**
     * 手動で登録できる、まとまりの最も短い長さ。これより広いまとまりは、
     * 関係の無い人まで大勢止めてしまうので、登録させない
     */
    private const MIN_PREFIX = [
        4 => 16,
        6 => 32,
    ];

    /** そのIPアドレスを止めているか。期限の過ぎたものは見ない */
    public static function isBlocked(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        return Gate::isBlocked(self::blockedList(), $ip, now()->getTimestamp());
    }

    /**
     * 応答を見て、存在しないURLなどへのアクセスを数える。目安を超えたら、自動で止める。
     * RejectBlockedIpが、応答を返す前に呼ぶ。
     */
    public static function countMiss(Request $request, Response $response): void
    {
        if (! config('neobit.ip_block_auto', self::DEFAULT_AUTO) || ! in_array($response->getStatusCode(), self::MISS_STATUSES, true)) {
            return;
        }

        $ip = (string) $request->ip();
        if ($ip === '' || self::isAllowed($ip)) {
            return;
        }

        $range = self::autoRange($ip);
        $minutes = (int) config('neobit.ip_block_watch_minutes') ?: self::DEFAULT_WATCH_MINUTES;
        $count = (int) config('neobit.ip_block_watch_count') ?: self::DEFAULT_WATCH_COUNT;
        $hours = (int) config('neobit.ip_block_hours') ?: self::DEFAULT_HOURS;

        // 数える時間の中のアクセスの回数。目安に届かなければ、ここまで
        $hits = RateLimiter::hit(self::MISS_KEY.$range, $minutes * 60);
        if ($hits < $count) {
            return;
        }

        RateLimiter::clear(self::MISS_KEY.$range);

        self::block($range, now()->addHours($hours), self::AUTO_REASON, auto: true, hits: $hits);

        // ERROR_NOTIFY_LEVELがwarningなら、エラーの通知のメールが届く
        Log::warning("IpBlocker: 存在しないURLへのアクセスが多いので、{$range}を{$hours}時間止めました。", [
            'hits' => $hits,
            'minutes' => $minutes,
            'path' => '/'.ltrim($request->path(), '/'),
        ]);
    }

    /**
     * 止める。同じIPアドレスの行があれば、期限などを書き換える。
     * $untilがnullなら期限なし。$registeredByは、管理画面から登録したスタッフの名前。
     */
    public static function block(string $range, ?CarbonInterface $until, ?string $reason, bool $auto = false, ?int $hits = null, ?string $registeredBy = null): IpBlock
    {
        $values = [
            'auto' => $auto,
            'reason' => $reason,
            'hits' => $hits,
            'blocked_until' => $until,
            'registered_by' => $registeredBy,
        ];

        // 同じIPアドレスのアクセスが同時に目安を超えると、2つの処理が同時に登録しようとする。
        // 後から来た方は一意の制約に当たるので、先に登録された行を書き換える
        try {
            $block = IpBlock::query()->updateOrCreate(['ip' => $range], $values);
        } catch (UniqueConstraintViolationException) {
            $block = IpBlock::query()->where('ip', $range)->firstOrFail();
            $block->update($values);
        }

        self::forgetList();

        OperationRecorder::record(OperationLogAction::IpBlock, detail: [
            'ip' => $range,
            'until' => $until?->format('Y-m-d H:i') ?? '期限なし',
            'reason' => $auto ? '自動：'.$reason : (string) $reason,
        ]);

        return $block;
    }

    /** 期限を変える。$untilがnullなら期限なし */
    public static function changeUntil(IpBlock $block, ?CarbonInterface $until): void
    {
        $block->update(['blocked_until' => $until]);

        self::forgetList();

        OperationRecorder::record(OperationLogAction::IpBlock, detail: [
            'ip' => $block->ip,
            'until' => $until?->format('Y-m-d H:i') ?? '期限なし',
            'reason' => '期限の変更',
        ]);
    }

    /** 解除する。行を消して、止めている一覧を作り直す */
    public static function release(IpBlock $block): void
    {
        $block->delete();

        self::forgetList();

        OperationRecorder::record(OperationLogAction::IpUnblock, detail: ['ip' => $block->ip]);
    }

    /** 期限が過ぎた行を消して、消した件数を返す。毎時の片付け（TemporaryDataCleaner）が呼ぶ */
    public static function deleteExpired(): int
    {
        $deleted = IpBlock::query()->where('blocked_until', '<=', now())->delete();

        if ($deleted > 0) {
            self::forgetList();
        }

        return $deleted;
    }

    /**
     * 管理画面で入力されたIPアドレスかまとまりを、保存する形にそろえる。正しくない形なら null。
     * まとまりの長さが MIN_PREFIX より短いもの（広すぎるもの）も null にする。
     */
    public static function normalizeRange(string $value): ?string
    {
        $value = trim($value);
        [$address, $prefix] = str_contains($value, '/') ? explode('/', $value, 2) : [$value, null];

        $packed = @inet_pton($address);
        if ($packed === false) {
            return null;
        }

        // IPv6は、書き方の揺れ（0の省略や大文字）をそろえる
        $address = inet_ntop($packed);
        if ($prefix === null) {
            return $address;
        }

        $version = strlen($packed) === 4 ? 4 : 6;
        $max = $version === 4 ? 32 : 128;
        if (! ctype_digit($prefix) || (int) $prefix < self::MIN_PREFIX[$version] || (int) $prefix > $max) {
            return null;
        }

        return $address.'/'.(int) $prefix;
    }

    /**
     * IPアドレスかまとまりが表す、最初と最後のIPアドレス。画面で「192.0.2.0 ～ 192.0.2.255」と見せるため。
     * まとまりでないなら、最初と最後は同じ。
     *
     * @return array{0: string, 1: string}
     */
    public static function rangeBounds(string $range): array
    {
        [$address, $prefix] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $packed = (string) @inet_pton($address);
        $bits = strlen($packed) * 8;
        $prefix = $prefix === null ? $bits : (int) $prefix;

        // 頭からprefixのビットだけが1のマスクを作り、最初は0で、最後は1で埋める
        $first = '';
        $last = '';
        foreach (str_split($packed) as $index => $byte) {
            $maskBits = max(0, min(8, $prefix - $index * 8));
            $mask = (0xFF << (8 - $maskBits)) & 0xFF;
            $first .= chr(ord($byte) & $mask);
            $last .= chr(ord($byte) | (~$mask & 0xFF));
        }

        return [(string) inet_ntop($first), (string) inet_ntop($last)];
    }

    /**
     * 止めると困るIPアドレスを含むか。入ってよいIPアドレスの一覧と、今操作している人の
     * IPアドレス。管理画面からの登録で、自分たちを止めないように確かめる。
     */
    public static function coversProtected(string $range, string $currentIp): bool
    {
        if (Gate::inRange($currentIp, [$range])) {
            return true;
        }

        // 入ってよいIPアドレスの一覧の行と、どちらかがもう一方を含んでいれば重なる
        foreach (self::allowedList() as $allowed) {
            $allowedAddress = explode('/', $allowed, 2)[0];
            $rangeAddress = explode('/', $range, 2)[0];

            if (Gate::inRange($allowedAddress, [$range]) || Gate::inRange($rangeAddress, [$allowed])) {
                return true;
            }
        }

        return false;
    }

    /** 止めている一覧。[IPアドレスかまとまり, 期限のUNIX時刻かnull]の並び。入口の見張りの状態にも入れる（GateState） */
    public static function blockedList(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => IpBlock::query()
            ->active()
            ->get(['ip', 'blocked_until'])
            ->map(fn (IpBlock $block) => [$block->ip, $block->blocked_until?->getTimestamp()])
            ->all());
    }

    /** 止めている一覧のキャッシュを消して、次の判定でDBから作り直す。入口の見張りの状態も書き出す */
    private static function forgetList(): void
    {
        Cache::forget(self::CACHE_KEY);
        GateState::exportOrReport();
    }

    /** アクセス制限で通しているIPアドレスの一覧 */
    private static function allowedList(): array
    {
        return AccessRestriction::allowedIps();
    }

    /** 入ってよいIPアドレスか。自動で止めないために見る */
    private static function isAllowed(string $ip): bool
    {
        $allowed = self::allowedList();

        return Gate::inRange($ip, $allowed);
    }

    /** 自動で止めるときの単位。IPv4は1つ、IPv6は/64のまとまり */
    private static function autoRange(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) === 4) {
            return $ip;
        }

        // 後ろの64ビットを0にして、まとまりの頭のアドレスにする
        $network = substr($packed, 0, intdiv(self::IPV6_PREFIX, 8)).str_repeat("\0", 16 - intdiv(self::IPV6_PREFIX, 8));

        return inet_ntop($network).'/'.self::IPV6_PREFIX;
    }
}
