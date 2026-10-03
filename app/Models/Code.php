<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DBで管理するコード表の1行。どのコード表の行かは、type列にApp\Enums\CodeTypeの値で持つ。
 *
 * 画面や検証では、このモデルを直接読まず、code_table()などのヘルパーを使う。
 * このモデルを直接使うのは、管理画面の項目見出し一覧とシーダーだけ。
 */
class Code extends Model
{
    protected $table = 't_codes';

    protected $fillable = [
        'type',
        'code',
        'name',
        'sort_order',
        'staff_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'staff_id' => 'integer',
    ];
}
