<?php

namespace Neobit\Core\Auth;

use Illuminate\Database\Eloquent\Relations\Relation;
use Neobit\Core\Enums\LoginIdType;

/**
 * LoginAccount（ログインするモデルの共通の型）の、決まった中身。個人会員・企業の担当者・
 * スタッフのモデルに付ける。種類の名前から、ガード・ルート・メールのテンプレート・
 * Cookieの名前を作る。
 *
 * ■ 種類の名前
 * モデルの短い名前。AppServiceProviderのRelation::enforceMorphMap()に書いた名前で、
 * member・company_user・staff。操作ログや信頼済み端末がDBに持っている種類の名前と、同じもの。
 * モデルには書かない。
 *
 * ■ 名前の決まり（種類の名前が company_user のとき）
 * - ガード              company_user
 * - ルート              company_user.login のように、頭に「種類の名前.」を付ける
 * - メールのテンプレート  company_user_verification_code のように、頭に「種類の名前_」を付ける。
 *                       無ければ、頭を付けない verification_code を使う。全部の種類に共通の文面で
 *                       よいときは、頭を付けない名前で置く。それも無ければ、フレームワークの既定を使う
 * - 信頼済み端末のCookie  company_user_trusted_device
 *
 * ■ モデルに書くもの
 * - displayName()            画面やメールに出す名前
 * - notificationEmail()      お知らせを送るメールアドレス
 *
 * ■ ログインに使う値を変えるときに書くもの
 * - 定数 LOGIN_ID_TYPE       ログインに使う値の種類（Neobit\Core\Enums\LoginIdType）。メールアドレス・
 *                            ログインID・どちらでも、から選ぶ。書かなければメールアドレス
 * - loginId()                ログインに使う値。書かなければメールアドレス
 *
 * ■ 決まりと違う名前にするときだけ書くもの
 * - 定数 LOGIN_GUARD         ガードの名前。書かなければ種類の名前
 * - 定数 LOGIN_ROUTE_PREFIX  ルートの名前の頭。書かなければ「種類の名前.」
 * ガードとルートは、画面の側の名前なので、種類の名前と違うことが多い。個人会員（Member）は
 * ガードがweb、ルートの頭は無し。企業の担当者（CompanyUser）は、企業会員の画面の名前に合わせて、
 * ガードがcompany、ルートの頭が「company.」。スタッフは、ガードがadmin、ルートの頭が「admin.」。
 */
trait IsLoginAccount
{
    public static function loginType(): string
    {
        return Relation::getMorphAlias(static::class);
    }

    public static function loginGuard(): string
    {
        return defined(static::class.'::LOGIN_GUARD') ? static::LOGIN_GUARD : static::loginType();
    }

    public static function loginRoute(string $name): string
    {
        $prefix = defined(static::class.'::LOGIN_ROUTE_PREFIX') ? static::LOGIN_ROUTE_PREFIX : static::loginType().'.';

        return $prefix.$name;
    }

    public static function loginMailTemplate(string $name): string
    {
        return static::loginType().'_'.$name;
    }

    public static function loginIdCondition(string $value): array
    {
        $type = defined(static::class.'::LOGIN_ID_TYPE') ? static::LOGIN_ID_TYPE : LoginIdType::Email;

        $column = match ($type) {
            LoginIdType::Email => 'email',
            LoginIdType::LoginId => 'login_id',
            // ログインIDには「@」を使わせないので、あればメールアドレス
            LoginIdType::Either => str_contains($value, '@') ? 'email' : 'login_id',
        };

        return [$column => $value];
    }

    public static function trustedDeviceCookie(): string
    {
        return static::loginType().'_trusted_device';
    }

    /** ログインに使う値。ログインに使う列は、メールアドレス */
    public function loginId(): string
    {
        return (string) $this->getAttribute('email');
    }
}
