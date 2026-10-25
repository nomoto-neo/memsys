<?php

namespace Neobit\Core\Seo;

/**
 * SEOの値の元になる記事。モデルが実装し、コントローラーの詳細の画面でSeo::for()に渡す。
 * 空欄のときの補い（説明文は本文の先頭、画像はサイトの既定）は、Seoが行う。
 */
interface SeoSubject
{
    /** ページのタイトル。サイトの設定のタイトルの形（{title}｜{site}）に当てはめて出す */
    public function seoTitle(): string;

    /** 説明文の元になる文。HTMLでもよい。タグを除き、先頭の決めた文字数を使う。無ければnull */
    public function seoDescriptionSource(): ?string;

    /** シェアの画像のURL。無ければnull（サイトの既定の画像を使う） */
    public function seoImageUrl(): ?string;
}
