<?php

namespace Neobit\Core\Seo;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 管理画面の「リダイレクト」で登録する前の確かめ。画面からの登録・編集と、CSVの取り込みで同じものを使う。
 * .htaccessのリダイレクトで起きやすい、思った動きにならない登録を、保存する前に止めるため。
 *
 * 確かめる順は次のとおりで、項目ごとに最初のエラーだけを返す。
 * 1. 形。古いURLは/から始め、?と#は書けない。*は末尾に1つだけ。古いURLに*が無ければ、新しいURLにも書けない
 * 2. 同じ古いURLの二重の登録
 * 3. 古いURLと新しいURLが同じ
 * 4. 古いURLが、今ある引数の無い画面のURLと重なる。*なら、巻き込む画面
 * 5. 輪（移した先がまた自分に当たる）と、つながり（移した先がほかの移し方に当たる。直す相手を示す）
 * 6. 新しいURLが、サイトの中で開けない。引数のあるルートでは、記事が本当にあるかまでは確かめない
 * 4から6は、新しいURLがサイトの中のパスのときだけ。外のURLへ移すものは確かめない。
 */
final class RedirectChecker
{
    // ---- 形 ----

    /** 新しいURLに書ける、外のURLの形 */
    private const EXTERNAL_PATTERN = '#^https?://[^\s/?\#]+\S*$#';

    /** エラーの文に並べる、巻き込む画面の数の上限 */
    private const MAX_LISTED_ROUTES = 5;

    // ---- 確かめ ----

    /**
     * 登録してよいかを確かめ、項目 => エラーの文を返す。無ければ空。$old・$newはRedirectResolver::normalize()で
     * そろえたもの。$othersは、自分以外の登録（登録済みのものと、CSVのほかの行）。
     *
     * @param  list<array{old_url: string, new_url: string}>  $others
     * @return array<string, string>  old_url・new_url => エラーの文
     */
    public static function errors(string $old, string $new, array $others): array
    {
        // 形
        $errors = self::formatErrors($old, $new);
        if ($errors !== []) {
            return $errors;
        }

        // 同じ古いURLの二重の登録
        foreach ($others as $other) {
            if ($other['old_url'] === $old) {
                return ['old_url' => 'この古いURLは、もう登録されています。'];
            }
        }

        // 古いURLと新しいURLが同じ
        if ($old === $new) {
            return ['new_url' => '古いURLと新しいURLが同じです。'];
        }

        // 今ある画面との重なり
        $conflicts = self::conflictingRoutes($old);
        if ($conflicts !== []) {
            $listed = implode('・', array_slice($conflicts, 0, self::MAX_LISTED_ROUTES)).(count($conflicts) > self::MAX_LISTED_ROUTES ? 'ほか' : '');

            // *のものは、その下に入る画面を巻き込む
            if (RedirectResolver::isWildcard($old)) {
                return ['old_url' => "この古いURLは、今ある画面（{$listed}）を巻き込みます。"];
            }

            return ['old_url' => "この古いURLは、今ある画面（{$listed}）と重なります。"];
        }

        // ここから先は、サイトの中へ移すものだけ
        if (! str_starts_with($new, '/')) {
            return [];
        }

        return self::chainErrors($old, $new, $others) ?: self::targetErrors($new);
    }

    /** 形の確かめ。古いURL・新しいURLの書き方と、*の書き方 */
    private static function formatErrors(string $old, string $new): array
    {
        $errors = [];

        // 古いURL。/から始まるパスで、?・#・空白は書けない
        if (! str_starts_with($old, '/') || str_starts_with($old, '//') || preg_match('/[?#\s]/u', $old)) {
            $errors['old_url'] = '古いURLは、/ から始めてください。? と # は書けません。';
        } elseif (! self::wildcardIsAtEnd($old)) {
            $errors['old_url'] = '* は、末尾に1つだけ書けます。';
        }

        // 新しいURL。サイトの中のパスか、外のURL
        $isPath = str_starts_with($new, '/') && ! str_starts_with($new, '//') && ! preg_match('/\s/u', $new);
        if (! $isPath && ! preg_match(self::EXTERNAL_PATTERN, $new)) {
            $errors['new_url'] = '新しいURLは、/ から始まるサイトの中のパスか、http(s):// から始まるURLにしてください。';
        } elseif (! self::wildcardIsAtEnd($new)) {
            $errors['new_url'] = '* は、末尾に1つだけ書けます。';
        } elseif (RedirectResolver::isWildcard($new) && ! RedirectResolver::isWildcard($old)) {
            $errors['new_url'] = '古いURLに * が無いときは、新しいURLに * を書けません。';
        }

        return $errors;
    }

    /** *が無いか、末尾に1つだけあるか */
    private static function wildcardIsAtEnd(string $url): bool
    {
        $count = substr_count($url, RedirectResolver::WILDCARD);

        return $count === 0 || ($count === 1 && RedirectResolver::isWildcard($url));
    }

