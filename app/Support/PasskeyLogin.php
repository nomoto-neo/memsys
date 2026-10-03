<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Throwable;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * パスキーでのログイン。ログインのコントローラーにuseして使う。
 *
 * パスキーは、端末を持っていることと指紋や顔などで端末のロックを解いたことの2つを
 * 1回で確かめる。そのため、パスキーで通ったときはパスワードも2段階目も求めずにログイン
 * させる。パスワードでのログインも、パスキーを持っていない人のために残しておく。
 *
 * ■ 使い方
 * 1. コントローラーにuse PasskeyLogin;を書き、次の定数とメソッドを用意する
 *      private const PASSKEY_GUARD = 'web';        // ログインさせるガード
 *      private function passkeyRedirectUrl(): string // ログイン後に移動するURL
 * 2. routes/web.phpに、passkeyLoginOptions()とpasskeyLogin()へのルートを書く。
 *    名前はlogin.passkey.optionsとlogin.passkeyで、管理画面はadmin.を付ける
 * 3. ログイン画面はルートがあるときだけ「パスキーでログイン」のボタンを出す。
 *    ルートを消せばボタンも消える
 *
 * ■ 会員とスタッフのパスキーの区別
 * 会員とスタッフのパスキーは同じサイトのものなので、ブラウザの選択画面に両方が並び、
 * 管理画面のログインで会員のパスキーを選ぶこともできてしまう。そこで照合の前に、
 * パスキーの持ち主がこのガードのモデルかを確かめる。
 */
trait PasskeyLogin
{
    // ブラウザへ渡したオプションを署名の結果が届くまで控えておくセッションキー。
    private function passkeyLoginSessionKey(): string
    {
        return 'passkey.login_options.'.self::PASSKEY_GUARD;
    }

    /**
     * ログイン用のオプションをJSONで返す。GETで受ける。「パスキーでログイン」のボタンで
     * resources/js/passkeys.jsが呼ぶ。まだ誰か分からないので特定のパスキーに絞らず、
     * 端末の中にあるこのサイトのパスキーから利用者に選んでもらう。
     */
    public function passkeyLoginOptions(Request $request): JsonResponse
    {
        $options = app(GenerateVerificationOptions::class)();

        return response()->json(
            PasskeyCeremony::putOptions($request, $this->passkeyLoginSessionKey(), $options)
        );
    }

    /**
     * ログインを実行する。POSTで受けてJSONで返す。通ったら移動先のURLを返し、画面の移動は
     * resources/js/passkeys.jsが行う。通らなければ422と画面に出す文言を返す。
     */
    public function passkeyLogin(Request $request): JsonResponse
    {
        // 署名の結果と、控えておいたオプション
        $credential = PasskeyCeremony::credential($request);
        $request->validate(['remember' => ['boolean']]);

        $options = PasskeyCeremony::pullOptions(
            $request,
            $this->passkeyLoginSessionKey(),
            PublicKeyCredentialRequestOptions::class
        );

        $verify = app(VerifyPasskey::class);

        // 照合すると使った記録が更新されるので、照合の前に持ち主がこのガードのモデルかを確かめる
        try {
            $owner = $verify->getPasskey($credential)->user;
        } catch (InvalidPasskeyException) {
            $owner = null;
        }

        $ownerModel = Auth::guard(self::PASSKEY_GUARD)->getProvider()->getModel();

        if (! $owner instanceof $ownerModel) {
            throw ValidationException::withMessages([
                'credential' => 'このパスキーではログインできません。削除したパスキーや、ほかの画面用のパスキーを選んでいないかご確認ください。',
            ]);
        }

        try {
            $passkey = $verify($credential, $options);
        } catch (InvalidPasskeyException) {
            throw ValidationException::withMessages([
                'credential' => 'このパスキーではログインできません。削除したパスキーを選んでいないかご確認ください。',
            ]);
        } catch (Throwable $e) {
            // 署名が合わないなど。原因は利用者には伝えずログにだけ残す
            Log::warning('PasskeyLogin: パスキーの照合に失敗しました。', [
                'guard' => self::PASSKEY_GUARD,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'credential' => 'パスキーを確認できませんでした。もう一度お試しください。',
            ]);
        }

        // 最後に使った端末を記録する
        $passkey->forceFill(['last_used_device' => UserAgentLabel::of($request->userAgent())])->save();

        // ログインする。「ログイン状態を保持する」の値は、画面のJavaScriptが一緒に送ってくる
        Auth::guard(self::PASSKEY_GUARD)->login($owner, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['redirect' => $this->passkeyRedirectUrl()]);
    }

    // ログインした後に移動するURL。コントローラー側で用意する。
    abstract private function passkeyRedirectUrl(): string;
}
