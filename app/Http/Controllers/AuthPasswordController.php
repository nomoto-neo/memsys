<?php

namespace App\Http\Controllers;

use App\Models\Member;
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
 * マイページからのパスワード変更。本人確認は、メールで送る確認コードで行う。
 *
 * 確認コードの仕組みはパスワードの再設定と同じだが、用途を分けているので、
 * 再設定用のコードをここで使うことはできない。
 * パスワードを変えると、ほかの端末のログインは解除される。
 */
class AuthPasswordController extends Controller
{
    private const PURPOSE = MemberVerificationCode::PURPOSE_MYPAGE_PASSWORD;

    /** 確認コードの試行制限（LoginThrottle）のカウンターの名前。アカウントは会員idで区別する。 */
    private const THROTTLE_SCOPE = 'member-password-code';

    /**
     * パスワード変更フォームの入力バリデーションルール。
     * password_confirmationはconfirmedルールでpasswordと照合するので、ここには書かない。
     */
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
     * まだ有効な確認コードを送ってあれば、送り直さない。戻る・再読み込みのたびに
     * メールが届いて紛らわしくならないように。無くした場合は、画面の「再送する」で送り直せる。
     */
    public function edit(Request $request): View
    {
        $member = Auth::user();

        // まだ有効なコードが無いときだけ、確認コードを送る
        $sendFailed = false;

        if (! (new MemberVerificationCode(Member::class))->hasPending($request, self::PURPOSE, $member)) {
            $sendFailed = ! (new MemberVerificationCode(Member::class))->issue($request, $member, self::PURPOSE);
        }

        return view('auth.password', [
            'sendFailed' => $sendFailed,
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    /** 確認コードの再送信（POST /mypage/password/resend） */
    public function resend(Request $request): RedirectResponse
    {
        $member = Auth::user();

        if (! (new MemberVerificationCode(Member::class))->issue($request, $member, self::PURPOSE)) {
            return redirect()->route('password.edit')
                ->with('error', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->route('password.edit')->with('status', '確認コードを再送しました。');
    }

    /** パスワードの更新（PATCH /mypage/password/update） */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // 失敗回数による試行制限（IP単位・会員id単位。App\Support\LoginThrottle）
        $throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), Auth::id());

        if ($throttle->isBlocked()) {
            return redirect()->route('password.edit')
                ->withErrors(['code' => $throttle->blockedMessage('確認コード')]);
        }

        // 確認コードの照合。コードの宛先と、今ログイン中の本人が同じかも念のため確かめる
        // （このpurposeのコードは、edit()でログイン中の本人宛にしか発行しないので、通常は必ず同じ）
        $verifiedMember = (new MemberVerificationCode(Member::class))->verify($request, self::PURPOSE, $validated['code']);

        if ($verifiedMember === null || $verifiedMember->id !== Auth::id()) {
            $throttle->hit();

            return redirect()->route('password.edit')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $throttle->clear();

        // 更新は、ログイン中の会員のインスタンス（Auth::user()）に対して行う。auth.sessionは
        // リクエストの最後に、このインスタンスのパスワードのハッシュ値をセッションに控え直すので、
        // 別のインスタンス（$verifiedMember）を更新すると、次のリクエストで本人までログアウトされる
        $member = Auth::user();

        // パスワードを変え、記憶済みの端末（この端末も含む。次のログインは確認コードの入力になる）と
        // パスキーを無効にして、お知らせのメールを送る（App\Support\PasswordChange）
        $deletedPasskeys = DB::transaction(function () use ($member, $validated) {
            $member->update([
                'password' => Hash::make($validated['password']),
            ]);

            return PasswordChange::resetAndNotify($member);
        });

        // パスキーを消したときは、登録し直してもらうよう案内する
        if ($deletedPasskeys > 0) {
            $message = 'パスワードを変更しました。登録されていたパスキーは削除しましたので、お使いになる場合は登録し直してください。';
        } else {
            $message = 'パスワードを変更しました。';
        }

        return redirect()->route('mypage')->with('status', $message);
    }
}
