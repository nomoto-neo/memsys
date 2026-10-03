<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\Member;
use App\Models\Staff;

/**
 * お問い合わせ1件ごとに「誰が何をしてよいか」の判断。
 *
 * App\Models\InquiryとApp\Policies\InquiryPolicyという名前の対応から、Laravelが自動で
 * このクラスを見つけるので、どこかに登録する必要は無い。
 */
class InquiryPolicy
{
    /**
     * 非公開のフィールド（Inquiry::PRIVATE_FILE_FIELDS。添付ファイル）のファイルを見てよいか。
     * 送ってきた訪問者はログインしていないので、見られるのはスタッフだけ。訪問者が
     * 送る前に確認画面で見るのは一時ファイル（uploads.tmp）なので、ここは通らない。
     */
    public function viewFiles(Member|Staff $user, Inquiry $inquiry, string $field): bool
    {
        return $user instanceof Staff;
    }
}
