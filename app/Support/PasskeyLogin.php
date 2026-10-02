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
 * パスキーでのログイン。ログインのコントローラーに use して使う
 * （会員：AuthSessionController、管理：Admin\AuthSessionController）。
 *
 * パスキーは、端末を持っていること（所持）と、指紋・顔・PINで端末のロックを
 * 解いたこと（生体・知識）の2つを1回で確かめるので、パスキーで通ったときは
 * ログインID・パスワードも2段階目（確認コード・TOTP）も求めずにログインさせる。
 * ログインID・パスワードでのログインは、今までどおり残る（パスキーを
 * 登録していない人・パスキーの端末を失くした人のため）。
 *
 * ■ 使い方
 * 1. コントローラーに use PasskeyLogin; を書き、次の定数とメソッドを用意する
 *      private const PASSKEY_GUARD = 'web';        // ログインさせるガード
 *      private function passkeyRedirectUrl(): string // ログイン後に移動するURL
 * 2. routes/web.phpに、passkeyLoginOptions()・passkeyLogin()へのルートを書く
 *    （名前は login.passkey.options・login.passkey。管理画面は admin. を付ける）
 * 3. ログイン画面は、ルートがあるときだけ「パスキーでログイン」のボタンを出す
 *    （Route::has()で判定しているので、ルートを消せばボタンも消える）
 *
 * ■ 会員とスタッフのパスキーの区別
 * 会員・スタッフのパスキーは同じサイト（同じドメイン）のものなので、ブラウザの
 * パスキー選択画面には両方が並ぶ。管理画面のログインで会員のパスキーを選ぶ
 * （またはその逆の）ことができてしまうため、照合の前に、パスキーの持ち主が
 * このガードのモデル（config/auth.phpのprovider）かどうかを確かめている。
 */
trait PasskeyLogin
{
    /** ブラウザへ渡したオプションを、署名の結果が届くまで控えておくセッションキー。 */
    private function passkeyLoginSessionKey(): string
    {
        return 'passkey.login_options.'.self::PASSKEY_GUARD;
    }

    /**
     * ログイン用のオプション（GET、JSON）。「パスキーでログイン」のボタンを
     * 押したときに、resources/js/passkeys.jsが呼ぶ。
     *
     * 誰がログインしようとしているかはまだ分からないので、特定のパスキーに
     * 絞らないオプションを返す（端末の中にあるこのサイトのパスキーから、
     * 利用者が選ぶ）。
     */
    public function passkeyLoginOptions(Request $request): JsonResponse
    {
        $options = app(GenerateVerificationOptions::class)();

        return response()->json(
            PasskeyCeremony::putOptions($request, $this->passkeyLoginSessionKey(), $options)
        );
    }

    /**
     * ログインの実行（POST、JSON）。成功したら、移動先のURLを返す
     * （画面の移動はresources/js/passkeys.jsが行う）。
     * 失敗したときは422と、画面に出す文言を返す。
     */
    public function passkeyLogin(Request $request): JsonResponse
    {
        $credential = PasskeyCeremony::credential($request);
        $request->validate(['remember' => ['boolean']]);

        $options = PasskeyCeremony::pullOptions(
            $request,
            $this->passkeyLoginSessionKey(),
            PublicKeyCredentialRequestOptions::class
        );

        $verify = app(VerifyPasskey::class);

        // 照合（署名カウンターと最終利用日時の更新を含む）の前に、持ち主を確かめる。
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
            // 署名が合わない・オプションと食い違う、など。原因は利用者には
            // 伝えず、ログにだけ残す。
            Log::warning('PasskeyLogin: パスキーの照合に失敗しました。', [
                'guard' => self::PASSKEY_GUARD,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'credential' => 'パスキーを確認できませんでした。もう一度お試しください。',
            ]);
        }

        $passkey->forceFill(['last_used_device' => UserAgentLabel::of($request->userAgent())])->save();

        // 「ログイン状態を保持する」は、ログインID・パスワードでのログインと同じ
        // チェックボックスの値を、resources/js/passkeys.jsが一緒に送ってくる。
        Auth::guard(self::PASSKEY_GUARD)->login($owner, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['redirect' => $this->passkeyRedirectUrl()]);
    }

    /** ログインした後に移動するURL。コントローラー側で用意する。 */
    abstract private function passkeyRedirectUrl(): string;
}
