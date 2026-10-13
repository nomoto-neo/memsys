<?php

namespace Neobit\Core\Auth\Legacy;

use Neobit\Core\Auth\LegacyPassword;
use Neobit\Core\Auth\LoginAccount;

/** 古い方式のパスワード。ソルトの無いSHA-256（16進の64文字） */
final class Sha256Password implements LegacyPassword
{
    public function hash(string $password, LoginAccount $account): string
    {
        return hash('sha256', $password);
    }
}
