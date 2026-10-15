<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Neobit\Core\Auth\HasPasskeys;
use Neobit\Core\Auth\HasTwoFactor;
use Neobit\Core\Auth\IsLoginAccount;
use Neobit\Core\Auth\LoginAccount;
use Neobit\Core\Enums\LoginIdType;
use Neobit\Core\Enums\TwoFactorMethod;
use Neobit\Core\Models\OperationLogNamedModel;

/**
 * 企業会員の担当者。企業（Company）に属し、ログインする（companyガード）。
 * ログインするモデルの共通の型（Neobit\Core\Auth\LoginAccount）を実装している。
 *
 * 担当者に、権限の区別は無い。同じ企業の担当者は、だれでも同じことができる。
 * 設計は docs/member-types-spec.md。
 */
class CompanyUser extends Authenticatable implements LoginAccount, OperationLogNamedModel, PasskeyUser
{
    // パスキーを持てるようにする（Neobit\Core\Auth\PasskeyLogin・PasskeyManagement参照）
    use HasPasskeys;

    // ログインするモデルの、決まった中身。種類の名前から、ガード（company）、
    // ルート（company.login など）、メールのテンプレート（company_...）の名前を作る
    use IsLoginAccount;

    // 2段階認証（方式の読み出し・認証アプリの列・バックアップコード・信頼済み端末）
    use HasTwoFactor;

    /**
     * 企業会員の画面のガードと、ルートの名前の頭。種類の名前（company_user）とは別に、
     * 画面の側の名前（/company/…のURL）に合わせて決めている
     */
    public const LOGIN_GUARD = 'company';

    public const LOGIN_ROUTE_PREFIX = 'company.';

    /**
     * ログインに使う値。担当者IDでも、メールアドレスでもよい。どちらも、同じ企業の中で重ならない。
     * 企業IDは、どちらの場合も一緒に入力させる
     */
    public const LOGIN_ID_TYPE = LoginIdType::Either;

    /**
     * ログインの2段階目の方式。メールで送る確認コード。
     * 変えるときは docs/two-factor-spec.md を読む
     */
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Email;

    /** バックアップコードを使うか。使うなら、希望する人が自分の画面で発行する */
    public const BACKUP_CODES = true;

    /**
     * 担当者IDに使える文字。半角の英数字と、記号の「_」「.」「-」。
     * 企業IDと「/」でつないで1人を決めるので、「/」は使わせない。
     * ログインの入力がメールアドレスか担当者IDかを「@」で見分けるので、「@」も使わせない
     */
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

    /** 配列やJSONにしたときに出さない項目 */
    protected $hidden = [
        'password',
        // 既存のシステムから移した担当者の、古い方式のパスワード（Neobit\Core\Auth\LegacyPasswordUserProvider）
        'legacy_password',
        'remember_token',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    /** この担当者が属する企業 */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 初回のログインでの登録が要るか。既存のシステムから移した企業の最初の担当者は、
     * メールアドレスが空で、2段階目の確認コードを送れない（Company\FirstLoginSetupController）
     */
    public function needsFirstLoginSetup(): bool
    {
        return empty($this->email);
    }

    /**
     * メールアドレスの検証のルール。同じ企業の中で、ほかの担当者が使っていないこと。
     * 確認コードとパスワードの再設定のメールが、本人だけに届くようにするため。
     * 別の企業の担当者と同じアドレスは、使ってよい。DBにも、同じ内容の制約がある。
     * $currentは、編集する担当者。自分自身は、重なりの相手に数えない。
     */
    public static function uniqueEmailInCompany(int $companyId, ?self $current = null): Unique
    {
        return Rule::unique(self::class, 'email')
            ->where('company_id', $companyId)
            ->ignore($current?->getKey());
    }

    /** 画面やメールに出す名前。どの企業の人かが分かるよう、企業名を前に付ける */
    public function displayName(): string
    {
        return trim($this->company->name.' '.$this->name);
    }

    /** お知らせや確認コードを送るメールアドレス。未登録ならnull */
    public function notificationEmail(): ?string
    {
        return $this->email;
    }

    /** ログインに使う値。企業IDと担当者IDの組で、1人に決まる */
    public function loginId(): string
    {
        return $this->company->code.'/'.$this->login_id;
    }

    /** 操作ログに出す名前。どの企業の担当者かが分かるよう、企業名を前に付ける */
    public static function operationLogNames(array $ids): Collection
    {
        return static::query()->with('company')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (self $user) => [$user->id => $user->displayName()]);
    }
}
