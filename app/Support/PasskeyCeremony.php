<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;

/**
 * パスキーの登録・ログインに共通する、リクエストとセッションの受け渡し。
 * App\Support\PasskeyLogin・PasskeyManagementの両方から使う。
 *
 * パスキーの処理は、どちらも次の2往復で進む（「セレモニー」と呼ばれる）。
 *   1. サーバーが、毎回違うランダムな値（チャレンジ）を含んだ「オプション」を
 *      作ってブラウザへ返し、同じものをセッションに控える（putOptions()）
 *   2. ブラウザが端末（指紋・顔・PINなど）で署名した結果を送ってくるので、
 *      セッションに控えたオプションと突き合わせて確かめる（pullOptions()）
 * 控えたオプションは1回使ったら消す（pull）。同じ署名を2回送られても、
 * 2回目は照合の相手が無いので通らない。
 */
class PasskeyCeremony
{
    /** ブラウザへ返す形（JSON）のオプションを作り、セッションに控える。 */
    public static function putOptions(Request $request, string $sessionKey, object $options): array
    {
        $request->session()->put($sessionKey, WebAuthn::toJson($options));

        return ['options' => WebAuthn::toBrowserArray($options)];
    }

    /**
     * セッションに控えたオプションを取り出す（取り出すと同時に消す）。
     * 無ければ（有効期限切れ・別のタブで先に使った）エラーにする。
     *
     * @template T of object
     * @param  class-string<T>  $class
     * @return T
     */
    public static function pullOptions(Request $request, string $sessionKey, string $class): object
    {
        $serialized = $request->session()->pull($sessionKey);

        if (! is_string($serialized)) {
            throw ValidationException::withMessages([
                'credential' => '操作の有効期限が切れています。もう一度お試しください。',
            ]);
        }

        return WebAuthn::fromJson($serialized, $class);
    }

    /**
     * ブラウザから送られてきた署名の結果（credential）を検証用のオブジェクトにする。
     * 形が壊れていればエラーにする。
     */
    public static function credential(Request $request): PublicKeyCredential
    {
        $request->validate([
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        try {
            return WebAuthn::fromJson(
                json_encode($request->input('credential'), JSON_THROW_ON_ERROR),
                PublicKeyCredential::class
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'credential' => 'パスキーの情報を読み取れませんでした。もう一度お試しください。',
            ]);
        }
    }
}