    /**
     * 輪とつながりの確かめ。新しいURLの移した先が、自分やほかの移し方の古いURLに当たらないか。
     * 反対に、ほかの移し方の移した先が、この古いURLに当たらないか
     *
     * @param  list<array{old_url: string, new_url: string}>  $others
     */
    private static function chainErrors(string $old, string $new, array $others): array
    {
        [$landing, $landingIsWildcard] = self::landing($new);

        // 移した先が、また自分に当たる
        if (RedirectResolver::isWildcard($old) && self::reaches($landing, $landingIsWildcard, $old)) {
            return ['new_url' => "移した先が、また {$old} に当たり、くり返しになります。"];
        }

        foreach ($others as $other) {
            // ほかの移し方の移した先が、この古いURLに当たるか。外のURLへ移すものは当たらない
            $otherComesHere = false;
            if (str_starts_with($other['new_url'], '/')) {
                [$otherLanding, $otherLandingIsWildcard] = self::landing($other['new_url']);
                $otherComesHere = self::reaches($otherLanding, $otherLandingIsWildcard, $old);
            }

            // 移した先が、ほかの移し方に当たる。そこから自分に戻れば輪、戻らなければつながり
            if (self::reaches($landing, $landingIsWildcard, $other['old_url'])) {
                if ($otherComesHere) {
                    return ['new_url' => "移した先が、ほかのリダイレクト（{$other['old_url']} → {$other['new_url']}）を通ってこの古いURLに戻り、くり返しになります。"];
                }

                return ['new_url' => "移した先の {$landing} は、ほかのリダイレクト（{$other['old_url']} → {$other['new_url']}）に当たります。{$other['new_url']} へ直接移すようにしてください。"];
            }

            // ほかの移し方の移した先が、この古いURLに当たる
            if ($otherComesHere) {
                return ['old_url' => "この古いURLは、ほかのリダイレクト（{$other['old_url']} → {$other['new_url']}）の移した先です。そちらの新しいURLを先に直してください。"];
            }
        }

        return [];
    }

    /**
     * サイトの中の新しいURLの、移した先のパス（?と#の後ろを除き、末尾の*も除く）と、*で残りを引き継ぐか。
     *
     * @return array{0: string, 1: bool}
     */
    private static function landing(string $new): array
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $new);

        return [RedirectResolver::prefix($path), RedirectResolver::isWildcard($path)];
    }

    /**
     * 移した先が、ある古いURLに当たるか。移した先が*で残りを引き継ぐときは、引き継いだどれかが当たれば当たるものとする
     */
    private static function reaches(string $landing, bool $landingIsWildcard, string $old): bool
    {
        $prefix = RedirectResolver::prefix($old);

        // 古いURLが完全一致のもの
        if (! RedirectResolver::isWildcard($old)) {
            return $landingIsWildcard ? str_starts_with($old, $landing) : $landing === $old;
        }

        // 古いURLが*のもの
        return $landingIsWildcard
            ? str_starts_with($landing, $prefix) || str_starts_with($prefix, $landing)
            : str_starts_with($landing, $prefix);
    }

    /** 新しいURLが、サイトの中で開けるか。*で残りを引き継ぐものは、移した先が決まらないので確かめない */
    private static function targetErrors(string $new): array
    {
        [$path, $isWildcard] = self::landing($new);
        if ($isWildcard || self::opensPage($path)) {
            return [];
        }

        return ['new_url' => "新しいURLの {$path} は、サイトの中で開ける画面がありません。"];
    }

    // ---- ルート ----

    /**
     * パスで、サイトの画面かファイルが開くか。GETで当たるルートがあるか、publicの下にファイルがあるか。
     * 引数のあるルート（/{slug}など）にも当たるので、記事が本当にあるかまでは確かめない。画面の「URLを試す」も使う
     */
    public static function opensPage(string $path): bool
    {
        if (is_file(public_path(ltrim($path, '/')))) {
            return true;
        }

        try {
            app('router')->getRoutes()->match(Request::create(RedirectResolver::encodeLocation($path), 'GET'));

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    /**
     * 古いURLと重なる、今ある引数の無い画面のURL。完全一致なら同じURLの画面、*ならその下に入る画面。
     * 引数のある画面（/{slug}など）は、ほぼどのURLにも当たるので比べない
     *
     * @return list<string>
     */
    private static function conflictingRoutes(string $old): array
    {
        $prefix = RedirectResolver::prefix($old);
        $conflicts = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }

            $path = $route->uri() === '/' ? '/' : '/'.trim($route->uri(), '/');
            $overlaps = RedirectResolver::isWildcard($old) ? str_starts_with($path, $prefix) : $path === $old;
            if ($overlaps) {
                $conflicts[] = $path;
            }
        }

        sort($conflicts);

        return array_values(array_unique($conflicts));
    }
}
