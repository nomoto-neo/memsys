<?php

namespace Neobit\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 管理画面の「ベーシック認証」のユーザーの1行。範囲（site・admin）ごとのユーザー。
 * Neobit\Core\Security\BasicAuthが作って消し、そのたびにその範囲の.htpasswdを書き直す。パスワードはbcryptのハッシュで持つ。
 */
class BasicAuthUser extends Model
{
    protected $table = 'basic_auth_users';

    protected $fillable = [
        'area',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
