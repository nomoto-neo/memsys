<?php

namespace Neobit\Core\Security;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\Setting;
use Neobit\Core\Operations\OperationRecorder;

/**
 * アクセス制限。サイト（訪問者画面）と管理画面を、それぞれ決めたIPアドレスからだけ開けるようにする。
 * 管理画面の「アクセス制限」の画面（Neobit\Core\Http\Controllers\AccessRestrictionController）で、
 * 運用する人が設定を変えられる。判断するのは、サイトの制限（RestrictSiteAccess）と、
 * 管理画面の制限（RestrictAdminAccess）。
 *
 * ■ 決まり
 * - サイトと管理画面は、それぞれ画面でチェックを入れたときだけ制限する。管理画面の制限はサイトに掛からず、
 *   サイトの制限は管理画面に掛からない。ただし、管理画面のIPアドレスは、サイトの制限でも通す（Gate::opensSite()）
 * - サイトは、サイトのIPアドレスのほかに、メンテナンス中の画面を出す。管理画面の下のURLには掛けない
 *   （外すパスは、アプリがRestrictSiteAccess::except()で渡す）
 * - 管理画面は、管理画面のIPアドレスのほかに、404を返す
 * - どちらの一覧も、画面で入れたものと、設定ファイル（.envのSITE_ALLOWED_IPS・ADMIN_ALLOWED_IPS）の
 *   ものを足し合わせる。設定ファイルの分は画面から外せないので、サーバーを管理する人の非常口になる。
 *   設定ファイルに書いただけでは、制限は掛からない
 *
 * ■ 締め出さない
 * 管理画面の制限を掛けて保存するとき、今の自分のIPアドレスが管理画面の一覧に無ければ、保存させない
 * （lockoutMessage()）。それでも回線が変わるなどで締め出されたときは、サーバーでapp:access-denied-offを
 * 実行すると、両方の制限を外す（turnOff()）。
 *
 * ■ 速さ
 * 毎回のアクセスで読むので、画面の設定はキャッシュに置き、保存のたびに作り直す。設定のテーブルが
 * まだ無いとき（マイグレーションの前）は、画面の設定が無いものとして動く。
 *
 * ■ 一覧の書き方
 * 画面の2つの欄は、1行に1つ。# から後ろはコメントで、行の頭に # を付けると、その行は使わない。どのIPアドレスが
 * 誰のものかを残すためと、一時的に外して後で戻せるようにするため。入力した文はそのまま保存し（コメントも並び順も）、
 * 判断に使うIPアドレスの一覧は、保存のときにそこから作って別に保存する。
 *
 * ■ 入口の見張り
 * 判断は、入口の見張りと同じもの（Neobit\Core\Security\Gate）を使う。保存したら、見張りの状態も書き出す（GateState）。
 */
final class AccessRestriction
{
    // ---- 設定の置き場所 ----

    /** 画面の設定を置くキャッシュのキー */
    private const CACHE_KEY = 'neobit-access-restriction';

    /** 画面の設定のキー（settingsテーブル） */
    private const SITE_RESTRICTED_KEY = 'access.site_restricted';

    private const SITE_IPS_KEY = 'access.site_allowed_ips';

    private const ADMIN_RESTRICTED_KEY = 'access.admin_restricted';

    private const ADMIN_IPS_KEY = 'access.admin_allowed_ips';

    /** 画面で入力した文のキー。コメントも並び順もそのまま持つ */
    private const SITE_TEXT_KEY = 'access.site_allowed_ips_text';

    private const ADMIN_TEXT_KEY = 'access.admin_allowed_ips_text';

    /** 設定ファイルの一覧のある設定の名前。config/app.phpが.envから作る */
    private const ENV_SITE_IPS = 'app.site_allowed_ips';

    private const ENV_ADMIN_IPS = 'app.admin_allowed_ips';

    // ---- 判断 ----

    /** サイト（訪問者画面）のIPアドレスの制限で、開いてよいか。制限していなければ誰でもよい */
    public static function allowsSite(Request $request): bool
    {
        return Gate::allows(GateState::build()['site'], (string) $request->ip());
    }

    /** 管理画面を開いてよいか。制限していなければ誰でもよい */
    public static function allowsAdmin(Request $request): bool
    {
        return Gate::allows(GateState::build()['admin'], (string) $request->ip());
    }

