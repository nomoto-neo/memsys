<?php

namespace Neobit\Core\Seo;

use DateTimeInterface;

/**
 * 構造化データで記事（Article）として出す記事。公開日と更新日を返す。
 * 日付を持たない固定ページなどに実装を求めないよう、SeoSubjectとは別に持つ。実装しないモデルの記事は、記事として出さない。
 */
interface SeoArticle extends SeoSubject
{
    /** 公開日。無ければnull */
    public function seoPublishedAt(): ?DateTimeInterface;

    /** 更新日。無ければnull */
    public function seoModifiedAt(): ?DateTimeInterface;
}
