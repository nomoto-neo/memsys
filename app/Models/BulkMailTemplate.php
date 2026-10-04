<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 一斉メールの文面。titleは管理用の名前で、メールには出ない。
 * 件名と本文には、宛先の氏名を差し込む{{$name}}を書ける。差し込みの決まりはBulkMailにある。
 */
class BulkMailTemplate extends Model
{
    protected $table = 't_bulk_mail_templates';

    protected $fillable = [
        'title',
        'subject',
        'body',
    ];
}
