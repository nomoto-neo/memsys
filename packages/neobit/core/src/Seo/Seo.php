<?php

namespace Neobit\Core\Seo;

use LogicException;

/**
 * 今のページのSEOの値（タイトル・説明文・OGP）を決める。訪問者の側のレイアウトの<head>の部品
 * （neobit::_seo_head）と、ヘルパーのseo()がここを通る。
 *
 * コントローラーの詳細の画面でSeo::for($news)を呼ぶと、その記事（SeoSubject）から値を決める。記事にSEOの欄
 * （HasSeoMeta）の値があれば、そちらを優先する。呼ばなければ、管理画面の「SEO設定」のページごとの値、
 * 画面の@section('title')、サイトの既定の順に決める。サイトの既定は「SEO設定」の値で、空欄なら設定ファイル
 * （config/neobit.phpのseo）の値（Neobit\Core\Seo\SeoSettings）。
 * 記事は今のリクエストの属性に持つ。リクエストが変われば、前の記事は引き継がない。
 *
 * 検索に出さない（robotsのnoindex）は、次のどれかなら自動で付く。
 * - ルートにauthのミドルウェアが付いた画面（ログインしないと開けない画面）
 * - コントローラーでSeo::noindex()を呼んだ画面（検索や絞り込みをした一覧など）
 * - 設定のseo.noindex_routesに並べたルートの名前（*が使える。お礼の画面など）
 * - 「SEO設定」のページごとの値で、検索に出さないにしたもの
 * - 記事のSEOの欄で、検索に出さないにしたもの
 */
final class Seo
{
    /** ヘルパーのseo()で取り出せる値の名前 */
    public const KEYS = ['title', 'description', 'image', 'url', 'canonical', 'type', 'site_name', 'robots', 'site_verification'];

    /** シェアの画像のURLの形。サイトの中のパス（/から始まる）か、http(s)://から始まるURL。記事のSEOの欄と「SEO設定」の検証が使う */
    public const IMAGE_URL_PATTERN = '#^(/(?!/)|https?://)\S*$#';

    /** 記事を持つ、リクエストの属性の名前 */
    private const ATTRIBUTE = 'neobit.seo.subject';

    /** 検索に出さない印を持つ、リクエストの属性の名前 */
    private const NOINDEX_ATTRIBUTE = 'neobit.seo.noindex';

    /** 今のページの記事を決める。コントローラーの詳細の画面で呼ぶ */
    public static function for(SeoSubject $subject): void
    {
        request()->attributes->set(self::ATTRIBUTE, $subject);
    }

    /** 今のページを検索に出さない。検索や絞り込みをした一覧のように、URLだけでは見分けられない画面で、コントローラーが呼ぶ */
    public static function noindex(): void
    {
        request()->attributes->set(self::NOINDEX_ATTRIBUTE, true);
    }

    /** 今のページの記事。無ければnull */
    private function subject(): ?SeoSubject
    {
        return request()->attributes->get(self::ATTRIBUTE);
    }

    /** 今のページの記事のSEOの欄の値。$columnはtitle・description・image・noindex。記事にSEOの欄が無ければnull */
    private function meta(string $column): mixed
    {
        $subject = $this->subject();

        return $subject !== null && method_exists($subject, 'seoMeta') ? $subject->seoMeta?->{$column} : null;
    }

    /** 「SEO設定」の、今のページのルートの値。$columnはtitle・description・image・noindex。設定が無ければnull */
    private function page(string $column): mixed
    {
        $name = request()->route()?->getName();

        return $name !== null ? SeoSettings::page($name)[$column] ?? null : null;
    }

    /** 値を1つ返す。$keyはKEYSのどれか。知らない名前は書き間違いなので例外にする */
    public function value(string $key): string
    {
        return match ($key) {
            'title' => $this->title(),
            'description' => $this->description(),
            'image' => $this->image(),
            'url' => url()->current(),
            'canonical' => $this->canonical(),
            'type' => $this->subject() !== null ? 'article' : 'website',
            'site_name' => (string) config('app.site_name'),
            'robots' => $this->isNoindex() ? 'noindex' : '',
            'site_verification' => SeoSettings::siteVerification(),
            default => throw new LogicException("seo()の名前には、'".implode("'・'", self::KEYS)."'のどれかを渡してください：{$key}"),
        };
    }

    /**
     * 正規のURL。今のURLから?の後ろを除く。ページ送りの?page=だけは残す。
     * 一覧の2ページ目以降も、1ページ目とは別のページとして検索に出すため
     */
    private function canonical(): string
    {
        $page = request()->query('page');

        return url()->current().(is_scalar($page) && (string) $page !== '' ? '?page='.urlencode((string) $page) : '');
    }

    /** 検索に出さないページか。決まりはクラスの説明のとおり */
    private function isNoindex(): bool
    {
        if ($this->meta('noindex') || $this->page('noindex') || request()->attributes->get(self::NOINDEX_ATTRIBUTE)) {
            return true;
        }

        $route = request()->route();
        if ($route === null) {
            return false;
        }

        // ログインしないと開けない画面
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
                return true;
            }
        }

        // 設定に並べたルートの名前
        $patterns = (array) config('neobit.seo.noindex_routes');

        return $patterns !== [] && $route->named(...$patterns);
    }

    /**
     * タイトル。SEOの欄のタイトル、記事のタイトル、「SEO設定」のページのタイトル、画面の@section('title')の順で
     * 最初にあるものを、タイトルの形に当てはめる。「SEO設定」を@section('title')より先にするのは、
     * 運用する人が画面で入れた値だから。どれも無ければサイト名だけ
     */
    private function title(): string
    {
        $site = (string) config('app.site_name');
        $title = $this->meta('title') ?? $this->subject()?->seoTitle() ?? $this->page('title') ?? trim((string) app('view')->yieldContent('title'));

        if ($title === '') {
            return $site;
        }

        return strtr(SeoSettings::titleFormat(), ['{title}' => $title, '{site}' => $site]);
    }

    /**
     * 説明文。記事の本文のタグを除き、改行と続く空白を1つにまとめて、先頭の決めた文字数にする。
     * 長ければ末尾に「…」を付ける。記事が無いか本文が空なら、「SEO設定」のページの説明文、サイトの既定の説明文の順。
     * scriptとstyleは、タグだけを除くと中身が文として残るので、中身ごと除く。段落や改行のタグは、
     * 除くと前後の文がくっつくので、空白にする
     */
    private function description(): string
    {
        // SEOの欄に入れた説明文は、そのまま使う
        if (filled($this->meta('description'))) {
            return (string) $this->meta('description');
        }

        $source = (string) $this->subject()?->seoDescriptionSource();
        $source = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $source);
        $source = (string) preg_replace('#</(p|div|li|h[1-6]|tr|td|th|blockquote)\s*>|<br\s*/?>#i', ' ', $source);
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($text === '') {
            return $this->page('description') ?? SeoSettings::description();
        }

        // 文字の数で切る（全角も1文字）
        $length = (int) config('neobit.seo.description_length');

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length).'…' : $text;
    }

    /** シェアの画像の絶対のURL。SEOの欄の画像、記事の画像、「SEO設定」のページの画像、サイトの既定の画像の順。どれも無ければ空 */
    private function image(): string
    {
        $image = $this->meta('image') ?? $this->subject()?->seoImageUrl() ?? $this->page('image') ?? SeoSettings::image();

        return $image === null || $image === '' ? '' : url($image);
    }
}
