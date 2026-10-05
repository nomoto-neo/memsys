<?php

namespace App\Support\Legacy;

use App\Support\LegacyPassword;
use App\Support\MemberAccount;

// 古い方式のパスワード。ソルトの無いSHA-1（16進の40文字）
final class Sha1Password implements LegacyPassword
{
    public function hash(string $password, MemberAccount $member): string
    {
        return sha1($password);
    }
}
