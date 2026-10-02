<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
 * パスワードを忘れた会員向けの再設定（ログインしていない状態から）。
 *
 * メールアドレスの入力→確認コード入力＋新しいパスワード、の2画面構成。
 * 中身の発行・検証はマイページのパスワード変更（AuthPasswordController）と
 * 同じApp\Support\MemberVerificationCodeを使うが、purposeを分けている
 * ことに加えて、そもそもログインしていない状態から呼ばれる（対象の
 * Memberが「今ログイン中の本人」ではなく「入力されたメールアドレスの
 * 持ち主」になる）という違いがある。
 *
 * ■ メールアドレスが登録されているかどうかを教えない
 *
 * sendCode()は、該当する会員が見つかった場合も見つからなかった場合も、
 * 常に同じ案内文をwith('status', ...)で返す。もし「そのメールアドレスは
 * 登録されていません」のように分けて表示すると、第三者が適当なメール
 * アドレスを打ち込むだけで「このメールアドレスは会員登録済みかどうか」を
 * 調べられてしまう（会員登録フォーム側は重複チェックのために存在確認が
 * 必要なのでこの限りではないが、こちらのパスワード再設定は隠す側に倒す）。
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

    public function create(): View
    {
        return view('auth.password-forgot');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $member = Member::where('email', $validated['email'])->first();

        if ($member !== null) {
            // 送信に失敗した場合も、下のリダイレクト先・案内文は変えない
            // （理由はクラス冒頭のコメント参照。開発者側は
            // MemberVerificationCode::issue()内のLog::error()で気づける）。
            (new MemberVerificationCode())->issue($request, $member, self::PURPOSE);
        }

        return redirect()->route('password.reset')
            ->with('status', 'ご入力いただいたメールアドレス宛に確認コードを送信しました（会員登録が無いメールアドレスには送信されません）。');
    }

    public function edit(): View
    {
        return view('auth.password-reset', [
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules(), ['password_confirmation']),
        ]);
    }

    /**
     * 確認コードの検証とパスワードの再設定。成功したら、そのままログイン
     * 状態にする（会員登録直後にAuth::login()するAuthRegisteredMemberController
     * ::verify()と同じ、「この後もう一度ログインし直させない」という考え方）。
     *
     * このログインを、そのまま「このデバイスを記憶する」対象にはしない
     * （TrustedDeviceManagerは呼ばない）。パスワード再設定と端末記憶は
     * 別の意思決定なので、記憶させたい場合は次回ログイン時に改めて
     * チェックを入れてもらう、というシンプルな整理にしている。
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $member = (new MemberVerificationCode())->verify($request, self::PURPOSE, $validated['code']);

        if ($member === null) {
            return redirect()->route('password.reset')
                ->withErrors(['code' => '確認コードが正しくないか、有効期限が切れています。']);
        }

        $deletedPasskeys = DB::transaction(function () use ($member, $validated) {
            $member->update([
                'password' => Hash::make($validated['password']),
            ]);

            // 記憶済みの端末とパスキーを無効にし、お知らせのメールを送る
            // （App\Support\PasswordChange参照）。
            return PasswordChange::resetAndNotify($member);
        });

        Auth::login($member);
        $request->session()->regenerate();

        return redirect()->route('mypage')->with('status', $deletedPasskeys > 0
            ? 'パスワードを再設定しました。登録されていたパスキーは削除しましたので、お使いになる場合は登録し直してください。'
            : 'パスワードを再設定しました。');
    }
}
