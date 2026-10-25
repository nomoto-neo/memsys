<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\Response;

/**
 * 検索エンジンへの巡回のしかた（GET /robots.txt）。public/robots.txtを置くとApacheがそちらを返すので、置かない。
 * サイトマップの行に、環境ごとの絶対のURLを書くため、ファイルではなくここで作る。
 * 本番（APP_ENV=production）は、設定のseo.robots_disallowの場所を巡回させず、サイトマップの場所を伝える。
 * 本番以外（開発サーバーなど）は、全部を巡回させない。開発サーバーが検索に出ないようにするため。
 */
class RobotsController
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            foreach ((array) config('neobit.seo.robots_disallow') as $path) {
                $lines[] = "Disallow: {$path}";
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('seo.sitemap');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
