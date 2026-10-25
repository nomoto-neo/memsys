<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\Response;
use LogicException;
use Neobit\Core\Seo\SeoSettings;
use Neobit\Core\Seo\SitemapSource;

/**
 * XMLのサイトマップ（GET /sitemap.xml）。検索エンジンに、公開しているページのURLと更新日を伝える。
 * 載せるのは、設定のseo.sitemap_routesのルート（記事を持たないページ）と、seo.sitemap_modelsのモデルの記事。
 * ルートは、管理画面の「SEO設定」で検索に出さないにしたものを除く。
 * 記事は、モデルのsitemapQuery()（訪問者に見せてよい記事）のうち、SEOの欄で検索に出さないにしたものを除く。
 * 記事が数千件までのサイトを前提に、毎回その場で作る。
 */
class SitemapController
{
    /** 1回に読む記事の数。記事が多くても、まとめて読み込まないため */
    private const CHUNK = 500;

    public function __invoke(): Response
    {
        $urls = [];

        // 記事を持たないページ。「SEO設定」で検索に出さないにしたものは除く
        foreach ((array) config('neobit.seo.sitemap_routes') as $name) {
            if (SeoSettings::page($name)['noindex'] ?? false) {
                continue;
            }
            $urls[] = ['loc' => route($name), 'lastmod' => null];
        }

        // 記事
        foreach ((array) config('neobit.seo.sitemap_models') as $class) {
            if (! is_subclass_of($class, SitemapSource::class)) {
                throw new LogicException("設定のseo.sitemap_modelsの{$class}は、Neobit\\Core\\Seo\\SitemapSourceを実装していません。");
            }

            $query = $class::sitemapQuery();

            // SEOの欄で検索に出さないにした記事は除く
            if (method_exists($class, 'seoMeta')) {
                $query->whereDoesntHave('seoMeta', fn ($meta) => $meta->where('noindex', true));
            }

            $query->chunkById(self::CHUNK, function ($records) use (&$urls) {
                foreach ($records as $record) {
                    $updatedAt = $record->usesTimestamps() ? $record->{$record->getUpdatedAtColumn()} : null;
                    $urls[] = ['loc' => $record->sitemapUrl(), 'lastmod' => $updatedAt?->toAtomString()];
                }
            });
        }

        return response()
            ->view('neobit::sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
