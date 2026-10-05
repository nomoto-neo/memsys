<?php

namespace Tests\Feature\Auth;

use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * 管理画面（スタッフ）のログインの流れ。ログインIDとパスワードを確かめ、認証アプリのコードで
 * 本ログインにする。信頼済みの端末では認証アプリのコードを省く。
 */
class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    private string $secret;

    // 2段階認証を登録済みのスタッフ
    private function staff(StaffAcl $acl = StaffAcl::Manager): Staff
    {
        $this->secret = (new Google2FA())->generateSecretKey();

        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make(self::PASSWORD),
            'acl' => $acl,
        ]);
        $staff->forceFill(['totp_secret' => $this->secret, 'totp_confirmed_at' => now()])->save();

        return $staff;
    }

    // 認証アプリが今出しているコード
    private function currentCode(): string
    {
        return (new Google2FA())->getCurrentOtp($this->secret);
    }

    public function test_password_then_authenticator_code_logs_in(): void
    {
        $staff = $this->staff();

        // 1段階目。パスワードが合っても、まだログインにはならない
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.twoFactor.show'));
        $this->assertGuest('admin');
        $this->get('/admin/2fa')->assertOk();

        // 2段階目。認証アプリのコードで本ログインになる
        $this->post('/admin/2fa/verify', ['code' => $this->currentCode()])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'admin');

        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Login)
            ->where('operator_type', 'staff')->where('operator_id', $staff->id)->count());
    }

    public function test_wrong_password_is_rejected_and_recorded(): void
    {
        $this->staff();

        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => 'wrong-password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login_id');
        $this->assertGuest('admin');

        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->sole();
        $this->assertNull($log->operator_id);
        $this->assertSame(['login_id' => 'hanako'], $log->detail);
    }

    public function test_wrong_authenticator_code_does_not_log_in(): void
    {
        $staff = $this->staff();

        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $wrong = $this->currentCode() === '000000' ? '111111' : '000000';

        $this->post('/admin/2fa/verify', ['code' => $wrong])
            ->assertRedirect(route('admin.twoFactor.show'))
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');

        // パスワードは通っているので、そのスタッフの失敗として残る
        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->sole();
        $this->assertSame(['staff', $staff->id], [$log->operator_type, $log->operator_id]);
    }

    public function test_second_step_needs_the_first_step(): void
    {
        $this->get('/admin/2fa')->assertRedirect(route('admin.login'));
    }

    public function test_trusted_device_skips_the_authenticator_code(): void
    {
        $staff = $this->staff();

        // 「この端末を信頼する」を付けてログインする
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $response = $this->post('/admin/2fa/verify', ['code' => $this->currentCode(), 'remember_device' => '1']);
        $cookie = $response->getCookie('staff_trusted_device');
        $this->assertSame('/', $cookie->getPath());

        // ログアウトして、同じ端末でもう一度。認証アプリのコードは求められない
        $this->post('/admin/logout');
        $this->assertGuest('admin');

        $this->withCookie('staff_trusted_device', $cookie->getValue())
            ->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'admin');
    }

    public function test_member_device_cookie_is_not_trusted_for_staff(): void
    {
        $this->staff();

        // 会員の側で記憶した端末の値を、スタッフの側のCookieとして送っても、信頼されない
        $member = Member::factory()->create();
        $member->trustedDevices()->create(['token_hash' => hash('sha256', 'member-token'), 'expires_at' => now()->addDay()]);

        $this->withCookie('staff_trusted_device', 'member-token')
            ->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.twoFactor.show'));
        $this->assertGuest('admin');
    }

    public function test_staff_without_two_factor_is_sent_to_the_setup(): void
    {
        Staff::create([
            'name' => '新人',
            'login_id' => 'newcomer',
            'email' => 'new@example.com',
            'password' => Hash::make(self::PASSWORD),
            'acl' => StaffAcl::Staff,
        ]);

        // 2段階認証をまだ登録していないスタッフは、登録の画面（QRコード）になる
        $this->post('/admin/login', ['login_id' => 'newcomer', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.twoFactor.show'));
        $this->get('/admin/2fa')->assertOk()->assertSee('QR');
        $this->assertGuest('admin');
    }

    public function test_login_returns_to_the_page_that_required_it(): void
    {
        $this->staff();

        $this->get('/admin/members')->assertRedirect(route('admin.login'));

        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->post('/admin/2fa/verify', ['code' => $this->currentCode()])
            ->assertRedirect(route('admin.members.index'));
    }

    public function test_logout(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Logout)
            ->where('operator_type', 'staff')->where('operator_id', $staff->id)->count());

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
