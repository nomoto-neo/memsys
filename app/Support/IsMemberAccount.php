<?php

namespace App\Support;

/**
 * MemberAccount（訪問者側でログインするモデルの共通の型）の、決まった中身。
 * 種類の名前から、ガード・ルート・メールのテンプレート・Cookieの名前を作る。
 *
 * ■ モデルに書くもの
 * - 定数 MEMBER_TYPE          種類の名前。例：'company'
 * - displayName()             画面やメールに出す名前
 * - notificationEmail()       お知らせを送るメールアドレス
 *
 * ■ 決まりと違う名前にするときだけ書くもの
 * - 定数 MEMBER_GUARD         ガードの名前。書かなければ種類の名前
 * - 定数 MEMBER_ROUTE_PREFIX  ルートの名前の頭。書かなければ「種類の名前.」
 * 個人会員（Member）は、今のURLとルートの名前を変えないよう、この2つを書いている。
 */
trait IsMemberAccount
{
    public static function memberType(): string
    {
        return static::MEMBER_TYPE;
    }

    public static function memberGuard(): string
    {
        return defined(static::class.'::MEMBER_GUARD') ? static::MEMBER_GUARD : static::MEMBER_TYPE;
    }

    public static function memberRoute(string $name): string
    {
        $prefix = defined(static::class.'::MEMBER_ROUTE_PREFIX') ? static::MEMBER_ROUTE_PREFIX : static::MEMBER_TYPE.'.';

        return $prefix.$name;
    }

    public static function memberMailTemplate(string $name): string
    {
        return static::MEMBER_TYPE.'_'.$name;
    }

    public static function trustedDeviceCookie(): string
    {
        return static::MEMBER_TYPE.'_trusted_device';
    }

    // ログインに使う値。ログインに使う列は、メールアドレス
    public function loginId(): string
    {
        return (string) $this->getAttribute('email');
    }
}
