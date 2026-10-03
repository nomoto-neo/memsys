<?php

namespace App\Support;

use App\Models\Member;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ログインの2段階目を省ける、信頼済みの端末の判定・登録・取り消し。会員の「このデバイスを
 * 記憶する」と、スタッフの「この端末を信頼する」の両方で使う。
 *
 * ブラウザを閉じても何日も覚えておくものなので、セッションではなくCookieとDBで持つ。
 * DBのテーブルは会員とスタッフで共通にし、Cookieの名前だけを分けている。同じブラウザで
 * 両方にログインしたときに、片方で信頼するともう片方のCookieを上書きしてしまうため。
 *
 * 判定はパスワードの確認が済んで誰かが分かってから行う。Cookieの値をその人の期限内の
 * 記録と照合し、1件でも一致すれば2段階目を省いてよい。ハッシュ値から検索することは
 * できないので、1件ずつ照合する。
 *
 * パスワードの変更・2段階認証の登録解除・スタッフの削除のときは、forgetAll()ですべて無効にする。
 * 乗っ取りを疑うときの操作なので、それまでに信頼した端末からも入れないようにするため。
 */
class TrustedDeviceManager
{
    // 信頼の有効な日数。長すぎると、端末を無くしたときに第三者がいつまでも
    // 2段階目なしでログインできてしまう
    public const VALID_DAYS = 30;

    // 会員とスタッフで、記録の値を入れるCookieの名前だけを変える
    private function __construct(private readonly string $cookieName)
    {
    }

    // 会員の「このデバイスを記憶する」
    public static function forMember(): self
    {
        return new self('member_trusted_device');
    }

    // スタッフの「この端末を信頼する」
    public static function forStaff(): self
    {
        return new self('staff_trusted_device');
    }

    // この端末が、その人に信頼されているか
    public function isTrusted(Member|Staff $owner, Request $request): bool
    {
        $token = $request->cookie($this->cookieName);

        if (! is_string($token) || $token === '') {
            return false;
        }

        // 期限内の記録と1件ずつ照合する
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
     * この端末を信頼済みにする。ランダムな値を作り、ハッシュ値をDBへ、元の値をCookieへ保存する。
     * 自宅のパソコンとスマートフォンのように端末ごとに信頼できるよう、今ある記録は残して
     * 1件増やす。
     */
    public function remember(Member|Staff $owner): void
    {
        // 期限の切れた記録は使われずに残るので、ここで消しておく
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
     * その人の信頼済みの端末をすべて無効にし、消した件数を返す。
     * 端末に残ったCookieは照合の相手が無くなるので効かなくなる。
     */
    public function forgetAll(Member|Staff $owner): int
    {
        return $owner->trustedDevices()->delete();
    }
}
