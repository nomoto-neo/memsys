<?php

namespace Neobit\Core\Auth\Legacy;

use Neobit\Core\Auth\LegacyPassword;
use Neobit\Core\Auth\LoginAccount;

/** 古い方式のパスワード。ソルトの無いMD5（16進の32文字） */
final class Md5Password implements LegacyPassword
{
    public function hash(string $password, LoginAccount $account): string
    {
        return md5($password);
    }
}
