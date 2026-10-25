<?php

namespace Neobit\Core\Seo;

use LogicException;

/**
 * 今のページのSEOの値（タイトル・説明文・OGP）を決める。訪問者の側のレイアウトの<head>の部品
 * （neobit::_seo_head）と、ヘルパーのseo()がここを通る。
 *
 * コントローラーの詳細の画面でSeo::for($news)を呼ぶと、その記事（SeoSubject）から値を決める。記事にSEOの欄
 * （HasSeoMeta）の値があれば、そちらを優先する。呼ばなければ、画面の@section('title')とサイトの既定
 * （config/neobit.phpのseo）から決める。
 * 記事は今のリクエストの属性に持つ。リクエストが変われば、前の記事は引き継がない。
 */
final class Seo
{
    /** ヘルパーのseo()で取り出せる値の名前 */
    public const KEYS = ['title', 'description', 'image', 'url', 'type', 'site_name', 'robots'];

    /** 記事を持つ、リクエストの属性の名前 */
    private const ATTRIBUTE = 'neobit.seo.subject';

    /** 今のページの記事を決める。コントローラーの詳細の画面で呼ぶ */
    public static function for(SeoSubject $subject): void
    {
        request()->attributes->set(self::ATTRIBUTE, $subject);
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

    /** 値を1つ返す。$keyはKEYSのどれか。知らない名前は書き間違いなので例外にする */
    public function value(string $key): string
    {
        return match ($key) {
            'title' => $this->title(),
            'description' => $this->description(),
            'image' => $this->image(),
            'url' => url()->current(),
            'type' => $this->subject() !== null ? 'article' : 'website',
            'site_name' => (string) config('app.site_name'),
            'robots' => $this->meta('noindex') ? 'noindex' : '',
            default => throw new LogicException("seo()の名前には、'".implode("'・'", self::KEYS)."'のどれかを渡してください：{$key}"),
        };
    }

    /**
     * タイトル。SEOの欄のタイトル、記事のタイトル、画面の@section('title')のどれかを、サイトの設定のタイトルの形に当てはめる。
     * どちらも無ければサイト名だけ
     */
    private function title(): string
    {
        $site = (string) config('app.site_name');
        $title = $this->meta('title') ?? $this->subject()?->seoTitle() ?? trim((string) app('view')->yieldContent('title'));

        if ($title === '') {
            return $site;
        }

        return strtr((string) config('neobit.seo.title_format'), ['{title}' => $title, '{site}' => $site]);
    }

    /**
     * 説明文。記事の本文のタグを除き、改行と続く空白を1つにまとめて、先頭の決めた文字数にする。
     * 長ければ末尾に「…」を付ける。記事が無いか本文が空なら、サイトの既定の説明文。
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
            return (string) config('neobit.seo.description');
        }

        // 文字の数で切る（全角も1文字）
        $length = (int) config('neobit.seo.description_length');

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length).'…' : $text;
    }

    /** シェアの画像の絶対のURL。SEOの欄の画像、記事の画像、サイトの既定の画像の順。どれも無ければ空 */
    private function image(): string
    {
        $image = $this->meta('image') ?? $this->subject()?->seoImageUrl() ?? config('neobit.seo.image');

        return $image === null || $image === '' ? '' : url($image);
    }
}
