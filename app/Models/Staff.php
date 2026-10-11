<?php

namespace App\Models;

use App\Enums\StaffAcl;
use App\Enums\TwoFactorMethod;
use App\Support\HasPasskeys;
use App\Support\HasTwoFactor;
use App\Support\LoginAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;

/**
 * 管理画面のスタッフ。会員とは別のテーブル・別のガード（admin）でログインする。
 * 自分で登録する画面は無く、最初の1人はStaffSeederで作る。
 *
 * ■ 論理削除
 * 削除しても行は消さず、deleted_atに日時を入れるだけで、restore()で元に戻せる。
 * 削除済みのスタッフは、一覧・ログイン・URLからの読み込みのどれでも自動的に除かれ、
 * ログインできず、編集画面などを開いても404になる。削除済みも含めて扱うときは、
 * クエリやルートにwithTrashed()を付ける。
 *
 * ■ ログインする人の共通の型
 * 会員と同じ共通の型（App\Support\LoginAccount）を実装している。2段階認証などのログインまわりの
 * 共通部品は、スタッフと会員を同じ形で扱う。
 */
class Staff extends Authenticatable implements LoginAccount, PasskeyUser
{
    use HasFactory;
    use SoftDeletes;

    // パスキーを持てるようにする
    use HasPasskeys;

    // 2段階認証（方式の読み出し・認証アプリの列・バックアップコード・信頼済み端末）
    use HasTwoFactor;

    // ---- ログインする人の共通の型（LoginAccount）の設定 ----

    /** 種類の名前。メールのテンプレート（staff_...）や、信頼済み端末のCookieの名前の頭になる */
    public const LOGIN_TYPE = 'staff';

    /** ログインするガード */
    public const LOGIN_GUARD = 'admin';

    /** ルートの名前の頭 */
    public const LOGIN_ROUTE_PREFIX = 'admin.';

    /**
     * ログインの2段階目の方式。認証アプリのコード（TOTP）。
     * 変えるときは docs/two-factor-spec.md を読む
     */
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Totp;

    protected $table = 't_staffs';

    protected $fillable = [
        'name',
        'login_id',
        'email',
        'password',
        'acl',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        // 権限は列挙型で読み書きする。DBには0か1で入る
        'acl' => StaffAcl::class,
    ];

    // ---- ログインする人の共通の型（LoginAccount）の問い ----

    public static function loginType(): string
    {
        return self::LOGIN_TYPE;
    }

    public static function loginGuard(): string
    {
        return self::LOGIN_GUARD;
    }

    public static function loginRoute(string $name): string
    {
        return self::LOGIN_ROUTE_PREFIX.$name;
    }

    public static function loginMailTemplate(string $name): string
    {
        return self::LOGIN_TYPE.'_'.$name;
    }

    public static function trustedDeviceCookie(): string
    {
        return self::LOGIN_TYPE.'_trusted_device';
    }

    /** 画面やメールに出す名前 */
    public function displayName(): string
    {
        return (string) $this->name;
    }

    /** お知らせや確認コードを送るメールアドレス。スタッフのメールアドレスは任意なので、空ならnull */
    public function notificationEmail(): ?string
    {
        return $this->email ?: null;
    }

    /** ログインに使う値 */
    public function loginId(): string
    {
        return (string) $this->login_id;
    }

    /** 管理者かどうか。ミドルウェアとPolicyで同じ判定を使うため、ここに置いている。 */
    public function isManager(): bool
    {
        return $this->acl === StaffAcl::Manager;
    }

    /**
     * 端末のパスキーの選択画面に出る名前。会員用のパスキーと並んで出るので、
     * 見分けが付くよう「管理画面：」を付ける。端末には登録した時点の値が残るので、
     * 後で氏名を変えても、登録済みのパスキーの表示は変わらない。
     */
    public function getPasskeyDisplayName(): string
    {
        return '管理画面：'.$this->name;
    }

    /** 端末のパスキーの選択画面に出るユーザー名。名前と同じく「管理画面：」を付ける */
    public function getPasskeyUsername(): string
    {
        return '管理画面：'.$this->login_id;
    }
}
