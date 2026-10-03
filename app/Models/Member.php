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

// 通常のModelではなく、Authenticatable(認証機能つきの基底クラス)を継承する。
// これにより Auth::attempt() / Auth::login() / Auth::user() などが
// このモデルを対象に動くようになる。デフォルトで用意されている
// App\Models\User はもう使わないので、後で削除してよい。
class Member extends Authenticatable implements PasskeyUser
{
    use Notifiable;

    // パスキー（passkeysテーブル、authenticatable_type='member'）を持てるようにする。
    // パスキーでログインさせるかどうかは、ルートとコントローラー側で決める
    // （App\Support\PasskeyLogin・PasskeyManagement参照）。
    use HasPasskeys;

    // MemberFactory（database/factories/MemberFactory.php）を
    // Member::factory()から呼べるようにするトレイト。これが無いと
    // Member::factory()の呼び出しでBadMethodCallExceptionになる。
    use HasFactory;

    /**
     * 顔写真(photo)の横幅(px)。登録される画像は、この横幅を超えないように
     * リサイズして保存される。管理画面・マイページのどちらから登録しても
     * 変わらない、このデータ項目の仕様なのでモデルに持たせている
     * （Admin\MemberController・MypageControllerのUPLOAD_FILESが参照する）。
     */
    public const PHOTO_WIDTH = 600;

    /**
     * ログインした人だけが見られる場所に置くアップロードのフィールド。顔写真は、
     * 本人とスタッフだけが見られる（App\Support\UploadFilePathの「非公開」参照。
     * 見てよいかの判断はApp\Policies\MemberPolicy::viewFiles()）。
     */
    public const PRIVATE_FILE_FIELDS = ['photo'];

    // モデル名(Member)から自動推測されるテーブル名は本来 "members" だが、
    // 業務テーブルであることが分かるよう t_ 接頭辞を付けた "t_members" を
    // 使っているため、ここで明示的に上書きしている。
    // クラス名(Member)自体は業務上の呼び名として分かりやすいのでそのまま。
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
        // /adminから最後に更新した操作者（スタッフ）のid。
        'staff_id',
    ];

    // ここに列挙した項目は、配列やJSONに変換されるとき(例: デバッグ出力やAPIレスポンス)
    // に自動的に除外される。パスワードのハッシュ値やremember_tokenを
    // うっかり画面に出してしまう事故を防ぐための仕組み。
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birthdate' => 'date',
        // DBはunsignedTinyIntegerだが、素のPDOなら文字列で返ってくるところを
        // Eloquentのキャストで明示的にintへ変換させている。これにより
        // $member->prefectureは常にint(またはnull)であることが保証され、
        // Blade側で厳密比較(===)や配列添字に使うときに型のズレを気にしなくて済む。
        'prefecture' => 'integer',
        'staff_id' => 'integer',
    ];

    /**
     * /adminから最後にこの会員情報を更新した操作者（スタッフ）。
     *
     * Staffモデルは論理削除（SoftDeletes）を使っていて、通常のクエリでは
     * 削除済みスタッフが自動的に除外されるが、ここではwithTrashed()で
     * 削除済みも含めている。このリレーションの目的が「削除後も最終更新者の
     * 氏名を参照できるようにする」ことそのものだから。付けないと、
     * 最後に更新したスタッフが削除された時点で$member->editorStaffが
     * nullになり、履歴として氏名を追えなくなる。
     */
    public function editorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')
            ->withTrashed();
    }

    /**
     * ログインの「このデバイスを記憶する」で登録した端末一覧。汎用の
     * trusted_devicesテーブルに、authenticatable_type='member'として記録される。
     * 判定そのものはApp\Support\TrustedDeviceManagerが行う
     * （詳しくはそちらのコメント参照）。
     */
    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TrustedDevice::class, 'authenticatable');
    }

    /**
     * 顔写真のURL（未登録ならnull）。非公開のファイルなので、ファイルの置き場所
     * ではなくuploads.showのルートのURLになる（見られるのは本人とスタッフだけ）。
     * マイページのように、フォームの無い「モデルをそのまま見せる」画面で使う。
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), 'photo', $this->photo),
        );
    }
}
