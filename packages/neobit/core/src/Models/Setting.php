<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 管理画面から変えられる設定の1行。キーと値の組で、値はJSONで持つので、真偽値や配列も入る。
 * 毎回のアクセスで読む設定は、使う側がキャッシュに置く（例：Neobit\Core\Security\AccessRestriction）。
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * いくつかの設定の値をまとめて読む。キー => 値。行の無いキーは入らない。
     *
     * @param  string[]  $keys
     */
    public static function values(array $keys): array
    {
        return static::query()->whereIn('key', $keys)->pluck('value', 'key')->all();
    }

    /** 設定の値を書く。行が無ければ作る */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
