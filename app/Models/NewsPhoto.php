<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Neobit\Core\Upload\UploadFilePath;

/**
 * ニュース記事の写真1枚分。画像とキャプションの組。
 * 保存・削除はNeobit\Core\Crud\RepeatableGroupが、News::photos()のリレーションを通して行う。
 */
class NewsPhoto extends Model
{
    protected $table = 't_news_photos';

    protected $fillable = [
        'news_id',
        'image',
        'image_origin',
        'caption',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class, 'news_id');
    }

    /**
     * 画像のURL。画像が無ければnull。画面からは$photo->image_urlで読める。
     * 親の記事を読み込まず、news_idだけでURLを作る。写真が何枚あっても、URLのために余計なSQLを出さないようにするため。
     * 欄の名前は、NewsControllerのUPLOAD_FILESの'photos.*.image'から決まるphotos_image。
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(News::class, $this->news_id, 'photos_image', $this->image),
        );
    }
}
