<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Neobit\Core\Seo\RedirectResolver;

/**
 * 管理画面の「リダイレクト」で登録する、古いURLから新しいURLへの移し方の1行。
 * 当て方はNeobit\Core\Seo\RedirectResolver、登録のときの確かめはNeobit\Core\Seo\RedirectChecker。
 * 操作ログの対象にするので、アプリはモデルの短い名前（enforceMorphMap()）にredirectを足す。
 * 保存と削除のたびに、当てるときに使う一覧のキャッシュを作り直す。画面・取り込み・コマンドのどこから変えても、
 * 古い一覧が残らないようにするため。当たった回数の更新は、モデルを通さないので作り直さない。
 */
class Redirect extends Model
{
    /** 操作ログの変わった列に数えない列。当たるたびに変わるため */
    public const OPERATION_LOG_IGNORE = ['hits', 'last_hit_at'];

    protected $table = 'redirects';

    protected $fillable = [
        'old_url',
        'new_url',
        'status_code',
        'enabled',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'enabled' => 'boolean',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    /** 保存と削除のたびに、当てるときに使う一覧のキャッシュを作り直す */
    protected static function booted(): void
    {
        static::saved(fn () => RedirectResolver::forget());
        static::deleted(fn () => RedirectResolver::forget());
    }
}
