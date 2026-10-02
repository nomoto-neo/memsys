<?php

namespace App\Models;

use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ニュース記事に付いた添付ファイル1件分。
 *
 * このモデル自体は薄いラッパーで、実際の保存・削除・並び替えのロジックは
 * すべてApp\Support\AjaxFileUploadトレイト側が持っている。ここでは
 * News::attach()からのhasMany経由でしか基本的に触らない想定。
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
     * 添付ファイルの公開URL。ビューからは $attachment->url で読める
     * （仕組みはNews::listImageUrl()のコメント参照）。
     *
     * ファイルは親の記事のディレクトリ（例: news/000/000012/）に置かれて
     * いるが、ここでは$this->newsで親のモデルを読み込まず、手元にある
     * news_idだけでURLを組み立てている。記事1件に添付が何件あっても、
     * URLのために余計なSQLが発行されないようにするため。
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(News::class, $this->news_id, $this->filename),
        );
    }
}
