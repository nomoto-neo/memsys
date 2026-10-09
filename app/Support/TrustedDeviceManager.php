<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * ログインの2段階目を省ける、信頼済みの端末の判定・登録・取り消し。会員の「このデバイスを
 * 記憶する」と、スタッフの「この端末を信頼する」の両方で使う。
 *
 * ブラウザを閉じても何日も覚えておくものなので、セッションではなくCookieとDBで持つ。
 * DBのテーブルは会員とスタッフで共通にし、Cookieの名前だけを分けている。同じブラウザで
 * 両方にログインしたときに、片方で信頼するともう片方のCookieを上書きしてしまうため。
 *
 * 判定はパスワードの確認が済んで誰かが分かってから行う。Cookieの値のハッシュ値が、
 * その人の期限内の記録にあれば、2段階目を省いてよい。
 *
 * ■ ハッシュ値
 * DBにはCookieの値そのものではなく、SHA-256のハッシュ値を置く。DBが漏れても、
 * そこからCookieの値を作れないようにするため。
 * パスワードと同じHash::make()（bcrypt）は使わない。bcryptはわざと時間がかかる作りで、
 * 同じ値でも毎回違うハッシュ値になるので検索できず、記録を1件ずつ照合することになる。
 * 記録の数だけログインが遅くなる。Cookieの値は64文字の乱数で、総当たりでは当てられないので、
 * 速いハッシュ値で足りる。
 *
 * パスワードの変更・2段階認証の登録解除・スタッフの削除のときは、forgetAll()ですべて無効にする。
 * 乗っ取りを疑うときの操作なので、それまでに信頼した端末からも入れないようにするため。
 */
class TrustedDeviceManager
{
    /**
     * 信頼の有効な日数。長すぎると、端末を無くしたときに第三者がいつまでも
     * 2段階目なしでログインできてしまう
     */
    public const VALID_DAYS = 30;

    /** 会員とスタッフで、記録の値を入れるCookieの名前だけを変える */
    private function __construct(private readonly string $cookieName)
    {
    }

    /**
     * 会員の「このデバイスを記憶する」。Cookieの名前は、会員の種類の名前から決まる。
     *
     * @param  MemberAccount|class-string<MemberAccount>  $member  会員か、会員のモデルのクラス
     */
    public static function forMember(MemberAccount|string $member): self
    {
        return new self($member::trustedDeviceCookie());
    }

    /** スタッフの「この端末を信頼する」 */
    public static function forStaff(): self
    {
        return new self('staff_trusted_device');
    }

    /** この端末が、その人に信頼されているか */
    public function isTrusted(MemberAccount|Staff $owner, Request $request): bool
    {
        $token = $request->cookie($this->cookieName);

        if (! is_string($token) || $token === '') {
            return false;
        }

        // その人の期限内の記録に、同じハッシュ値のものがあるか
        return $owner->trustedDevices()
            ->where('token_hash', self::hashOf($token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * この端末を信頼済みにする。ランダムな値を作り、ハッシュ値をDBへ、元の値をCookieへ保存する。
     * 自宅のパソコンとスマートフォンのように端末ごとに信頼できるよう、今ある記録は残して
     * 1件増やす。
     */
    public function remember(MemberAccount|Staff $owner): void
    {
        // 期限の切れた記録は使われずに残るので、ここで消しておく
        $owner->trustedDevices()->where('expires_at', '<=', now())->delete();

        $token = Str::random(64);

        $owner->trustedDevices()->create([
            'token_hash' => self::hashOf($token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        // 有効期間は「分」単位。パスは既定の「/」、JavaScriptから読めない設定（httpOnly）も既定のまま。
        // 引数に名前を付けて渡さないこと。Cookie::queue()は引数を順番だけで受け取るので、
        // httpOnly: trueと書くと4番目のパスに入り、どのURLにも送られないCookieになる
        Cookie::queue($this->cookieName, $token, self::VALID_DAYS * 24 * 60);

        // 同じ名前で、パスが「今のURLのフォルダー」になっている古いCookieを消す。
        // パスの指定を誤っていた頃に保存されたもので、ブラウザに残っていると、パスの長いほうが
        // 先に送られてきて、上で保存した新しい値が使われない。会員なら/login/verifyで保存されて
        // パスが/loginになっており、ログインのURL（/login）に、古い値のほうが届く。
        // 新しいCookie（パスは「/」）より後に書くこと。同じ名前のCookieを、パスごとに別々に送る
        $oldPath = dirname('/'.ltrim(request()->path(), '/'));
        if ($oldPath !== '/' && $oldPath !== '\\' && $oldPath !== '.') {
            Cookie::queue(Cookie::forget($this->cookieName, str_replace('\\', '/', $oldPath)));
        }
    }

    /** DBに置く、Cookieの値のハッシュ値。理由は冒頭のコメントの「ハッシュ値」 */
    private static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * その人の信頼済みの端末をすべて無効にし、消した件数を返す。
     * 端末に残ったCookieは照合の相手が無くなるので効かなくなる。
     */
    public function forgetAll(MemberAccount|Staff $owner): int
    {
        return $owner->trustedDevices()->delete();
    }
}
