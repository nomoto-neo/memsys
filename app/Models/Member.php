<?php

namespace App\Models;

use App\Support\HasPasskeys;
use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;

/**
 * 会員。ログインできるモデルなので、Authenticatableを継承している（webガード）。
 */
class Member extends Authenticatable implements PasskeyUser
{
    use Notifiable;

    // パスキーを持てるようにする。パスキーでログインさせるかどうかは、ルートと
    // コントローラーで決める（App\Support\PasskeyLogin・PasskeyManagement参照）
    use HasPasskeys;

    // Member::factory()を使えるようにする（database/factories/MemberFactory.php）
    use HasFactory;

    // 顔写真の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。
    // どの画面から登録しても同じ、このデータ項目の仕様なので、モデルに持たせている。
    public const PHOTO_WIDTH = 600;

    // 顔写真を履歴書に貼るときの、横と縦の比。履歴書の写真の大きさ（横30mm・縦40mm）に
    // 合わせ、PDFにするときに真ん中をこの比で切り抜く。
    public const PHOTO_ASPECT = [3, 4];

    // ログインした人だけが見られる場所に置くアップロードのフィールド。顔写真は、
    // 本人とスタッフだけが見られる。見てよいかはApp\Policies\MemberPolicyで判断する。
    public const PRIVATE_FILE_FIELDS = ['photo'];

    // 業務のテーブルなので、t_を付けた名前にしている
    protected $table = 't_members';

    protected $fillable = [
        'name',
        'kana',
        'email',
        'password',
        'phone',
        'birthdate',
        'prefecture',
        'photo',
        'photo_origin',
        // 管理画面から最後に更新したスタッフのid
        'staff_id',
    ];

    // 配列やJSONにしたときに出さない項目。パスワードのハッシュ値などを、うっかり出さないように
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birthdate' => 'date',
        // 都道府県はコード表の値と===で比べるので、intにそろえる
        'prefecture' => 'integer',
        'staff_id' => 'integer',
    ];

    // 管理画面から最後にこの会員を更新したスタッフ。
    // そのスタッフを削除した後も名前を出せるよう、削除済みのスタッフも含めて探す。
    public function editorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')
            ->withTrashed();
    }

    // 「このデバイスを記憶する」で記憶した端末。判定はApp\Support\TrustedDeviceManagerが行う。
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    // 顔写真のURL。未登録ならnull。非公開のファイルなので、本人とスタッフだけが開けるURLになる。
    // マイページのように、フォームの無い画面で使う。
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), 'photo', $this->photo),
        );
    }

    // 顔写真のサーバー上の場所。未登録ならnull。履歴書のPDFに埋め込むときに使う。
    protected function photoPath(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::path(self::class, $this->getKey(), 'photo', $this->photo),
        );
    }
}
