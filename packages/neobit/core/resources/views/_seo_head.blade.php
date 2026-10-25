{{--
    訪問者の側のレイアウトの<head>に置く、SEOのタグ一式（タイトル・説明文・OGP・Xのカード）。
    値はヘルパーのseo()が決める（Neobit\Core\Seo\Seo）。記事の詳細の画面ではコントローラーが渡した記事から、
    ほかの画面では@section('title')とサイトの既定（config/neobit.phpのseo）から決まる。
    値が空のタグは出さない。並べ方を変えたいときは、この部品を使わずに、レイアウトでseo()を使って書く。
--}}
@php
    $seoDescription = seo('description');
    $seoImage = seo('image');
@endphp
<title>{{ seo('title') }}</title>
{{-- 検索に出さないページ。ログインが要る画面・Seo::noindex()を呼んだ画面・設定のseo.noindex_routes・記事のSEOの欄 --}}
@if (seo('robots') !== '')
    <meta name="robots" content="{{ seo('robots') }}">
@endif
@if ($seoDescription !== '')
    <meta name="description" content="{{ $seoDescription }}">
@endif
<meta property="og:title" content="{{ seo('title') }}">
@if ($seoDescription !== '')
    <meta property="og:description" content="{{ $seoDescription }}">
@endif
<link rel="canonical" href="{{ seo('canonical') }}">
<meta property="og:url" content="{{ seo('url') }}">
<meta property="og:type" content="{{ seo('type') }}">
<meta property="og:site_name" content="{{ seo('site_name') }}">
@if ($seoImage !== '')
    <meta property="og:image" content="{{ $seoImage }}">
@endif
{{-- 画像があれば大きな画像のカード、無ければ小さなカード --}}
<meta name="twitter:card" content="{{ $seoImage !== '' ? 'summary_large_image' : 'summary' }}">
