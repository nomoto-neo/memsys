<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\LoginThrottle;
use App\Support\MemberVerificationCode;
use App\Support\PasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * マイページからのパスワード変更。
 *
 * 本人確認は、メールで送る確認コードで行う。パスワードを忘れた場合の
 * 再設定（PasswordResetController）と同じApp\Support\MemberVerificationCodeの
 * 仕組みを使っている。用途はpurpose=mypage_passwordで区別しているので、
 * パスワード再設定用に発行したコードをここで使い回すことはできない。
 *
 * パスワードを変えると、ほかの端末のログインと「ログイン状態を保持する」のCookieは
 * 無効になる（routes/web.phpのauth.sessionによる）。
 */
class AuthPasswordController extends Controller
{
    private const PURPOSE = MemberVerificationCode::PURPOSE_MYPAGE_PASSWORD;

    // 確認コードの試行制限（LoginThrottle）のカウンターの名前。アカウントは会員idで区別する。
    private const THROTTLE_SCOPE = 'member-password-code';

    // パスワード変更フォームの入力バリデーションルール。
    // password_confirmationはconfirmedルールでpasswordと照合するので、ここには書かない。
    private function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * パスワード変更フォームの表示（GET /mypage/password）。
     *
     * 画面を開くたびに確認コードを送るのではなく、まだ有効なコードを
     * 発行済み（hasPending()がtrue）ならメールを送り直さない。
     * ブラウザの戻る・再読み込みのたびに新しいメールが届いて紛らわしく
     * なるのを避けるため。届いたコードを紛失した場合は、画面の
     * 「コードを再送する」から明示的に送り直せる（resend()）。
     */
    public function edit(Request $request): View
    {
        $member = Auth::user();

        $sendFailed = false;

        if (! (new MemberVerificationCode())->hasPending($request, self::PURPOSE, $member)) {
            $sendFailed = ! (new MemberVerificationCode())->issue($request, $member, self::PURPOSE);
        }

        return view('auth.password', [
            'sendFailed' => $sendFailed,
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    /**
     * 確認コードの再送信（POST /mypage/password/resend）。
     */
    public function resend(Request $request): RedirectResponse
    {
        $member = Auth::user();

        if (! (new MemberVerificationCode())->issue($request, $member, self::PURPOSE)) {
            return redirect()->route('password.edit')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('password.edit')->with('status', '確認コードを再送しました。');
    }

    /**
     * パスワードの更新処理（PATCH /mypage/password/update）。
     *
     * MemberVerificationCode::verify()が返すMemberと、今ログイン中の
     * 本人が一致することまで確認している。念のための二重チェックで、
     * 通常は一致しないケースは起こり得ない
     * （このpurposeのコードは、edit()で常にAuth::user()宛にしか
     * 発行していないため）。
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // 失敗回数による試行制限（IP単位・会員id単位。詳しくはApp\Support\LoginThrottle参照）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), Auth::id());

        if ($throttle->isBlocked()) {
            return redirect()->route('password.edit')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        $verifiedMember = (new MemberVerificationCode())->verify($request, self::PURPOSE, $validated['code']);

        if ($verifiedMember === null || $verifiedMember->id !== Auth::id()) {
            $throttle->hit();

            return redirect()->route('password.edit')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 更新はログイン中の会員のインスタンス（Auth::user()）に対して行う。auth.sessionは
        // リクエストの最後に、このインスタンスのパスワードのハッシュ値をセッションに控え直す。
        // 別のインスタンス（$verifiedMember）を更新すると古い値が控えられ、次のリクエストで
        // 本人までログアウトされてしまう。
        $member = Auth::user();

        $deletedPasskeys = DB::transaction(function () use ($member, $validated) {
            $member->update([
                'password' => Hash::make($validated['password']),
            ]);

            // 「このデバイスを記憶する」で記憶した端末（この端末も含む。次回ログイン時は
            // 確認コードの入力になる）とパスキーを無効にし、お知らせのメールを送る
            // （App\Support\PasswordChange参照）。
            return PasswordChange::resetAndNotify($member);
        });

        return redirect()->route('mypage')->with('status', $deletedPasskeys > 0
            ? 'パスワードを変更しました。登録されていたパスキーは削除しましたので、お使いになる場合は登録し直してください。'
            : 'パスワードを変更しました。');
    }
}
