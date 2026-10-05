<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 一斉メールの文面。titleは管理用の名前で、メールには出ない。
 * 件名と本文には、宛先の氏名を差し込む{{$name}}を書ける。差し込みの決まりはBulkMailにある。
 */
class BulkMailTemplate extends Model
{
    // 入力の全角と半角をそろえない項目（App\Support\InputNormalizer）。
    // メールの件名と本文は、書いたとおりに残す。管理用の名前はそろえる
    public const RAW_INPUT_FIELDS = ['subject', 'body'];

    protected $table = 't_bulk_mail_templates';

    protected $fillable = [
        'title',
        'subject',
        'body',
    ];
}
