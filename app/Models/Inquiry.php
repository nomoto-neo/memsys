<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * お問い合わせフォームから送られてきた内容。
 */
class Inquiry extends Model
{
    /**
     * ログインした人だけが見られる場所に置くアップロードのフィールド。添付ファイルは
     * 個人情報を含むことがあるので、スタッフだけが見られるようにする。
     * 見てよいかはApp\Policies\InquiryPolicyで判断する。
     */
    public const PRIVATE_FILE_FIELDS = ['attach_file'];

    /**
     * 入力の全角と半角をそろえない項目（App\Support\InputNormalizer）。
     * お問い合わせの内容は、書いたとおりに残す。氏名や住所はそろえる
     */
    public const RAW_INPUT_FIELDS = ['body'];

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
        // 都道府県はコード表の値と===で比べるので、intにそろえる
        'prefecture' => 'integer',
    ];
}
