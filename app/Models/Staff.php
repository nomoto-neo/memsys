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
 * 管理者アカウント。t_membersとは別のテーブル・別のガード（admin）で認証する、
 * 会員とは独立した存在。会員登録のような自己登録の画面は用意していない
 * （StaffSeederで最初の1件を作り、それ以降は必要になった時点で追加機能を検討する）。
 *
 * テーブル名はt_staffs。クラス名（Staff）とテーブル名（t_staffs）は
 * 一致していないが、Eloquentのモデル・テーブル対応は$tableプロパティが
 * 決めているだけなので、両者が食い違っていても問題なく動く。
 *
 * ■ 論理削除（SoftDeletes）
 * Laravel標準のSoftDeletesトレイトを使っている。$staff->delete()は行を
 * 消さずにdeleted_atへ削除日時を入れるだけで、$staff->restore()で元に戻せる
 * （完全に消したい場合は$staff->forceDelete()）。
 *
 * SoftDeletesは、このモデルのすべてのクエリに「deleted_atがnullの行だけ」
 * という条件を自動で付ける。そのため一覧・検索だけでなく、ログイン時の
 * ユーザー検索（adminガード）やルートモデルバインディング（/admin/staff/{staff}）
 * でも、削除済みスタッフは自動的に対象外になる（削除済みスタッフは
 * ログインできず、編集画面などのURLを直接開いても404になる）。
 * 削除済みも含めて扱いたいときは、クエリやリレーションにwithTrashed()を付ける
 * （例：Member::editorStaff()、スタッフ一覧の「削除済みも含める」）。ルートの場合は
 * ルートの定義に->withTrashed()を付ける（例：スタッフの詳細画面・削除の取り消し）。
 * 削除済みかどうかは$staff->trashed()で判定する。
 *
 * ■ 2段階認証（TOTP）関連
 * totp_secretは認証アプリと共有する秘密鍵で、$castsで'encrypted'にして
 * いるため、DBには暗号化された状態で保存される（読み書きは平文の
 * 文字列のまま扱える）。totp_secretがあってもtotp_confirmed_atがnullの
 * 間は「QRコードを発行しただけで、まだ確認コードでの確定がされていない」
 * 状態（TwoFactorChallengeControllerを参照）。
 */
class Staff extends Authenticatable implements PasskeyUser
{
    use HasFactory;
    use SoftDeletes;

    // パスキー（passkeysテーブル、authenticatable_type='staff'）を持てるようにする。
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
        // 権限はApp\Enums\StaffAclとして読み書きする（DBには0/1で入る）
        'acl' => StaffAcl::class,
        'totp_secret' => 'encrypted',
        'totp_confirmed_at' => 'datetime',
    ];

    /**
     * 管理者かどうか。EnsureStaffIsManagerミドルウェアとStaffPolicyから、
     * 同じ判定を使うためにここに置いている。
     */
    public function isManager(): bool
    {
        return $this->acl === StaffAcl::Manager;
    }

    /**
     * 2段階認証のバックアップコード。汎用のtwo_factor_backup_codesテーブルに、
     * authenticatable_type='staff'として記録される（TwoFactorBackupCodeモデル参照）。
     */
    public function backupCodes(): MorphMany
    {
        return $this->morphMany(TwoFactorBackupCode::class, 'authenticatable');
    }

    /**
     * 管理ログインの「この端末を信頼する」で登録した端末一覧。汎用の
     * trusted_devicesテーブルに、authenticatable_type='staff'として記録される。
     * 判定そのものはApp\Support\TrustedDeviceManagerが行う
     * （詳しくはそちらのコメント参照）。
     */
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    /**
     * 2段階認証の登録が完了している（QRコード読み取り後、確認コードの
     * 入力まで済んでいる）かどうか。totp_secretがあるだけでは「発行した
     * だけでまだ確認していない」状態がありうるので、totp_confirmed_atの
     * 有無で判定する。
     */
    public function hasTwoFactorConfirmed(): bool
    {
        return $this->totp_confirmed_at !== null;
    }

    /**
     * 端末のパスキー選択画面に出る名前。会員と同じサイトのパスキーなので、
     * 会員用のパスキーと並んで表示される。見分けが付くよう「管理画面：」を付ける。
     * 端末に保存されるのは登録した時点の値なので、後で氏名やログインIDを
     * 変えても、登録済みのパスキーの表示は変わらない。
     */
    public function getPasskeyDisplayName(): string
    {
        return '管理画面：'.$this->name;
    }

    public function getPasskeyUsername(): string
    {
        return '管理画面：'.$this->login_id;
    }

    /**
     * 未使用のバックアップコードの残数。スタッフ詳細画面での案内表示に使う。
     */
    public function unusedBackupCodesCount(): int
    {
        return $this->backupCodes()->whereNull('used_at')->count();
    }
}
