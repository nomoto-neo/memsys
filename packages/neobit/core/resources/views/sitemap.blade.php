{{--
    XMLのサイトマップ（Neobit\Core\Http\Controllers\SitemapController）。
    $urlsは[['loc' => URL, 'lastmod' => 更新日（ISO 8601）かnull], …]。
--}}
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if ($url['lastmod'] !== null)
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
