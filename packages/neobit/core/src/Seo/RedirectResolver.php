<?php

namespace Neobit\Core\Seo;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Neobit\Core\Models\Redirect;

/**
 * 管理画面の「リダイレクト」で登録した移し方を、URLに当てる。ミドルウェアNeobit\Core\Http\Middleware\ApplyRedirectsと、
 * 画面の「URLを試す」が使う。
 *
 * 当たるのは、有効にした移し方のうち、次の順で最初のもの。登録した順番には頼らない。
 * 1. 古いURLと完全に一致するもの
 * 2. 末尾に*を書いた古いURLのうち、*の前の部分で始まるもの。複数あれば、*の前がいちばん長いもの
 * 大文字と小文字、末尾の/の有無は別物として比べる。URLは%の符号を戻した形（日本語のまま）で比べる。
 *
 * 新しいURLの末尾に*を書いたときだけ、古いURLの*に当たった残りを引き継ぐ。新しいURLに?が無ければ、
 * 来たリクエストの?の後ろを付け足す。
 *
 * 毎回のアクセスで読むので、有効な移し方の一覧はキャッシュに置き、モデルの保存と削除のたびに作り直す。
 * テーブルがまだ無いとき（マイグレーションの前）は、登録が無いものとして動く。
 */
final class RedirectResolver
{
    // ---- 設定の置き場所 ----

    /** 有効な移し方の一覧を置くキャッシュのキー */
    private const CACHE_KEY = 'neobit-redirects';

    // ---- 書き方 ----

    /** 古いURL・新しいURLの末尾に書く、その下の全部を表す印 */
    public const WILDCARD = '*';

    // ---- 当てる ----

    /**
     * パスに当たる移し方。当たらなければnull。$pathは%の符号を戻したパス、$queryは?の後ろ（?は含まない）。
     *
     * @return array{id: int, old_url: string, location: string, status_code: int, wildcard: bool}|null
     */
    public static function find(string $path, string $query = ''): ?array
    {
        $table = self::table();

        // 完全に一致するもの
        if (isset($table['exact'][$path])) {
            $redirect = $table['exact'][$path];

            return self::result($redirect, '', $query, false);
        }

        // *の前の部分で始まるもの。一覧は*の前が長い順に並んでいる
        foreach ($table['wildcards'] as $prefix => $redirect) {
            if (str_starts_with($path, $prefix)) {
                return self::result($redirect, substr($path, strlen($prefix)), $query, true);
            }
        }

        return null;
    }

    /** 当たった回数を1つ増やし、最後に当たった日時を今にする。更新日時は変えない（登録の変更ではないため） */
    public static function hit(int $id): void
    {
        Redirect::query()->whereKey($id)->toBase()->increment('hits', 1, ['last_hit_at' => now()]);
    }

    /** 有効な移し方の一覧を作り直す。Neobit\Core\Models\Redirectの保存と削除のたびに呼ばれる */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ---- 書き方の読み替え ----

    /**
     * 入れたURLを、保存と比べる形にそろえる。前後の空白を除き、/から始まるパスは%の符号を戻す（日本語のまま持つ）。
     * 外のURL（http(s)://）は、そのまま
     */
    public static function normalize(?string $url): string
    {
        $url = trim((string) $url);

        return str_starts_with($url, '/') ? rawurldecode($url) : $url;
    }

    /** 古いURLが、末尾に*を書いた、その下の全部に当たる形か */
    public static function isWildcard(string $url): bool
    {
        return str_ends_with($url, self::WILDCARD);
    }

    /** 末尾の*を除いた部分。*が無ければそのまま */
    public static function prefix(string $url): string
    {
        return self::isWildcard($url) ? substr($url, 0, -strlen(self::WILDCARD)) : $url;
    }

    /** Locationのヘッダーに書けるよう、日本語や空白などを%の符号にする。もとからある%の符号や記号は、そのまま */
    public static function encodeLocation(string $url): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7E]+/u', fn (array $matches) => rawurlencode($matches[0]), $url);
    }

    // ---- 一覧 ----

    /**
     * 有効な移し方の一覧。完全一致は古いURL => 中身、*のものは*の前の部分 => 中身で、*の前が長い順。
     *
     * @return array{exact: array<string, array{id: int, old_url: string, new_url: string, status_code: int}>, wildcards: array<string, array{id: int, old_url: string, new_url: string, status_code: int}>}
     */
    private static function table(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // テーブルがまだ無いとき（マイグレーションの前）は、登録が無いものとして動く。キャッシュには置かない
        try {
            $redirects = Redirect::query()->where('enabled', true)->get(['id', 'old_url', 'new_url', 'status_code']);
        } catch (QueryException) {
            return ['exact' => [], 'wildcards' => []];
        }

        $table = ['exact' => [], 'wildcards' => []];
        foreach ($redirects as $redirect) {
            $item = [
                'id' => $redirect->id,
                'old_url' => $redirect->old_url,
                'new_url' => $redirect->new_url,
                'status_code' => $redirect->status_code,
            ];

            if (self::isWildcard($redirect->old_url)) {
                $table['wildcards'][self::prefix($redirect->old_url)] = $item;
            } else {
                $table['exact'][$redirect->old_url] = $item;
            }
        }

        // *の前が長いものから比べる。いちばん絞り込んだものを当てるため
        uksort($table['wildcards'], fn (string $a, string $b) => strlen($b) <=> strlen($a));

        Cache::forever(self::CACHE_KEY, $table);

        return $table;
    }

    /**
     * 当たった移し方から、移す先を組み立てる。$restは古いURLの*に当たった残り。
     *
     * @param  array{id: int, old_url: string, new_url: string, status_code: int}  $redirect
     * @return array{id: int, old_url: string, location: string, status_code: int, wildcard: bool}
     */
    private static function result(array $redirect, string $rest, string $query, bool $wildcard): array
    {
        $location = $redirect['new_url'];

        // 新しいURLの末尾に*があれば、当たった残りを引き継ぐ
        if (self::isWildcard($location)) {
            $location = self::prefix($location).$rest;
        }

        // 新しいURLに?が無ければ、来たリクエストの?の後ろを付け足す
        if ($query !== '' && ! str_contains($location, '?')) {
            $location .= '?'.$query;
        }

        return [
            'id' => $redirect['id'],
            'old_url' => $redirect['old_url'],
            'location' => $location,
            'status_code' => $redirect['status_code'],
            'wildcard' => $wildcard,
        ];
    }
}
