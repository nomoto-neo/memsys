<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 会員向けの、メールで送る確認コードの発行と照合。
 *
 * 使い道はログイン・パスワードの再設定・マイページのパスワード変更・会員登録・
 * パスキーの登録の前の本人確認・初回のログインでの登録・メールアドレスの変更の7つ。
 * どれもコードを作ってハッシュ値にしてセッションに仮置きし、
 * メールで送って照合するという同じ処理なので、1つのクラスにまとめている。
 * コードはその場で作って使ったら捨てるので、DBには持たずセッションだけで済ませる。
 *
 * 使い道ごとにセッションのキーを分けているので、ある使い道で発行したコードを
 * 別の画面で使い回すことはできない。
 * 会員登録・初回のログインでの登録・メールアドレスの変更は、メールアドレスがまだ
 * 保存されていないので、宛先のメールアドレスに結び付けて発行と照合をする。
 *
 * 会員の種類（個人会員か、企業の担当者か）は、作るときにモデルのクラスで渡す。
 *     new MemberVerificationCode(Member::class)
 * メールのテンプレートと、セッションのキーは、種類の名前から決まる（個人会員なら
 * member_verification_codeと、member.verification_code.使い道）。同じブラウザで個人会員と
 * 企業の担当者の両方を使っても、片方のコードがもう片方の仮置きを上書きしない。
 * 仮置きには、会員の種類とidの両方を持つ。種類が違えばidが同じでも別の人なので、
 * 取り違えないようにするため。
 */
class MemberVerificationCode
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_MYPAGE_PASSWORD = 'mypage_password';

    public const PURPOSE_REGISTER = 'register';

    public const PURPOSE_PASSKEY = 'passkey';

    public const PURPOSE_FIRST_LOGIN = 'first_login';

    public const PURPOSE_EMAIL_CHANGE = 'email_change';

    /** メールのテンプレートの、会員の種類の名前を除いた名前。例：member_verification_code */
    private const TEMPLATE = 'verification_code';

    /** コードの桁数。認証アプリのコードと同じ6桁にそろえ、利用者が迷わないようにする */
    private const CODE_LENGTH = 6;

    /**
     * コードの有効な分数。長すぎると総当たりの時間を与え、短すぎるとメールが遅れたときに
     * 間に合わない。よくある確認コードのメールに合わせた長さ
     */
    private const VALID_MINUTES = 10;

    /**
     * @param  class-string<MemberAccount>  $memberClass  確認コードを送る相手の、会員のモデル
     */
    public function __construct(private readonly string $memberClass)
    {
    }

    /** 会員の種類と使い道ごとのセッションのキー */
    private function sessionKey(string $purpose): string
    {
        return $this->memberClass::memberType().".verification_code.$purpose";
    }

    /**
     * コードを発行し、ハッシュ値にしてセッションに仮置きしてからメールで送る。
     * セッションの中身が漏れてもコードそのものは分からないよう、ハッシュ値にしている。
     *
     * 送れなかったときはfalseを返す。ログインなど本人と分かっている画面では、そのまま
     * エラーにしてよい。パスワードの再設定では会員かどうかを知られないよう、送れなくても
     * 同じ案内を出す。PasswordResetController::sendCode()がその例。
     */
    public function issue(Request $request, MemberAccount $member, string $purpose): bool
    {
        $code = $this->generateCode();

        $request->session()->put($this->sessionKey($purpose), [
            'member_type' => $member->getMorphClass(),
            'member_id' => $member->getKey(),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES)->timestamp,
        ]);

        return $this->sendMail(
            (string) $member->notificationEmail(),
            $member->displayName(),
            $code,
            $purpose,
            ['member_type' => $member->getMorphClass(), 'member_id' => $member->getKey()],
        );
    }

    /**
     * まだ会員がいない会員登録や、これから変える先のアドレスを確かめるメールアドレスの変更の
     * ために、宛先のメールアドレスに結び付けてコードを発行してメールで送る。それ以外はissue()と同じ。
     *
     * @param  string  $name  メールの宛名に使う
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
     * その使い道と会員に、まだ有効なコードを発行してあるか。マイページのパスワード変更で
     * 画面を読み直すたびにコードを送り直さないよう、先にこれで確かめる。
     */
    public function hasPending(Request $request, string $purpose, MemberAccount $member): bool
    {
        $state = $request->session()->get($this->sessionKey($purpose));

        return is_array($state)
            && ($state['member_type'] ?? null) === $member->getMorphClass()
            && ($state['member_id'] ?? null) === $member->getKey()
            && ($state['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * 入力されたコードを照合する。通ったらその会員を返し、通らなければnullを返す。
     * 通ったら仮置きを消すので、コードは1回だけ使える。
     */
    public function verify(Request $request, string $purpose, string $inputCode): ?MemberAccount
    {
        $state = $this->consume($request, $purpose, $inputCode);

        if ($state === null || ! isset($state['member_type'], $state['member_id'])) {
            return null;
        }

        // 仮置きした種類の名前から、会員のモデルを決めて読む
        $memberClass = Relation::getMorphedModel($state['member_type']);

        return $memberClass !== null ? $memberClass::find($state['member_id']) : null;
    }

    /**
     * issueForAddress()で発行したコードを照合する。通ったら発行したときのメールアドレスを
     * 返すので、登録しようとしているアドレスと同じかは呼ぶ側で確かめる。通らなければnull。
     */
    public function verifyForAddress(Request $request, string $purpose, string $inputCode): ?string
    {
        $state = $this->consume($request, $purpose, $inputCode);

        return is_string($state['email'] ?? null) ? $state['email'] : null;
    }

    /** 前ゼロを含む6桁の数字 */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * verify()とverifyForAddress()に共通の照合。一致したら仮置きを消してその中身を返す。
     * 古いコードが何度でも照合の相手になり続けないよう、期限切れのときも仮置きを消す。
     */
    private function consume(Request $request, string $purpose, string $inputCode): ?array
    {
        $key = $this->sessionKey($purpose);
        $state = $request->session()->get($key);

        // 仮置きが無いか期限切れなら、消して終わる
        if (! is_array($state) || ($state['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget($key);

            return null;
        }

        if (! Hash::check($inputCode, $state['code_hash'])) {
            return null;
        }

        // 一致したので、使い切る
        $request->session()->forget($key);

        return $state;
    }

    /**
     * 使い道に合った案内文でメールを送る。送れなかったときはログに残してfalseを返す。
     * 送れたかどうかで、呼ぶ側がその後の手続きを続けるかを決めるため。
     *
     * @param  array<string, mixed>  $logContext  送れなかったときのログに添える情報。
     *         メールアドレスは個人情報なので、ログには出さない。
     */
    private function sendMail(string $email, string $name, string $code, string $purpose, array $logContext): bool
    {
        $label = match ($purpose) {
            self::PURPOSE_LOGIN => 'ログイン',
            self::PURPOSE_PASSWORD_RESET => 'パスワードの再設定',
            self::PURPOSE_MYPAGE_PASSWORD => 'パスワードの変更',
            self::PURPOSE_REGISTER => '会員登録',
            self::PURPOSE_PASSKEY => 'パスキーの登録',
            self::PURPOSE_FIRST_LOGIN => '初回ログインの登録',
            self::PURPOSE_EMAIL_CHANGE => 'メールアドレスの変更',
            default => 'お手続き',
        };

        try {
            Mail::send(new TemplatedMail($this->memberClass::memberMailTemplate(self::TEMPLATE), [
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
