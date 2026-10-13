<?php

namespace Neobit\Core\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Enums\TwoFactorMethod;
use Neobit\Core\Models\Passkey;
use Neobit\Core\Operations\OperationRecorder;
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * ログイン中の本人による、パスキーの一覧・登録・削除。コントローラーにuseして使う。
 *
 * ■ 登録の前の本人確認
 * パスキーを登録すると、それだけでログインできるようになる。ログイン中の画面を他人に
 * 使われたときにパスキーを足されないよう、登録の前に本人確認をする。確かめ方はその人の
 * ログインの2段階目の方式と同じで、メールの確認コードか、認証アプリのコード。2段階目が
 * 「なし」の人は、パスワードの再入力で確かめる（Neobit\Core\Auth\TwoFactorChallenge）。
 * 本人確認から決まった時間の間だけ登録でき、1件登録したら次の登録ではもう一度確かめる。
 * 削除はログインの手段が減るだけなので、本人確認なしでできる。
 *
 * ■ 使い方
 * 1. コントローラーにuse PasskeyManagement;を書き、次の定数を用意する
 *      private const PASSKEY_GUARD = 'web';                 // ログイン中の本人を取るガード
 *      private const PASSKEY_ROUTE = 'mypage.passkeys';     // 一覧画面のルート名
 *      private const PASSKEY_VIEW = 'mypage.passkeys';      // 一覧画面のビュー
 * 2. routes/web.phpに、PASSKEY_ROUTEと、その後ろに.code・.confirm・.options・.store・.destroyを
 *    付けた名前でルートを書く。.codeは確認コードの送信で、メールの方式のときだけ使う
 * 3. 一覧画面のビューは、resources/views/_passkeys.blade.phpを@includeする
 * 一覧画面への入口はルートがあるときだけ出すので、ルートを消せば入口も消える。
 */
trait PasskeyManagement
{
    /** 本人確認が済んでからパスキーを登録できる分数。 */
    private const PASSKEY_CONFIRM_MINUTES = 10;

    /** パスキーの一覧画面。 */
    public function passkeyIndex(Request $request): View
    {
        $owner = $this->passkeyOwner();

        return view(self::PASSKEY_VIEW, [
            'passkeys' => $owner->passkeys()->orderByDesc('id')->get(),
            'passkeyRoute' => self::PASSKEY_ROUTE,
            // 本人確認の方法（email・totp・none）。_passkeys.blade.phpが入力欄の出し分けに使う
            'confirmBy' => $owner::twoFactorMethod()->value,
            'identityConfirmed' => $this->passkeyIdentityConfirmed($request),
            // メールの方式：確認コードを送って入力を待っているか
            'codeSent' => (new TwoFactorChallenge($owner))->hasSent($request, TwoFactorChallenge::PURPOSE_PASSKEY),
            // 認証アプリの方式：認証アプリが未登録だと、本人確認ができない
            'twoFactorMissing' => $owner::usesTotp() && ! $owner->hasTwoFactorConfirmed(),
        ]);
    }

