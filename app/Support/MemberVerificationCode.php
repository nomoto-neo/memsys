<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 会員向けの「メールで送る確認コード」を発行・検証する。
 *
 * 用途が5つある（ログイン・パスワード再設定（未ログイン）・パスワード変更
 * （マイページ、ログイン中）・会員登録（未ログイン、会員はまだ存在しない）・
 * パスキーの登録の前の本人確認（マイページ、ログイン中））。中身の処理（コードを作る・セッションに
 * ハッシュ化して仮置きする・メールを送る・検証する）はどれも同じなので、
 * 1つのクラスに、Staffの管理者2段階認証（TOTP）でいう
 * TwoFactorAuthenticator + BackupCodeGeneratorに相当する役割をまとめている。
 * TOTPと違うのは、秘密鍵を長期間覚えておく必要が無い（毎回その場で
 * 作って、確認できたら即座に捨てる）ため、DBのテーブルを持たず、
 * セッションだけで完結させている点。confirm_token（ContactController）や
 * 管理ログインの2段階目（TwoFactorChallengeController::PENDING_SECRET_SESSION_KEY）
 * と同じ「短命な状態はセッションに置く」という、このプロジェクトの
 * 一貫した考え方に沿っている。
 *
 * 用途（$purpose）ごとに別々のセッションキーを使うので、例えば
 * 「ログイン用に発行したコード」を「マイページのパスワード変更」画面で
 * 誤って使い回せてしまう、といった事故は起きない。
 *
 * 会員登録だけは、コードを送る時点で会員がまだ存在しない（idが無い）ので、
 * 会員ではなく宛先のメールアドレスに紐付けて発行・検証する
 * （issueForAddress()・verifyForAddress()）。
 */
