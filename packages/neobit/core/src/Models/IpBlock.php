<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * IPブロック管理で、アクセスを止めているIPアドレスの1行。Neobit\Core\Security\IpBlockerが作って消す。
 * 解除したら行を消すので、残っている行は、止めているものか、期限が過ぎて片付けを待っているもの。
 */
class IpBlock extends Model
{
    protected $table = 'ip_blocks';

    protected $fillable = [
        'ip',
        'auto',
        'reason',
        'hits',
        'blocked_until',
        'registered_by',
    ];

    protected $casts = [
        'auto' => 'boolean',
        'hits' => 'integer',
        'blocked_until' => 'datetime',
    ];

    /** 今止めている行。期限が無いか、期限がまだ来ていないもの */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()));
    }
}
