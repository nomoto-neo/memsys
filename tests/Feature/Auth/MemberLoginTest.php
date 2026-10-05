<?php

namespace Tests\Feature\Auth;

use App\Enums\OperationLogAction;
use App\Models\Member;
use App\Models\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 個人会員のログインの流れ。メールアドレスとパスワードを確かめ、メールの確認コードで
 * 本ログインにする。記憶済みの端末では確認コードを省く。
 */
class MemberLoginTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    private function member(): Member
    {
        return Member::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    public function test_password_then_verification_code_logs_in(): void
    {
        $member = $this->member();

        // 1段階目。パスワードが合っても、まだログインにはならない
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
        $this->assertGuest('web');
        $this->assertSame(['taro@example.com'], $this->recipientsOf($this->lastMail()));

        // 2段階目。メールの確認コードで本ログインになる
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');

        // ログインは操作ログに残る
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Login)
            ->where('operator_type', 'member')->where('operator_id', $member->id)->count());
    }

    public function test_wrong_password_is_rejected_and_recorded(): void
    {
        $this->member();

        $this->post('/login', ['email' => 'taro@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertCount(0, $this->sentMails());

        // 誰か分からない失敗として、入力されたメールアドレスと一緒に残る
        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->sole();
        $this->assertNull($log->operator_id);
        $this->assertSame(['login_id' => 'taro@example.com'], $log->detail);
    }

    public function test_wrong_verification_code_does_not_log_in(): void
    {
        $member = $this->member();

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/login/verify', ['code' => $wrong])
            ->assertRedirect(route('login.verify'))
            ->assertSessionHasErrors('code');
        $this->assertGuest('web');

        // パスワードは通っているので、その会員の失敗として残る
        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->sole();
        $this->assertSame(['member', $member->id], [$log->operator_type, $log->operator_id]);
    }

    public function test_verification_screen_needs_the_first_step(): void
    {
        $this->get('/login/verify')->assertRedirect(route('login'));
        $this->post('/login/verify', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_remembered_device_skips_the_verification_code(): void
    {
        $member = $this->member();

        // 「このデバイスを記憶する」を付けてログインする
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $response = $this->post('/login/verify', ['code' => $this->lastVerificationCode(), 'remember_device' => '1']);
        $cookie = $response->getCookie('member_trusted_device');
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame(1, $member->trustedDevices()->count());

        // ログアウトして、同じ端末でもう一度。確認コードは求められない
        $this->post('/logout');
        $this->assertGuest('web');
        $mails = $this->sentMails()->count();

        $this->withCookie('member_trusted_device', $cookie->getValue())
            ->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');
        $this->assertSame($mails, $this->sentMails()->count());
    }

    public function test_unknown_device_cookie_still_needs_the_verification_code(): void
    {
        $this->member();

        $this->withCookie('member_trusted_device', 'not-a-real-token')
            ->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
        $this->assertGuest('web');
    }

    public function test_login_is_blocked_after_repeated_failures(): void
    {
        $this->member();

        // 同じメールアドレスで5回失敗すると、正しいパスワードでも通らなくなる
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'taro@example.com', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertCount(0, $this->sentMails());
    }

    public function test_login_returns_to_the_page_that_required_it(): void
    {
        $this->member();

        // ログインが必要な画面を開くと、ログイン画面へ回される
        $this->get('/mypage/edit')->assertRedirect(route('login'));

        // ログインの後は、開こうとしていた画面へ戻る
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage.edit'));
    }

    public function test_logout(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')->post('/logout')->assertRedirect('/');
        $this->assertGuest('web');
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Logout)
            ->where('operator_type', 'member')->where('operator_id', $member->id)->count());

        $this->get('/mypage')->assertRedirect(route('login'));
    }
}
