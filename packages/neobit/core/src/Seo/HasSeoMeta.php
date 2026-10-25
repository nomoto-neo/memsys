<?php

namespace Neobit\Core\Seo;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Neobit\Core\Models\SeoMeta;

/**
 * 記事ごとのSEOの欄（共通のテーブルseo_metas）を持つモデルに付ける。SeoSubjectと一緒に使う。
 * モデルの短い名前（Relation::enforceMorphMap()）に、このモデルを登録しておく。
 * SEOの欄に値があれば、Seoはそちらを優先し、空欄の項目はSeoSubjectのメソッドから補う。
 */
trait HasSeoMeta
{
    /** この記事のSEOの欄。入れていなければ行が無い */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}
