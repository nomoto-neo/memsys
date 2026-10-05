<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Support\MemberVerificationCode;
use App\Support\PasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * パスワードを忘れた担当者のための再設定。ログインしていない状態から使う。
 *
 * 企業ID・担当者ID・メールアドレスの3つを入力させ、全部が合う担当者に確認コードを送る。
 * メールアドレスはほかの担当者と重なっていてもよいので、企業IDと担当者IDで1人に決める。
 * 3つが合ったかどうかは、案内の文で区別しない。区別すると、第三者が値を試すだけで、
 * 担当者がいるかどうかを調べられてしまうため。
 * 再設定できたら、そのままログインさせる。承認済みでない企業の担当者は、ログインさせない。
 */
class PasswordResetController extends Controller
{
    private const PURPOSE = MemberVerificationCode::PURPOSE_PASSWORD_RESET;

    // 再設定フォーム（確認コード＋新しいパスワード）の入力バリデーションルール。
    // password_confirmationはconfirmedルールでpasswordと照合するので、ここには書かない。
    private function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // 確認コードの送り先を決める入力（企業ID・担当者ID・メールアドレス）のバリデーションルール。
    private function forgotRules(): array
    {
        return [
            'company_code' => ['required', 'string'],
            'login_id' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ];
    }

    // 企業ID・担当者ID・メールアドレスの入力画面
    public function create(): View
    {
        return view('company.auth.password-forgot', [
            'required' => required_fields($this->forgotRules()),
        ]);
    }

    // 確認コードの送信。担当者がいてもいなくても、同じ案内を返す（クラス冒頭のコメント参照）
    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->forgotRules());

        // 企業IDで企業を決めてから、その企業の担当者を、担当者IDとメールアドレスで探す
        $user = CompanyUser::query()
            ->where('login_id', $validated['login_id'])
            ->where('email', $validated['email'])
            ->whereIn('company_id', Company::query()->where('code', $validated['company_code'])->select('id'))
            ->first();

        // 担当者がいれば確認コードを送る。送信に失敗しても、案内は変えない
        // （開発者はMemberVerificationCode::issue()が残すログで気付ける）
        if ($user !== null) {
            (new MemberVerificationCode(CompanyUser::class))->issue($request, $user, self::PURPOSE);
        }

        return redirect()->route('company.password.reset')
            ->with('status', 'ご入力いただいたメールアドレス宛に確認コードを送信しました（ご登録の内容と一致しない場合は送信されません）。');
    }

    // 確認コード＋新しいパスワードの入力画面
    public function edit(): View
    {
        return view('company.auth.password-reset', [
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    // 確認コードの照合とパスワードの再設定。成功したら、そのままログインさせる
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $user = (new MemberVerificationCode(CompanyUser::class))->verify($request, self::PURPOSE, $validated['code']);

        if (! $user instanceof CompanyUser) {
            return redirect()->route('company.password.reset')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        // パスワードを変え、記憶済みの端末とパスキーを無効にして、お知らせのメールを送る
        // （App\Support\PasswordChange）
        $deletedPasskeys = DB::transaction(function () use ($user, $validated) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return PasswordChange::resetAndNotify($user);
        });

        // パスキーを消したときは、登録し直してもらうよう案内する
        if ($deletedPasskeys > 0) {
            $message = 'パスワードを再設定しました。登録されていたパスキーは削除しましたので、お使いになる場合は登録し直してください。';
        } else {
            $message = 'パスワードを再設定しました。';
        }

        // 承認済みでない企業（申請中・停止）の担当者は、ログインさせずにログイン画面へ戻す。
        // ログインできない理由は、ログイン画面でパスワードが合ったときに伝える
        if (! $user->company->isApproved()) {
            return redirect()->route('company.login')->with('status', $message);
        }

        Auth::guard(CompanyUser::memberGuard())->login($user);
        $request->session()->regenerate();

        return redirect()->route('company.mypage')->with('status', $message);
    }
}
