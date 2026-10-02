<?php

namespace App\Support;

use App\Models\Member;
use App\Models\Passkey;
use App\Models\Staff;
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
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * ログイン中の本人による、パスキーの一覧・登録・削除。コントローラーに use して使う
 * （会員：MypageController、管理：Admin\StaffController）。
 *
 * ■ 登録の前の本人確認
 * パスキーを登録すると、以後はそのパスキーだけでログインできるようになる。
 * ログイン中の画面を他人に使われた場合に、その人のパスキーを足されないよう、
 * 登録の前に本人確認をする。確かめ方は、その人のログインの2段階目と同じにする。
 * - 会員：メールで送る確認コード（App\Support\MemberVerificationCode）
 * - スタッフ：認証アプリのTOTPコード（App\Support\TwoFactorAuthenticator）
 * 本人確認が済んでから PASSKEY_CONFIRM_MINUTES 分の間だけ登録でき、1件登録したら
 * 本人確認は使い切りになる（もう1件登録するときは、もう一度確かめる）。
 * 削除は本人確認なしでできる（ログインの手段が減るだけで、他人が入れるように
 * なるわけではないため）。
 *
 * ■ 使い方
 * 1. コントローラーに use PasskeyManagement; を書き、次の定数を用意する
 *      private const PASSKEY_GUARD = 'web';                 // ログイン中の本人を取るガード
 *      private const PASSKEY_ROUTE = 'mypage.passkeys';     // 一覧画面のルート名
 *      private const PASSKEY_VIEW = 'mypage.passkeys';      // 一覧画面のビュー
 *      private const PASSKEY_THROTTLE_SCOPE = '...';        // 本人確認のコードの試行制限
 * 2. routes/web.phpに、PASSKEY_ROUTE と、その後ろに .code（会員だけ）・.confirm・
 *    .options・.store・.destroy を付けた名前でルートを書く
 * 3. 一覧画面のビューは、resources/views/_passkeys.blade.phpを@includeする
 *
 * 一覧画面への入口（マイページ・スタッフ詳細のリンク）は、ルートがあるときだけ
 * 出している（Route::has()）。ルートを消せば、入口も消える。
 */
trait PasskeyManagement
{
    /** 本人確認が済んでから、パスキーを登録できる時間（分）。 */
    private const PASSKEY_CONFIRM_MINUTES = 10;

    /**
     * パスキーの一覧画面（GET）。
     */
    public function passkeyIndex(Request $request): View
    {
        $owner = $this->passkeyOwner();

        return view(self::PASSKEY_VIEW, [
            'passkeys' => $owner->passkeys()->orderByDesc('id')->get(),
            'passkeyRoute' => self::PASSKEY_ROUTE,
            // 本人確認の方法。_passkeys.blade.phpが、入力欄の出し分けに使う
            'confirmBy' => $owner instanceof Staff ? 'totp' : 'email',
            'identityConfirmed' => $this->passkeyIdentityConfirmed($request),
            // 会員：確認コードを送った後（入力待ち）かどうか
            'codeSent' => $owner instanceof Member
                && (new MemberVerificationCode())->hasPending($request, MemberVerificationCode::PURPOSE_PASSKEY, $owner),
            // スタッフ：2段階認証（TOTP）が未登録だと、本人確認ができない
            'twoFactorMissing' => $owner instanceof Staff && ! $owner->hasTwoFactorConfirmed(),
        ]);
    }