    /** サイトと管理画面の一覧にあるIPアドレスの全部。IPブロック管理が、止めない対象にする */
    public static function allowedIps(): array
    {
        return [...self::siteIps(), ...self::adminIps()];
    }

    /** サイトのIPアドレス。画面と設定ファイルを足し合わせたもの */
    public static function siteIps(): array
    {
        return [...self::settings()['site_ips'], ...self::envSiteIps()];
    }

    /** 管理画面のIPアドレス。画面と設定ファイルを足し合わせたもの */
    public static function adminIps(): array
    {
        return [...self::settings()['admin_ips'], ...self::envAdminIps()];
    }

    /** 設定ファイルで、常に許可しているサイトのIPアドレス */
    public static function envSiteIps(): array
    {
        return (array) config(self::ENV_SITE_IPS, []);
    }

    /** 設定ファイルで、常に許可している管理画面のIPアドレス */
    public static function envAdminIps(): array
    {
        return (array) config(self::ENV_ADMIN_IPS, []);
    }

    // ---- 画面の設定 ----

    /**
     * 画面で入れた設定。DBを毎回読まないよう、キャッシュに置く。
     * 入力した文がまだ無いとき（コメントを書けるようになる前に保存した設定）は、一覧を1行1つの文にする。
     *
     * @return array{site_restricted: bool, site_ips: string[], site_text: string, admin_restricted: bool, admin_ips: string[], admin_text: string}
     */
    public static function settings(): array
    {
        $defaults = ['site_restricted' => false, 'site_ips' => [], 'site_text' => '', 'admin_restricted' => false, 'admin_ips' => [], 'admin_text' => ''];

        // キャッシュに前の版の形が残っていても動くよう、足りない項目は既定の値で補う
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached + $defaults;
        }

        // 設定のテーブルがまだ無いとき（マイグレーションの前）は、画面の設定が無いものとして動く。
        // キャッシュには置かず、テーブルができたら読めるようにする
        try {
            $values = Setting::values([self::SITE_RESTRICTED_KEY, self::SITE_IPS_KEY, self::SITE_TEXT_KEY, self::ADMIN_RESTRICTED_KEY, self::ADMIN_IPS_KEY, self::ADMIN_TEXT_KEY]);
        } catch (QueryException) {
            return $defaults;
        }

        $siteIps = array_values((array) ($values[self::SITE_IPS_KEY] ?? []));
        $adminIps = array_values((array) ($values[self::ADMIN_IPS_KEY] ?? []));
        $settings = [
            'site_restricted' => (bool) ($values[self::SITE_RESTRICTED_KEY] ?? false),
            'site_ips' => $siteIps,
            'site_text' => (string) ($values[self::SITE_TEXT_KEY] ?? implode("\n", $siteIps)),
            'admin_restricted' => (bool) ($values[self::ADMIN_RESTRICTED_KEY] ?? false),
            'admin_ips' => $adminIps,
            'admin_text' => (string) ($values[self::ADMIN_TEXT_KEY] ?? implode("\n", $adminIps)),
        ];

        Cache::forever(self::CACHE_KEY, $settings);

