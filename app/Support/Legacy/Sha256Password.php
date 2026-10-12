<?php

namespace App\Support\Legacy;

use App\Support\LegacyPassword;
use App\Support\LoginAccount;

/** 古い方式のパスワード。ソルトの無いSHA-256（16進の64文字） */
final class Sha256Password implements LegacyPassword
{
    public function hash(string $password, LoginAccount $account): string
    {
        return hash('sha256', $password);
    }
}
