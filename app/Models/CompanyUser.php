<?php

namespace App\Models;

use App\Support\HasPasskeys;
use App\Support\IsMemberAccount;
use App\Support\MemberAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;

/**
 * 企業会員の担当者。企業（Company）に属し、ログインする（companyガード）。
 * 訪問者側でログインするモデルの共通の型（App\Support\MemberAccount）を実装している。
 *
 * 担当者に、権限の区別は無い。同じ企業の担当者は、だれでも同じことができる。
 * 設計は docs/member-types-spec.md。
 */
class CompanyUser extends Authenticatable implements MemberAccount, PasskeyUser
{
    // パスキーを持てるようにする（App\Support\PasskeyLogin・PasskeyManagement参照）
    use HasPasskeys;

    // 訪問者側でログインするモデルの、決まった中身。種類の名前から、ガード（company）、
    // ルート（company.login など）、メールのテンプレート（company_...）の名前を作る
    use IsMemberAccount;

    // 種類の名前
    public const MEMBER_TYPE = 'company';

    // 担当者IDに使える文字。半角の英数字と、記号の「_」「.」「-」。
    // 企業IDと「/」でつないで1人を決めるので、「/」は使わせない
    public const LOGIN_ID_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    protected $table = 't_company_users';

    protected $fillable = [
        'company_id',
        // 担当者ID。その企業の中でだけ重ならない
        'login_id',
        'name',
        'email',
        'password',
    ];

    // 配列やJSONにしたときに出さない項目
    protected $hidden = [
        'password',
        // 既存のシステムから移した担当者の、古い方式のパスワード（App\Support\LegacyPasswordUserProvider）
        'legacy_password',
        'remember_token',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    // この担当者が属する企業
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // 「このデバイスを記憶する」で記憶した端末。判定はApp\Support\TrustedDeviceManagerが行う。
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    // 画面やメールに出す名前。どの企業の人かが分かるよう、企業名を前に付ける
    public function displayName(): string
    {
        return trim($this->company->name.' '.$this->name);
    }

    // お知らせや確認コードを送るメールアドレス。未登録ならnull
    public function notificationEmail(): ?string
    {
        return $this->email;
    }

    // ログインに使う値。企業IDと担当者IDの組で、1人に決まる
    public function loginId(): string
    {
        return $this->company->code.'/'.$this->login_id;
    }
}
