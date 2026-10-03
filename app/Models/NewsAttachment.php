<?php

namespace App\Models;

use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ニュース記事に付いた添付ファイル1件分。
 * 保存・削除はApp\Support\AjaxFileUploadが、News::attach()のリレーションを通して行う。
 */
class NewsAttachment extends Model
{
    protected $table = 't_news_attachments';

    protected $fillable = [
        'news_id',
        'filename',
        'original_name',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class, 'news_id');
    }

    /**
     * 添付ファイルのURL。画面からは$attachment->urlで読める。
     * 親の記事を読み込まず、news_idだけでURLを作る。添付が何件あっても、
     * URLのために余計なSQLを出さないようにするため。
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(News::class, $this->news_id, 'attach', $this->filename),
        );
    }
}
