<?php

namespace App\Models;

use App\Enums\StaffAcl;
use App\Support\HasPasskeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * ■ 2段階認証
 * totp_secretは認証アプリと共有する秘密鍵で、DBには暗号化して保存する。
 * totp_secretがあっても、totp_confirmed_atが空の間は、QRコードを出しただけで
 * 登録はまだ済んでいない。
 */
class Staff extends Authenticatable implements PasskeyUser
{
    use HasFactory;
    use SoftDeletes;

    // パスキーを持てるようにする
    use HasPasskeys;

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
        'totp_secret',
    ];

    protected $casts = [
        // 権限は列挙型で読み書きする。DBには0か1で入る
        'acl' => StaffAcl::class,
        'totp_secret' => 'encrypted',
        'totp_confirmed_at' => 'datetime',
    ];

    /** 管理者かどうか。ミドルウェアとPolicyで同じ判定を使うため、ここに置いている。 */
    public function isManager(): bool
    {
        return $this->acl === StaffAcl::Manager;
    }

    /** 2段階認証のバックアップコード。 */
    public function backupCodes(): MorphMany
    {
        return $this->morphMany(TwoFactorBackupCode::class, 'authenticatable');
    }

    /** 「この端末を信頼する」で信頼した端末。判定はApp\Support\TrustedDeviceManagerが行う。 */
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    /**
     * 2段階認証の登録が済んでいるか。QRコードを読み取り、コードの入力まで済んだら登録済み。
     * 秘密鍵があるだけでは、まだ登録の途中のことがあるので、totp_confirmed_atで判定する。
     */
    public function hasTwoFactorConfirmed(): bool
    {
        return $this->totp_confirmed_at !== null;
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

    /** 使っていないバックアップコードの残りの数。スタッフの詳細画面の案内に使う。 */
    public function unusedBackupCodesCount(): int
    {
        return $this->backupCodes()->whereNull('used_at')->count();
    }
}
