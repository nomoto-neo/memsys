<?php

namespace App\Support\Legacy;

use App\Support\LegacyPassword;
use App\Support\LoginAccount;

/** 古い方式のパスワード。ソルトの無いSHA-1（16進の40文字） */
final class Sha1Password implements LegacyPassword
{
    public function hash(string $password, LoginAccount $account): string
    {
        return sha1($password);
    }
}
