<?php

namespace App\Policies;

use App\Models\BulkMail;
use App\Models\Member;
use App\Models\Staff;

/**
 * 一斉メールの送信の記録ごとに、誰が何をしてよいかの判断。
 * App\Models\BulkMailと名前が対応しているので、Laravelが自動で見つける。
 */
class BulkMailPolicy
{
    /** 非公開の添付ファイルを見てよいか。送ったメールの中身なので、スタッフだけが見られる */
    public function viewFiles(Member|Staff $user, BulkMail $bulkMail, string $field): bool
    {
        return $user instanceof Staff;
    }
}