    /** 本人確認のための確認コードをメールで送る。メールの方式の人だけが使う */
    public function passkeySendCode(Request $request): RedirectResponse
    {
        $owner = $this->passkeyOwner();

        abort_unless($owner::twoFactorMethod() === TwoFactorMethod::Email, 404);

        if (! (new TwoFactorChallenge($owner))->send($request, TwoFactorChallenge::PURPOSE_PASSKEY)) {
            return redirect()->route(self::PASSKEY_ROUTE)
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route(self::PASSKEY_ROUTE)
            ->with('status', '確認コードをメールで送信しました。');
    }

    /** 本人確認のコードを照合する。通ったら一覧画面にパスキーを作るボタンが出る */
    public function passkeyConfirm(Request $request): RedirectResponse
    {
        $owner = $this->passkeyOwner();
        $challenge = new TwoFactorChallenge($owner);

        // 入力するもの。2段階目が「なし」の人はパスワード、ほかは確認コード
        $isPassword = $challenge->method() === TwoFactorMethod::None;
        $field = $isPassword ? 'password' : 'code';

        $validated = $request->validate([
            $field => ['required', 'string'],
        ]);

        // 失敗回数による試行制限。ログインの2段階目と同じカウンターで数える
        // （Neobit\Core\Auth\TwoFactorChallenge::throttle()）
        $throttle = $challenge->throttle($request);

        if ($throttle->isBlocked()) {
            return redirect()->route(self::PASSKEY_ROUTE)
                ->withErrors([$field => $throttle->blockedMessage($isPassword ? 'パスワード' : '確認コード')]);
        }

        if (! $challenge->verify($request, TwoFactorChallenge::PURPOSE_PASSKEY, $validated[$field])) {
            $throttle->hit();

            // メールの確認コードには期限があるので、文言を分ける
            $message = match ($challenge->method()) {
                TwoFactorMethod::Email => '確認コードが正しくないか、有効期限が切れています。',
                TwoFactorMethod::Totp => '確認コードが正しくありません。',
                TwoFactorMethod::None => 'パスワードが正しくありません。',
            };

            return redirect()->route(self::PASSKEY_ROUTE)
                ->withErrors([$field => $message]);
        }

        $throttle->clear();

        // 本人確認が済んだことを誰のものかと一緒に控える。同じブラウザで別の人が
        // ログインし直したときに、前の人の本人確認で登録できないように
        $request->session()->put($this->passkeyConfirmedSessionKey(), [
            'owner' => $owner->getMorphClass().':'.$owner->getKey(),
            'until' => now()->addMinutes(self::PASSKEY_CONFIRM_MINUTES)->timestamp,
        ]);

        return redirect()->route(self::PASSKEY_ROUTE);
    }

    /**
     * 登録用のオプションをJSONで返す。GETで受ける。「この端末でパスキーを作成する」のボタンで
     * resources/js/passkeys.jsが呼ぶ。登録済みのパスキーを端末に伝えるので、同じ端末に
     * 2つ作ろうとすると端末の側で止まる。
     */
    public function passkeyRegistrationOptions(Request $request): JsonResponse
    {
        $owner = $this->passkeyOwner();

        $this->ensurePasskeyIdentityConfirmed($request);

        $options = app(GenerateRegistrationOptions::class)($owner);

        return response()->json(
            PasskeyCeremony::putOptions($request, $this->passkeyRegistrationSessionKey(), $options)
        );
    }

    /**
     * パスキーを登録する。POSTで受けてJSONで返す。通ったら一覧画面のメッセージをセッションに入れ、
     * 画面の読み直しはresources/js/passkeys.jsが行う。
     * 名前は入力させず、認証器の名前と端末のOS・ブラウザから作る。
     * 例：「Google Password Manager（Windows・Chrome）」「iPhone・Safari」
     */
    public function passkeyStore(Request $request): JsonResponse
    {
        $owner = $this->passkeyOwner();

        $this->ensurePasskeyIdentityConfirmed($request);

        // 署名の結果と、控えておいたオプション
        $credential = PasskeyCeremony::credential($request);

        $options = PasskeyCeremony::pullOptions(
            $request,
            $this->passkeyRegistrationSessionKey(),
            PublicKeyCredentialCreationOptions::class
        );

        try {
            $passkey = app(StorePasskey::class)(
                $owner,
                UserAgentLabel::of($request->userAgent()),
                $credential,
                $options
            );
        } catch (InvalidPasskeyException) {
            throw ValidationException::withMessages([
                'credential' => 'このパスキーは登録できませんでした。既に登録されている可能性があります。',
            ]);
        } catch (Throwable $e) {
            Log::warning('PasskeyManagement: パスキーの登録に失敗しました。', [
                'guard' => self::PASSKEY_GUARD,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'credential' => 'パスキーを登録できませんでした。もう一度お試しください。',
            ]);
        }

        // 認証器の名前は、登録の結果を確かめた後でないと分からないので、保存した後で名前に足す
        if ($passkey->authenticator !== null) {
            $passkey->name = $passkey->authenticator.'（'.$passkey->name.'）';
            $passkey->save();
        }

        OperationRecorder::record(OperationLogAction::PasskeyAdd, $owner);

        // 本人確認は使い切り
        $request->session()->forget($this->passkeyConfirmedSessionKey());
        $request->session()->flash('status', 'パスキーを登録しました。次回から「パスキーでログイン」でログインできます。');

        return response()->json(['id' => $passkey->id, 'name' => $passkey->name]);
    }

    /**
     * パスキーを削除する。本人のものでなければ無いものとして扱う。
     * 端末に残っているパスキーはサイトからは消せないが、消した後はログインに使えなくなる。
     * 端末の分は利用者に端末の設定から消してもらう。
     */
    public function passkeyDestroy(Passkey $passkey): RedirectResponse
    {
        $owner = $this->passkeyOwner();

        abort_unless($passkey->belongsToOwner($owner), 404);

        app(DeletePasskey::class)($owner, $passkey);

        OperationRecorder::record(OperationLogAction::PasskeyDelete, $owner);

        return redirect()->route(self::PASSKEY_ROUTE)
            ->with('status', 'パスキーを削除しました。端末に残っているパスキーは、端末の設定から削除してください。');
    }

    /** ログイン中の本人 */
    private function passkeyOwner(): LoginAccount
    {
        $owner = Auth::guard(self::PASSKEY_GUARD)->user();

        abort_unless($owner instanceof LoginAccount, 403);

        return $owner;
    }

    /**
     * 本人確認が済んでいて今パスキーを登録できるか。認証アプリの方式の人は、本人確認の後に
     * 認証アプリの登録を解除したときも登録できない。パスキーは、認証アプリを登録してある間だけ
     * 持てる形にそろえているため。
     */
    private function passkeyIdentityConfirmed(Request $request): bool
    {
        $owner = $this->passkeyOwner();
        $confirmed = $request->session()->get($this->passkeyConfirmedSessionKey());

        // 認証アプリの方式で、登録を解除した人は登録できない
        if ($owner::usesTotp() && ! $owner->hasTwoFactorConfirmed()) {
            return false;
        }

        return is_array($confirmed)
            && ($confirmed['owner'] ?? null) === $owner->getMorphClass().':'.$owner->getKey()
            && ($confirmed['until'] ?? 0) > now()->timestamp;
    }

    /** 本人確認が済んでいなければエラーにする */
    private function ensurePasskeyIdentityConfirmed(Request $request): void
    {
        if (! $this->passkeyIdentityConfirmed($request)) {
            throw ValidationException::withMessages([
                'credential' => '本人確認の有効期限が切れています。画面を再読み込みして、もう一度確認コードを入力してください。',
            ]);
        }
    }

    /** 本人確認をした人と、登録できる期限のUNIX時刻を持つセッションキー。 */
    private function passkeyConfirmedSessionKey(): string
    {
        return 'passkey.confirmed_until.'.self::PASSKEY_GUARD;
    }

    /** 登録用のオプションを署名の結果が届くまで控えておくセッションキー。 */
    private function passkeyRegistrationSessionKey(): string
    {
        return 'passkey.registration_options.'.self::PASSKEY_GUARD;
    }
}
