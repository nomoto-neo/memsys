<?php

namespace App\Support;

use App\Models\Member;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ログインの2段階目を省略できる「信頼済み端末」の判定・登録・取り消し。
 *
 * 会員ログインの「このデバイスを記憶する」（2段階目はメールの確認コード）と、
 * 管理ログインの「この端末を信頼する」（2段階目はTOTP）の両方で使う。
 * 記録するテーブルは会員・スタッフ共通の trusted_devices（誰の端末かは
 * authenticatable_type・authenticatable_id の2列で区別する。TrustedDeviceモデル参照）。
 * Cookieの名前だけは会員用・スタッフ用で分けている。同じブラウザで会員と
 * 管理画面の両方にログインすることがあり、1つのCookieを共有すると、片方で
 * 信頼したときにもう片方のCookieを上書きしてしまうため。会員用・スタッフ用は
 * forMember()・forStaff() で作り分ける。
 *
 * MemberVerificationCodeのような短命な状態はセッションで扱うが、こちらは
 * 「ブラウザを閉じても、何日も経っても覚えていてほしい」長命な状態なので、
 * Cookie＋DBテーブルで持つ。
 *
 * 判定の流れ：
 *   1. ID・パスワードの認証は既に済んでいる（＝誰なのかは確定済み）状態から、
 *      isTrusted($owner, $request)を呼ぶ
 *   2. Cookieの値（ランダムな平文トークン）を、その人が持つ有効期限内の
 *      trustedDevices全件と、1件ずつHash::check()で照合する
 *      （bcryptはソルト付きなので、ハッシュ値だけを見て一致するCookieを
 *      検索する、ということはできない。バックアップコードの
 *      BackupCodeGenerator::verifyAndConsume()と同じ理由・同じやり方）
 *   3. 1件でも一致すればtrue＝2段階目を省略してよい
 *
 * パスワードの変更（App\Support\PasswordChange）・2段階認証の登録解除・
 * スタッフの削除のときは、forgetAll()でその人の信頼済み端末をすべて無効にする。アカウントが
 * 乗っ取られたかもしれないときに行う操作なので、それまでに信頼した端末からも
 * 2段階目なしでは入れないようにするため。
 */
class TrustedDeviceManager
{
    /** 信頼の有効期間（日数）。長すぎると、端末を紛失したときに
     *  第三者がいつまでも2段階目なしでログインできてしまう。 */
    public const VALID_DAYS = 30;

    private function __construct(private readonly string $cookieName)
    {
    }

    public static function forMember(): self
    {
        return new self('member_trusted_device');
    }

    public static function forStaff(): self
    {
        return new self('staff_trusted_device');
    }

    public function isTrusted(Member|Staff $owner, Request $request): bool
    {
        $token = $request->cookie($this->cookieName);

        if (! is_string($token) || $token === '') {
            return false;
        }

        $devices = $owner->trustedDevices()
            ->where('expires_at', '>', now())
            ->get();

        foreach ($devices as $device) {
            if (Hash::check($token, $device->token_hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * この端末を信頼済みにする。新しいランダムトークンを発行し、ハッシュ化
     * してDBへ、平文をCookieへ、それぞれ保存する。
     *
     * 呼ぶたびに新しい行が増える（有効な既存の行は消さない）。複数の端末
     * （自宅PC・スマホ等）をそれぞれ別々に信頼することを想定しているため。
     * 期限切れの行は照合に使われないだけで残り続けるので、ここで消しておく。
     */
    public function remember(Member|Staff $owner): void
    {
        $owner->trustedDevices()->where('expires_at', '<=', now())->delete();

        $token = Str::random(64);

        $owner->trustedDevices()->create([
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        Cookie::queue(
            $this->cookieName,
            $token,
            self::VALID_DAYS * 24 * 60, // Cookie::queue()の有効期間は「分」単位
            httpOnly: true,
        );
    }

    /**
     * その人の信頼済み端末をすべて無効にする（DBの行を消す）。
     * 各端末に残っているCookieは、照合相手の行が無くなるので効かなくなる。
     * 戻り値は消した件数。
     */
    public function forgetAll(Member|Staff $owner): int
    {
        return $owner->trustedDevices()->delete();
    }
}