        return $settings;
    }

    /**
     * 画面の設定を保存して、操作ログに残す。IPアドレスは、画面で入力した文で受け取り、判断に使う一覧をそこから作る。
     * 正しくない行は、呼ぶ前にparseList()で確かめておく。ここでは読み飛ばす。
     */
    public static function save(bool $siteRestricted, string $siteText, bool $adminRestricted, string $adminText): void
    {
        $siteIps = self::parseList($siteText, $siteInvalid, $siteComments);
        $adminIps = self::parseList($adminText, $adminInvalid, $adminComments);

        Setting::put(self::SITE_RESTRICTED_KEY, $siteRestricted);
        Setting::put(self::SITE_IPS_KEY, $siteIps);
        Setting::put(self::SITE_TEXT_KEY, $siteText);
        Setting::put(self::ADMIN_RESTRICTED_KEY, $adminRestricted);
        Setting::put(self::ADMIN_IPS_KEY, $adminIps);
        Setting::put(self::ADMIN_TEXT_KEY, $adminText);

        Cache::forget(self::CACHE_KEY);
        GateState::exportOrReport();

        OperationRecorder::record(OperationLogAction::AccessRestrictionChange, detail: [
            'site' => 'メンテナンス画面：'.($siteRestricted ? '表示する' : '表示しない'),
            'site_ips' => 'メンテナンス中も訪問者画面を閲覧できるIPアドレス：'.self::listForLog($siteIps, $siteComments),
            'admin_ips' => '常にサイト全体を閲覧できるIPアドレス：'.self::listForLog($adminIps, $adminComments),
            'admin' => '管理画面の存在：'.($adminRestricted ? '隠す' : '隠さない'),
        ]);
    }

    /**
     * 操作ログに残す、IPアドレスの並び。コメントがあれば、IPアドレスの後ろに括弧で付ける
     *
     * @param  string[]  $ips
     * @param  array<string, string>  $comments
     */
    private static function listForLog(array $ips, array $comments): string
    {
        if ($ips === []) {
            return 'なし';
        }

        return implode(' ', array_map(fn (string $ip) => isset($comments[$ip]) ? "{$ip}（{$comments[$ip]}）" : $ip, $ips));
    }

    /**
     * 締め出されたときの非常口。サイトと管理画面の制限を、両方外す。
     * IPアドレスの一覧は、次に制限を掛けるときのために残す。app:access-denied-offが呼ぶ。
     */
    public static function turnOff(): void
    {
        $settings = self::settings();

        self::save(false, $settings['site_text'], false, $settings['admin_text']);
    }

    // ---- 入力の確かめ ----

    /**
     * 画面で入れた文を、IPアドレスの一覧にする。1行に1つ。# から後ろはコメントで、行の頭が # の行と空の行は読み飛ばす。
     * 正しくない形のものは$invalidに、コメントはIPアドレスをキーにして$commentsに入れて返す。
     *
     * @param  string[]  $invalid
     * @param  array<string, string>  $comments
     * @return string[]
     */
    public static function parseList(?string $text, ?array &$invalid = null, ?array &$comments = null): array
    {
        $invalid = [];
        $comments = [];
        $ips = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            // # から後ろを、コメントとして分ける
            [$entry, $comment] = str_contains($line, '#') ? explode('#', $line, 2) : [$line, ''];
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $normalized = self::normalize($entry);
            if ($normalized === null) {
                $invalid[] = trim($comment) !== '' ? $entry.'（'.trim($comment).'）' : $entry;

                continue;
            }

            $ips[$normalized] = $normalized;
            if (trim($comment) !== '') {
                $comments[$normalized] = trim($comment);
            }
        }

        return array_values($ips);
    }

    /**
     * 保存すると、今の自分のIPアドレスで管理画面を開けなくなるなら、その理由。開けるならnull。
     * サイトの制限は管理画面に影響しないので、管理画面の制限だけを確かめる。
     */
    public static function lockoutMessage(bool $adminRestricted, array $adminIps, string $currentIp): ?string
    {
        if ($adminRestricted && ! self::matches($currentIp, [...$adminIps, ...self::envAdminIps()])) {
            return "あなたのIPアドレス（{$currentIp}）が「常にサイト全体を閲覧できるIPアドレス」に無いので、保存すると管理画面を開けなくなります。";
        }

        return null;
    }

    /** IPアドレスが、一覧のどれかに当たるか */
    private static function matches(string $ip, array $ranges): bool
    {
        return Gate::inRange($ip, $ranges);
    }

    /** IPアドレスか、192.0.2.0/24のようなまとまりの形をそろえる。正しくない形ならnull */
    private static function normalize(string $entry): ?string
    {
        [$address, $prefix] = str_contains($entry, '/') ? explode('/', $entry, 2) : [$entry, null];

        $packed = @inet_pton($address);
        if ($packed === false) {
            return null;
        }

        // IPv6は、書き方の揺れ（0の省略や大文字）をそろえる
        $address = inet_ntop($packed);
        if ($prefix === null) {
            return $address;
        }

        $max = strlen($packed) === 4 ? 32 : 128;
        if (! ctype_digit($prefix) || (int) $prefix > $max) {
            return null;
        }

        return $address.'/'.(int) $prefix;
    }
}
