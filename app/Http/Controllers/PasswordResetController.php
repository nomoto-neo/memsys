<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Support\MemberVerificationCode;
use App\Support\PasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * パスワードを忘れた会員のための再設定。ログインしていない状態から使う。
 *
 * メールアドレスを入力すると確認コードを送り、コードと新しいパスワードを入力してもらう。
 * メールアドレスが登録済みかどうかは、案内の文で区別しない。区別すると、第三者が
 * メールアドレスを試すだけで、会員かどうかを調べられてしまうため。
 * 再設定できたら、そのままログインさせる。
 */
class PasswordResetController extends Controller
{
    private const PURPOSE = MemberVerificationCode::PURPOSE_PASSWORD_RESET;

    /**
     * 再設定フォーム（確認コード＋新しいパスワード）の入力バリデーションルール。
     * password_confirmationはconfirmedルールでpasswordと照合するので、ここには書かない。
     */
    private function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** メールアドレスの入力画面 */
    public function create(): View
    {
        return view('auth.password-forgot');
    }

    /** 確認コードの送信。会員がいてもいなくても、同じ案内を返す（クラス冒頭のコメント参照） */
    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $member = Member::where('email', $validated['email'])->first();

        // 会員がいれば確認コードを送る。送信に失敗しても、案内は変えない
        // （開発者はMemberVerificationCode::issue()が残すログで気付ける）
        if ($member !== null) {
            (new MemberVerificationCode(Member::class))->issue($request, $member, self::PURPOSE);
        }

        return redirect()->route('password.reset')
            ->with('status', 'ご入力いただいたメールアドレス宛に確認コードを送信しました（会員登録が無いメールアドレスには送信されません）。');
    }

    /** 確認コード＋新しいパスワードの入力画面 */
    public function edit(): View
    {
        return view('auth.password-reset', [
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    /** 確認コードの照合とパスワードの再設定。成功したら、そのままログインさせる */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $member = (new MemberVerificationCode(Member::class))->verify($request, self::PURPOSE, $validated['code']);

        if ($member === null) {
            return redirect()->route('password.reset')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        // パスワードを変え、記憶済みの端末とパスキーを無効にして、お知らせのメールを送る
        // （App\Support\PasswordChange）
        $deletedPasskeys = DB::transaction(function () use ($member, $validated) {
            $member->update([
                'password' => Hash::make($validated['password']),
            ]);

            return PasswordChange::resetAndNotify($member);
        });

        Auth::login($member);
        $request->session()->regenerate();

        // パスキーを消したときは、登録し直してもらうよう案内する
        if ($deletedPasskeys > 0) {
            $message = 'パスワードを再設定しました。登録されていたパスキーは削除しましたので、お使いになる場合は登録し直してください。';
        } else {
            $message = 'パスワードを再設定しました。';
        }

        return redirect()->route('mypage')->with('status', $message);
    }
}
