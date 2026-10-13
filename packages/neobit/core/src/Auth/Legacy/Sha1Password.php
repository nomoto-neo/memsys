<?php

namespace Neobit\Core\Auth\Legacy;

use Neobit\Core\Auth\LegacyPassword;
use Neobit\Core\Auth\LoginAccount;

/** 古い方式のパスワード。ソルトの無いSHA-1（16進の40文字） */
final class Sha1Password implements LegacyPassword
{
    public function hash(string $password, LoginAccount $account): string
    {
        return sha1($password);
    }
}
