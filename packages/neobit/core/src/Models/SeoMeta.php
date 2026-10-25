<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 記事ごとのSEOの欄の1行。どのコーナーの記事でも、このテーブルに1記事1行で持つ。
 * 記事のモデルにはNeobit\Core\Seo\HasSeoMetaを付け、保存はコントローラーのNeobit\Core\Seo\SeoFieldsが行う。
 * 空欄の項目は、記事から自動で補う（Neobit\Core\Seo\Seo）。
 */
class SeoMeta extends Model
{
    protected $table = 'seo_metas';

    protected $fillable = [
        'title',
        'description',
        'image',
        'noindex',
    ];

    protected $casts = [
        'noindex' => 'boolean',
    ];

    /** この欄を持つ記事 */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
