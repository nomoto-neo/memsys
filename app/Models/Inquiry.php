<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 問い合わせフォーム（/contact）からの送信内容。
 */
class Inquiry extends Model
{
    /**
     * ログインした人だけが見られる場所に置くアップロードのフィールド。添付ファイルは
     * 個人情報を含みうるので、スタッフだけが見られる（App\Support\UploadFilePathの
     * 「非公開」参照。見てよいかの判断はApp\Policies\InquiryPolicy::viewFiles()）。
     */
    public const PRIVATE_FILE_FIELDS = ['attach_file'];

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
