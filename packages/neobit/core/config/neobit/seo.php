<?php

/**
 * SEO（ページのタイトル・説明文・OGP）の既定の値。アプリのconfig/neobit.phpのseoの節に書いたキーが、これに優先する。
 * 訪問者の側のレイアウトの<head>の部品（neobit::_seo_head）と、ヘルパーのseo()が使う。
 * 記事の詳細の画面では、記事（Neobit\Core\Seo\SeoSubject）の値が優先する。
 * title_format・description・image_url・organization_name・organization_logo_urlは、管理画面の「SEO設定」で空欄のときの値。
 */
return [

    // タイトルの形。{title}はページのタイトル、{site}はサイト名（neobit.site.name）
    'title_format' => '{title}｜{site}',

    // 記事の無いページの説明文。空なら説明文のタグを出さない
    'description' => '',

    // 記事の本文から作る説明文の文字数
    'description_length' => 120,

    // 既定のシェアの画像。publicの下のパス（例：/images/ogp.png）か、URL。nullなら出さない
    'image_url' => null,

    // 構造化データで出す運営者の名前。空ならサイト名
    'organization_name' => '',

    // 構造化データで出す運営者のロゴ。publicの下のパスか、URL。nullなら出さない
    'organization_logo_url' => null,

    // 検索に出さないルートの名前。*が使える（例：'contact.thanks'）
    'noindex_routes' => [],

    // サイトマップ（/sitemap.xml）に載せる、記事を持たないページのルートの名前
    'sitemap_routes' => [],

    // サイトマップに載せる記事のモデル（Neobit\Core\Seo\SitemapSourceを実装したもの）
    'sitemap_models' => [],

    // robots.txtで巡回させない場所。本番（APP_ENV=production）だけ使い、本番以外は全部を巡回させない。
    // 管理画面は書かない（URLを教えることになるため。管理画面はX-Robots-Tagで検索に出さない）
    'robots_disallow' => [],

    // 管理画面の「SEO設定」に出す、記事を持たないページ。ルート名 => 画面に出す名前。引数の要らないルートだけ
    'page_routes' => [],

    // 管理画面の「リダイレクト」で登録した移し方を当てるか。falseなら登録を見ずに、
    // URLの末尾の/を外す301だけをする（Neobit\Core\Http\Middleware\ApplyRedirects）
    'redirects' => false,

];
