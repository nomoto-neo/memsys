<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 問い合わせフォーム（/contact）からの送信内容。
 */
class Inquiry extends Model
{
    protected $table = 't_inquiries';

    protected $fillable = [
        'name',
        'kana',
        'email',
        'phone',
        'zip',
        'prefecture',
        'city',
        'address_other',
        'body',
        'attach_file',
        'attach_file_origin',
    ];

    protected $casts = [
        // t_members.prefectureと同じ理由（App\Models\Memberのコメント参照）。
        'prefecture' => 'integer',
    ];
}
