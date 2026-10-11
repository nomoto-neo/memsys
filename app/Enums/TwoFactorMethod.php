<?php

namespace App\Enums;

/**
 * ログインの2段階目の方式。ログインするモデルの定数 TWO_FACTOR_METHOD に書く。
 * 設計は docs/two-factor-spec.md。
 */
enum TwoFactorMethod: string
{
    /** メールで送る確認コード */
    case Email = 'email';

    /** 認証アプリのコード（TOTP） */
    case Totp = 'totp';

    /** 2段階目を求めない。パスワードが合ったら、そのままログインを確定する */
    case None = 'none';
}
