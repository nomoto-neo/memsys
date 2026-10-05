<?php

namespace App\Support;

/**
 * 訪問者側でログインするモデルの共通の型。個人会員（Member）と、企業の担当者が実装する。
 *
 * 共通部品（確認コード・信頼済み端末・パスワードの変更の後始末・パスキーなど）は、会員を
 * この型で受け取る。個人か企業かの場合分けを持たず、ルートやメールのテンプレートの名前は、
 * モデルに「種類の名前」を聞いて、決まりのとおりに作る。
 * 設計は docs/member-types-spec.md。決まった中身はIsMemberAccountトレイトにある。
 *
 * ■ 名前の決まり（種類の名前が company のとき）
 * - ガード              company
 * - ルート              company.login・company.mypage のように、頭に「種類の名前.」を付ける
 * - メールのテンプレート  company_verification_code のように、頭に「種類の名前_」を付ける
 * - 信頼済み端末のCookie  company_trusted_device
 * 個人会員だけは、今のURLとルートの名前を変えないよう、ガードは web、ルートの頭は無しにしている。
 */
interface MemberAccount
{
    // 種類の名前。個人会員は member
    public static function memberType(): string;

    // この種類がログインするガードの名前
    public static function memberGuard(): string;

    // この種類のルートの名前。$nameは頭を付ける前の名前。例：'login'・'mypage'・'password.forgot'
    public static function memberRoute(string $name): string;

    // この種類のメールのテンプレートの名前。$nameは頭を付ける前の名前。例：'verification_code'
    public static function memberMailTemplate(string $name): string;

    // 信頼済み端末の値を入れるCookieの名前
    public static function trustedDeviceCookie(): string;

    // 画面やメールに出す名前。個人なら氏名
    public function displayName(): string;

    // お知らせや確認コードを送るメールアドレス。未登録ならnull
    public function notificationEmail(): ?string;

    // ログインに使う値。試行制限やパスキーの表示名に使う
    public function loginId(): string;
}
