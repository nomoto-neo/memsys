<?php

namespace Neobit\Core\Seo;

use Illuminate\Database\Eloquent\Builder;

/**
 * XMLのサイトマップ（/sitemap.xml）に載せる記事のモデル。設定のseo.sitemap_modelsに並べる。
 * 記事の更新日は、モデルのupdated_atを使う。記事のSEOの欄（HasSeoMeta）で検索に出さないにした記事は、載せない。
 */
interface SitemapSource
{
    /** 訪問者に見せてよい記事のクエリ。非表示・会員限定・掲載期間の外の記事は入れない */
    public static function sitemapQuery(): Builder;

    /** 記事の訪問者の側のURL */
    public function sitemapUrl(): string;
}
