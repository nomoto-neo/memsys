<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DBで管理するコード表の1行（t_codes）。どのコード表の行かはtype列
 * （App\Enums\CodeTypeの値）で表す。
 *
 * 画面や検証でコード表を使うときは、このモデルを直接読まず、code_table()系の
 * ヘルパーを通す（App\Support\CodeTable）。このモデルを直接使うのは、
 * 管理画面の編集（Admin\CodeController）とシーダーだけ。
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
