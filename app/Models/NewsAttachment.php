<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Neobit\Core\Upload\UploadFilePath;

/**
 * ニュース記事に付いた添付ファイル1件分。fileは保存したファイル名、file_originは表示名。
 * 保存・削除はNeobit\Core\Crud\RepeatableGroupが、News::attach()のリレーションを通して行う。
 */
class NewsAttachment extends Model
{
    protected $table = 't_news_attachments';

    protected $fillable = [
        'news_id',
        'file',
        'file_origin',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class, 'news_id');
    }

    /**
     * 添付ファイルのURL。画面からは$attachment->urlで読める。
     * 親の記事を読み込まず、news_idだけでURLを作る。添付が何件あっても、
     * URLのために余計なSQLを出さないようにするため。
     * 欄の名前は、NewsControllerのUPLOAD_FILESの'attach.*.file'から決まるattach_file。
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(News::class, $this->news_id, 'attach_file', $this->file),
        );
    }
}
