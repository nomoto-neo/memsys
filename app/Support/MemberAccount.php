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
 * ログインする人の共通の型（LoginAccount）を継いでいる。宛名・宛先・2段階目の方式のような、
 * スタッフにも共通の問いは、そちらにある。
 *
 * ■ 名前の決まり（種類の名前が company のとき）
 * - ガード              company
 * - ルート              company.login・company.mypage のように、頭に「種類の名前.」を付ける
 * - メールのテンプレート  company_verification_code のように、頭に「種類の名前_」を付ける
 * - 信頼済み端末のCookie  company_trusted_device
 * 個人会員だけは、今のURLとルートの名前を変えないよう、ガードは web、ルートの頭は無しにしている。
 */
interface MemberAccount extends LoginAccount
{
    /** 種類の名前。個人会員は member */
    public static function memberType(): string;

    /** この種類がログインするガードの名前 */
    public static function memberGuard(): string;

    /** この種類のルートの名前。$nameは頭を付ける前の名前。例：'login'・'mypage'・'password.forgot' */
    public static function memberRoute(string $name): string;

    /** この種類のメールのテンプレートの名前。$nameは頭を付ける前の名前。例：'verification_code' */
    public static function memberMailTemplate(string $name): string;

    /**
     * ログイン画面で入力された値で、会員を探す条件。[列の名前 => 値]で返すので、そのままwhere()に渡せる。
     * どの列で探すかは、モデルの定数 LOGIN_ID_TYPE（App\Enums\LoginIdType）で決まる。
     *
     * @return array<string, string>
     */
    public static function loginIdCondition(string $value): array;
}