class MemberVerificationCode
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_MYPAGE_PASSWORD = 'mypage_password';

    public const PURPOSE_REGISTER = 'register';

    public const PURPOSE_PASSKEY = 'passkey';

    /** コードの桁数。TOTPの6桁コードと同じ桁数に揃え、利用者の混乱を減らす。 */
    private const CODE_LENGTH = 6;

    /** コードの有効時間（分）。長すぎると総当たりの猶予を与え、短すぎると
     *  メールの到着が遅れたときに間に合わない。10分は一般的なOTPメールの
     *  相場に合わせた値。 */
    private const VALID_MINUTES = 10;

    private function sessionKey(string $purpose): string
    {
        return "member.verification_code.$purpose";
    }

    /**
     * コードを発行し、セッションにハッシュ化して仮置きした上でメール送信する。
     *
     * コードそのものは平文のままセッションに置かない（ハッシュ化して
     * 保存し、検証時はHash::check()で照合する）。管理ログインの
     * バックアップコード（BackupCodeGenerator）と同じ考え方——セッションの
     * 中身が何らかの理由（ログ・デバッグ出力等）で漏れても、コード自体は
     * 読み取れないようにしている。
     *
     * メール送信に失敗した場合はfalseを返す。呼び出し側の扱いは用途に
     * よって変える必要がある：ログイン・マイページ変更は「本人だと
     * 確認済み」の状態から呼ぶので、失敗をそのままエラー表示してよい。
     * 一方パスワード再設定（未ログイン、メールアドレスを入力しただけ）は、
     * 「そのメールアドレスの会員が存在しない」場合にissue()自体を
     * 呼ばない設計と合わせて、失敗時も画面には常に同じ案内を出す
     * （PasswordResetController::sendCode()参照）。ここで送信失敗の
     * 有無によって案内文を変えてしまうと、存在確認の材料を与えてしまう
     * ため。
     */
    public function issue(Request $request, Member $member, string $purpose): bool
    {
        $code = $this->generateCode();

        $request->session()->put($this->sessionKey($purpose), [
            'member_id' => $member->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES)->timestamp,
        ]);

        return $this->sendMail($member->email, $member->name, $code, $purpose, ['member_id' => $member->id]);
    }

    /**
     * 会員がまだ存在しない用途（会員登録）向けに、宛先のメールアドレスに
     * 紐付けてコードを発行し、メールを送る。セッションへの置き方・送信失敗時に
     * falseを返すことはissue()と同じ。
     *
     * @param  string  $name  メール本文の宛名（「○○ 様」）に使う
     */
    public function issueForAddress(Request $request, string $email, string $name, string $purpose): bool
    {
        $code = $this->generateCode();

        $request->session()->put($this->sessionKey($purpose), [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES)->timestamp,
        ]);

        return $this->sendMail($email, $name, $code, $purpose, []);
    }

    /**
     * 指定した用途・会員について、まだ有効なコードを発行済みか。
     *
     * マイページのパスワード変更画面（AuthPasswordController::edit()）で、
     * 画面を再読み込みするたびに新しいコードを送り直してしまわないよう、
     * GETのたびにこれを先に確認し、trueならissue()を呼ばない。
     */
    public function hasPending(Request $request, string $purpose, Member $member): bool
    {
        $state = $request->session()->get($this->sessionKey($purpose));

        return is_array($state)
            && ($state['member_id'] ?? null) === $member->id
            && ($state['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * 入力されたコードを検証する。成功したら対象のMemberを返し、
     * セッションの仮置きは（1回使い切りのため）消す。失敗時はnull。
     *
     * 期限切れの場合もここでセッションを消す。「期限切れの古いコードが
     * セッションに残り続けて、次にissue()するまで何度でも判定対象になる」
     * という状態を残さないため。
     */
    public function verify(Request $request, string $purpose, string $inputCode): ?Member
    {
        $state = $this->consume($request, $purpose, $inputCode);

        if ($state === null || ! isset($state['member_id'])) {
            return null;
        }

        return Member::find($state['member_id']);
    }

    /**
     * issueForAddress()で発行したコードを検証する。成功したら、発行時の
     * メールアドレスを返す（呼び出し側で、登録しようとしているアドレスと
     * 一致するかを確かめる）。失敗時はnull。1回使い切り・期限切れの扱いは
     * verify()と同じ。
     */
    public function verifyForAddress(Request $request, string $purpose, string $inputCode): ?string
    {
        $state = $this->consume($request, $purpose, $inputCode);

        return is_string($state['email'] ?? null) ? $state['email'] : null;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * verify()・verifyForAddress()共通の照合。一致したらセッションの仮置きを
     * 消して、その中身を返す。期限切れの場合もセッションを消す
     * （期限切れの古いコードが、次にissue()するまで何度でも判定対象に
     * なる状態を残さないため）。
     */
    private function consume(Request $request, string $purpose, string $inputCode): ?array
    {
        $key = $this->sessionKey($purpose);
        $state = $request->session()->get($key);

        if (! is_array($state) || ($state['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget($key);

            return null;
        }

        if (! Hash::check($inputCode, $state['code_hash'])) {
            return null;
        }

        $request->session()->forget($key);

        return $state;
    }

    /**
     * $purposeに応じた案内文でメールを組み立てて送る。
     *
     * ContactController::sendStaffNotification()と同じ考え方で、送信例外は
     * ここで捕まえてログに残す（呼び出し側にThrowableを伝播させない）。
     * ただし問い合わせと違い、こちらは「送れたかどうか」がその後の手続きの
     * 続行可否に直結する（データは既に保存済み、ではない）ため、
     * bool を返して呼び出し側に伝える。
     *
     * @param  array<string, mixed>  $logContext  送信失敗時のログに添える情報。
     *         会員がいる用途ではmember_idを入れる。メールアドレスは個人情報なので
     *         ログには出さない。
     */
    private function sendMail(string $email, string $name, string $code, string $purpose, array $logContext): bool
    {
        $label = match ($purpose) {
            self::PURPOSE_LOGIN => 'ログイン',
            self::PURPOSE_PASSWORD_RESET => 'パスワードの再設定',
            self::PURPOSE_MYPAGE_PASSWORD => 'パスワードの変更',
            self::PURPOSE_REGISTER => '会員登録',
            self::PURPOSE_PASSKEY => 'パスキーの登録',
            default => 'お手続き',
        };

        try {
            Mail::send(new TemplatedMail('member_verification_code', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_mail' => $email,
                'member_name' => $name,
                'purpose_label' => $label,
                'code' => $code,
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::error('MemberVerificationCode: 確認コードメールの送信に失敗しました。', $logContext + [
                'purpose' => $purpose,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
