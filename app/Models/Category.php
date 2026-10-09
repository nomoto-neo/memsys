<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ニュースカテゴリー。
 */
class Category extends Model
{
    protected $table = 't_categories';

    protected $fillable = [
        'name',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    /**
     * このカテゴリーが付いているニュース記事。中間テーブルの列名は、省略しても
     * 同じになるが、どの列を見ているかがコードだけで分かるように書いている。
     */
    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 't_news_category', 'category_id', 'news_id');
    }
}
