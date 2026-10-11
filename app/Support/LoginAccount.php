<?php

namespace App\Support;

use App\Enums\TwoFactorMethod;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * ログインするモデルの共通の型。個人会員・企業の担当者・スタッフが実装する。
 *
 * ログインまわりの共通部品（2段階認証・信頼済み端末・確認コードなど）は、相手を
 * この型で受け取る。会員かスタッフかの場合分けを持たず、ガード・ルート・メールのテンプレートの
 * 名前は、モデルに聞いて決める。設計は docs/two-factor-spec.md。
 *
 * 訪問者側の会員は、この型を継いだ MemberAccount を実装する。
 * 2段階認証にかかわる中身（twoFactorMethod()から下）は、HasTwoFactorトレイトにある。
 */
interface LoginAccount
{
    /** 種類の名前。セッションのキー・Cookie・試行制限の名前に使う。例：member・company・staff */
    public static function loginType(): string;

    /** この種類がログインするガードの名前 */
    public static function loginGuard(): string;

    /** この種類のルートの名前。$nameは頭を付ける前の名前。例：'login'・'login.verify' */
    public static function loginRoute(string $name): string;

    /** この種類のメールのテンプレートの名前。$nameは頭を付ける前の名前。例：'verification_code' */
    public static function loginMailTemplate(string $name): string;

    /** 信頼済み端末の値を入れるCookieの名前 */
    public static function trustedDeviceCookie(): string;

    /** 画面やメールに出す名前。個人なら氏名 */
    public function displayName(): string;

    /** お知らせや確認コードを送るメールアドレス。未登録ならnull */
    public function notificationEmail(): ?string;

    /** ログインに使う値。試行制限や、認証アプリ・パスキーの表示名に使う */
    public function loginId(): string;

    /** ログインの2段階目の方式 */
    public static function twoFactorMethod(): TwoFactorMethod;

    /** 方式が認証アプリ（TOTP）か */
    public static function usesTotp(): bool;

    /** 認証アプリ（TOTP）の登録が済んでいるか */
    public function hasTwoFactorConfirmed(): bool;

    /** 認証アプリの登録が済んだ日時。未登録ならnull */
    public function twoFactorConfirmedAt(): ?Carbon;

    /** 認証アプリと共有している秘密鍵。未登録ならnull */
    public function totpSecret(): ?string;

    /** 認証アプリを登録する */
    public function registerTwoFactor(string $secret): void;

    /** 2段階認証のバックアップコード */
    public function backupCodes(): MorphMany;

    /** 2段階目を省けるように記憶した端末 */
    public function trustedDevices(): MorphMany;

    /** 認証アプリの登録を解除し、バックアップコード・信頼済み端末・パスキーも無効にする */
    public function resetTwoFactor(): void;
}
