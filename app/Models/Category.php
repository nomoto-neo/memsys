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
     * このカテゴリーが付いているニュース記事。
     *
     * belongsToMany()の第3・第4引数は、中間テーブル側の列名
     * （こちら側のキー・相手側のキー）。デフォルトの推測（category_id・
     * news_id）と実際の列名が一致しているので、本来省略してもよいが、
     * 「どのテーブルのどの列を見ているか」をコードだけで分かるように
     * 明示している。
     */
    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 't_news_category', 'category_id', 'news_id');
    }
}