    /**
     * 本人確認のための確認コードをメールで送る（POST、会員だけ）。
     * スタッフはTOTPで確かめるので、このルートは作らない。
     */
    public function passkeySendCode(Request $request): RedirectResponse
    {
        $owner = $this->passkeyOwner();

        abort_unless($owner instanceof Member, 404);

        if (! (new MemberVerificationCode())->issue($request, $owner, MemberVerificationCode::PURPOSE_PASSKEY)) {
            return redirect()->route(self::PASSKEY_ROUTE)
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route(self::PASSKEY_ROUTE)
            ->with('status', '確認コードをメールで送信しました。');
    }

    /**
     * 本人確認のコードを照合する（POST）。通ったら、一覧画面にパスキーを
     * 作るボタンが出る。
     */
    public function passkeyConfirm(Request $request): RedirectResponse
    {
        $owner = $this->passkeyOwner();

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // 失敗回数による試行制限（IP単位・アカウント単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::PASSKEY_THROTTLE_SCOPE, $request->ip(), $owner->id);

        if ($throttle->isBlocked()) {
            return redirect()->route(self::PASSKEY_ROUTE)
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        if (! $this->passkeyVerifyIdentity($request, $owner, $validated['code'])) {
            $throttle->hit();

            return redirect()->route(self::PASSKEY_ROUTE)
                ->withErrors(['code' => $owner instanceof Staff
                    ? '確認コードが正しくありません。'
                    : '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 誰の本人確認かも一緒に控える（同じブラウザで別の人がログインし直した場合に、
        // 前の人の本人確認で登録できてしまわないように）。
        $request->session()->put($this->passkeyConfirmedSessionKey(), [
            'owner' => $owner->getMorphClass().':'.$owner->getKey(),
            'until' => now()->addMinutes(self::PASSKEY_CONFIRM_MINUTES)->timestamp,
        ]);

        return redirect()->route(self::PASSKEY_ROUTE);
    }

    /**
     * 登録用のオプション（GET、JSON）。「この端末でパスキーを作成する」の
     * ボタンを押したときに、resources/js/passkeys.jsが呼ぶ。
     *
     * 既に登録済みのパスキーは除外するよう、端末に伝える（同じ端末の
     * パスキーを2つ作ろうとすると、端末側で止まる）。
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
     * パスキーの登録（POST、JSON）。成功したら一覧画面のメッセージを
     * セッションに入れておき、画面の再読み込みはresources/js/passkeys.jsが行う。
     *
     * 名前は利用者に入力させず、認証器の名前（AAGUIDから引ける場合）と、
     * 登録した端末のOS・ブラウザ名から作る。
     * 例：「Google Password Manager（Windows・Chrome）」「iPhone・Safari」
     */
    public function passkeyStore(Request $request): JsonResponse
    {
        $owner = $this->passkeyOwner();

        $this->ensurePasskeyIdentityConfirmed($request);

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

        // AAGUID（認証器の種類）は登録の結果を確かめた後でないと分からないので、
        // 保存した後で名前に足す。
        if ($passkey->authenticator !== null) {
            $passkey->name = $passkey->authenticator.'（'.$passkey->name.'）';
            $passkey->save();
        }

        $request->session()->forget($this->passkeyConfirmedSessionKey());
        $request->session()->flash('status', 'パスキーを登録しました。次回から「パスキーでログイン」でログインできます。');

        return response()->json(['id' => $passkey->id, 'name' => $passkey->name]);
    }

    /**
     * パスキーの削除（DELETE）。ルートの{passkey}は、laravel/passkeysが
     * App\Models\Passkeyとして読み込んでくる（Passkeys::usePasskeyModel()）。
     * 本人のものでなければ、存在しないものとして扱う。
     *
     * 利用者の端末に残っているパスキー自体は、サイトからは消せない。
     * 消したパスキーではログインできなくなる（端末側は、利用者が端末の
     * 設定から消す）。
     */
    public function passkeyDestroy(Passkey $passkey): RedirectResponse
    {
        $owner = $this->passkeyOwner();

        abort_unless($passkey->belongsToOwner($owner), 404);

        app(DeletePasskey::class)($owner, $passkey);

        return redirect()->route(self::PASSKEY_ROUTE)
            ->with('status', 'パスキーを削除しました。端末に残っているパスキーは、端末の設定から削除してください。');
    }

    private function passkeyOwner(): Member|Staff
    {
        $owner = Auth::guard(self::PASSKEY_GUARD)->user();

        abort_unless($owner instanceof Member || $owner instanceof Staff, 403);

        return $owner;
    }

    /**
     * 本人確認のコードを照合する。確かめ方は持ち主の種類で決まる
     * （そのアカウントのログインの2段階目と同じ）。
     */
    private function passkeyVerifyIdentity(Request $request, Member|Staff $owner, string $code): bool
    {
        if ($owner instanceof Staff) {
            return $owner->hasTwoFactorConfirmed()
                && (new TwoFactorAuthenticator())->verifyCode($owner->totp_secret, $code);
        }

        $verified = (new MemberVerificationCode())
            ->verify($request, MemberVerificationCode::PURPOSE_PASSKEY, $code);

        return $verified !== null && $verified->id === $owner->id;
    }

    /**
     * 本人確認が済んでいて、今パスキーを登録できるか。
     *
     * スタッフは、本人確認の後に2段階認証の登録を解除した（本人・管理者のどちらでも）
     * 場合も登録できないようにする。解除でパスキーを消した直後に、残っている
     * 本人確認でパスキーを足せてしまうと、「スタッフのパスキーはTOTPを登録済みの
     * 間だけ存在する」という形が崩れるため。
     */
    private function passkeyIdentityConfirmed(Request $request): bool
    {
        $owner = $this->passkeyOwner();
        $confirmed = $request->session()->get($this->passkeyConfirmedSessionKey());

        if ($owner instanceof Staff && ! $owner->hasTwoFactorConfirmed()) {
            return false;
        }

        return is_array($confirmed)
            && ($confirmed['owner'] ?? null) === $owner->getMorphClass().':'.$owner->getKey()
            && ($confirmed['until'] ?? 0) > now()->timestamp;
    }

    private function ensurePasskeyIdentityConfirmed(Request $request): void
    {
        if (! $this->passkeyIdentityConfirmed($request)) {
            throw ValidationException::withMessages([
                'credential' => '本人確認の有効期限が切れています。画面を再読み込みして、もう一度確認コードを入力してください。',
            ]);
        }
    }

    /** 本人確認をした人と、登録できる期限（UNIX時刻）を持つセッションキー。 */
    private function passkeyConfirmedSessionKey(): string
    {
        return 'passkey.confirmed_until.'.self::PASSKEY_GUARD;
    }

    /** 登録用のオプションを、署名の結果が届くまで控えておくセッションキー。 */
    private function passkeyRegistrationSessionKey(): string
    {
        return 'passkey.registration_options.'.self::PASSKEY_GUARD;
    }
}
