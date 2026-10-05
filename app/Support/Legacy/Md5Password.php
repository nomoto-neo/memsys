<?php

namespace App\Support\Legacy;

use App\Support\LegacyPassword;
use App\Support\MemberAccount;

// 古い方式のパスワード。ソルトの無いMD5（16進の32文字）
final class Md5Password implements LegacyPassword
{
    public function hash(string $password, MemberAccount $member): string
    {
        return md5($password);
    }
}
