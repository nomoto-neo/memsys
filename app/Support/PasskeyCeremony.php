<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;

/**
 * パスキーの登録とログインに共通する、ブラウザとセッションとの受け渡し。
 * PasskeyLoginとPasskeyManagementの両方から使う。
 *
 * パスキーの処理はどちらも2往復で進む。
 *   1. サーバーが毎回違うランダムな値を含んだオプションをブラウザへ返し、同じものを
 *      セッションに控える。putOptions()
 *   2. ブラウザが指紋や顔などで署名した結果を送ってくるので、控えたオプションと
 *      突き合わせて確かめる。pullOptions()
 * 控えたオプションは1回使ったら消すので、同じ署名を2回送られても2回目は通らない。
 */
class PasskeyCeremony
{
    // ブラウザへ返すJSONの形のオプションを作り、セッションに控える。
    public static function putOptions(Request $request, string $sessionKey, object $options): array
    {
        $request->session()->put($sessionKey, WebAuthn::toJson($options));

        return ['options' => WebAuthn::toBrowserArray($options)];
    }

    /**
     * セッションに控えたオプションを取り出し、同時に消す。期限切れや別のタブで先に
     * 使ったなどで無ければエラーにする。
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

    // ブラウザから送られてきた署名の結果のcredentialを、検証用のオブジェクトにする。
    // 形が壊れていればエラーにする。
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
