<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffAcl;
use App\Enums\TwoFactorMethod;
use App\Models\Member;
use App\Models\Passkey;
use App\Models\Staff;
use App\Support\TrustedDeviceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FakesTwoFactorMethod;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * ログインの2段階目の方式を入れ替えたときの流れ。方式はモデルの定数で選ぶので、見本のサイトの
 * 組み合わせ（会員はメール、スタッフは認証アプリ）のほかも、同じ部品で動くことを確かめる。
 * 見本のサイトの組み合わせは、MemberLoginTestとStaffLoginTestにある。
 */
class TwoFactorMethodTest extends TestCase
{
    use FakesTwoFactorMethod;
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

    private function staff(?string $email = 'hanako@example.com'): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'acl' => StaffAcl::Manager,
        ]);
    }

    private function loginAsMember(): void
    {
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
    }

    /** 2段階目の画面でQRコードを読み取った認証アプリが、今出しているコード */
    private function codeForPendingSecret(string $loginType): string
    {
        return (new Google2FA())->getCurrentOtp(session($loginType.'.login.pending_secret'));
    }

    // ---- なし ----

    public function test_member_without_two_factor_logs_in_right_after_the_password(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::None);
        $member = $this->member();

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('mypage'));

        $this->assertAuthenticatedAs($member, 'web');
        $this->assertCount(0, $this->sentMails());
    }

    public function test_staff_without_two_factor_logs_in_right_after_the_password(): void
    {
        $this->useTwoFactorMethod(Staff::class, TwoFactorMethod::None);
        $staff = $this->staff();

        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff, 'admin');
    }

    public function test_wrong_password_is_still_rejected_without_two_factor(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::None);
        $this->member();

        $this->post('/login', ['email' => 'taro@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    // ---- スタッフをメールの確認コードにする ----

    public function test_staff_with_email_method_logs_in_by_the_mailed_code(): void
    {
        $this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
        $staff = $this->staff();

        // パスワードが合っても、まだログインにはならない。確認コードがスタッフへ届く
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.login.verify'));
        $this->assertGuest('admin');
        $this->assertSame(['hanako@example.com'], $this->recipientsOf($this->lastMail()));

        // 2段階目の画面は、認証アプリではなく確認コードの入力になる
        $this->get('/admin/login/verify')->assertOk()->assertSee('確認コードを再送する')->assertDontSee('QR');

        $this->post('/admin/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'admin');
    }

    public function test_staff_with_email_method_cannot_log_in_with_a_wrong_code(): void
    {
        $this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
        $this->staff();

        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/admin/login/verify', ['code' => $wrong])
            ->assertRedirect(route('admin.login.verify'))
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_staff_email_is_required_while_the_method_is_email(): void
    {
        $actor = $this->staff();
        $input = ['name' => '新人 次郎', 'login_id' => 'jiro', 'email' => '', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'acl' => (string) StaffAcl::Staff->value];

        // 認証アプリの方式では、メールアドレスは任意のまま
        $this->actingAs($actor, 'admin')->post(route('admin.staff.confirm.create'), $input)
            ->assertSessionDoesntHaveErrors('email');

        // メールの方式では、確認コードの宛先になるので必須
        $this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
        $this->actingAs($actor, 'admin')->post(route('admin.staff.confirm.create'), $input)
            ->assertSessionHasErrors('email');
    }

    // ---- 会員を認証アプリにする ----

    public function test_member_with_totp_method_must_register_the_authenticator_at_login(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();

        // 未登録の会員は、パスワードの後にQRコードの登録へ進む。メールは送らない
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
        $this->get('/login/verify')->assertOk()->assertSee('QR');
        $this->assertCount(0, $this->sentMails());
        $this->assertGuest('web');

        // 認証アプリのコードで登録が済むと、本ログインになり、バックアップコードを1回だけ見せる
        $this->post('/login/verify', ['code' => $this->codeForPendingSecret('member')])
            ->assertRedirect(route('twoFactor.backupCodes'));
        $this->assertAuthenticatedAs($member, 'web');
        $this->assertTrue($member->refresh()->hasTwoFactorConfirmed());
        $this->assertSame(10, $member->backupCodes()->count());

        $this->get('/mypage/two-factor/backup-codes')->assertOk()->assertSee('バックアップコード');
        $this->get('/mypage/two-factor/backup-codes')->assertRedirect(route('mypage'));
    }

    public function test_unregistered_member_is_not_let_through_even_on_a_trusted_device(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();

        // メールの方式の頃に記憶した端末が残っていても、認証アプリの登録は省かない
        TrustedDeviceManager::for($member)->remember($member);
        $cookie = collect(cookie()->getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === Member::trustedDeviceCookie());

        $this->withCookie($cookie->getName(), $cookie->getValue())
            ->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
        $this->assertGuest('web');
    }

    public function test_member_with_totp_method_logs_in_by_authenticator_or_backup_code(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();

        // 1回目のログインで登録し、バックアップコードを受け取る。秘密鍵は、登録の画面を開いたときに作られる
        $this->loginAsMember();
        $this->get('/login/verify');
        $secret = session('member.login.pending_secret');
        $backupCodes = $this->post('/login/verify', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->getSession()->get('newBackupCodes');
        $this->post('/logout');

        // 2回目からは、認証アプリのコードの入力になる
        $this->loginAsMember();
        $this->get('/login/verify')->assertOk()->assertSee('バックアップコード')->assertDontSee('QR');
        $this->post('/login/verify', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');
        $this->post('/logout');

        // バックアップコードでも通れる。同じコードは2回使えない
        $this->loginAsMember();
        $this->post('/login/verify', ['backup_code' => $backupCodes[0]])->assertRedirect(route('mypage'));
        $this->post('/logout');

        $this->loginAsMember();
        $this->post('/login/verify', ['backup_code' => $backupCodes[0]])->assertSessionHasErrors('backup_code');
        $this->assertGuest('web');
    }

    public function test_member_can_reset_the_authenticator_by_oneself(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();
        $secret = (new Google2FA())->generateSecretKey();
        $member->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();

        // マイページに入口が出る
        $this->actingAs($member, 'web')->get('/mypage')->assertOk()->assertSee(route('mypage.twoFactor'));
        $this->actingAs($member, 'web')->get('/mypage/two-factor')->assertOk()->assertSee('2段階認証の登録を解除する');

        // 今のコードが要る
        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['code' => '000000'])
            ->assertRedirect(route('mypage.twoFactor'));
        $this->assertTrue($member->refresh()->hasTwoFactorConfirmed());

        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('mypage.twoFactor'));
        $this->assertFalse($member->refresh()->hasTwoFactorConfirmed());
        $this->assertNull($member->totp_secret);
    }

    public function test_staff_can_reset_the_authenticator_of_a_member(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();
        $member->forceFill(['totp_secret' => (new Google2FA())->generateSecretKey(), 'totp_confirmed_at' => now()])->save();
        TrustedDeviceManager::for($member)->remember($member);
        $actor = $this->staff();

        // 会員の詳細に、解除のボタンが出る
        $this->actingAs($actor, 'admin')->get(route('admin.members.show', $member))
            ->assertOk()->assertSee('2段階認証の登録を解除する');

        $this->actingAs($actor, 'admin')->delete(route('admin.members.twoFactor.reset', $member))
            ->assertRedirect(route('admin.members.show', $member));

        // 認証アプリの登録と、信頼済みの端末・パスキーが無効になる
        $member->refresh();
        $this->assertFalse($member->hasTwoFactorConfirmed());
        $this->assertSame(0, $member->trustedDevices()->count());
        $this->assertSame(0, Passkey::count());
    }

    public function test_management_screen_is_not_shown_while_the_method_is_not_totp(): void
    {
        // 見本のサイトの方式（会員はメール）では、入口も解除のボタンも出ない
        $member = $this->member();

        $this->actingAs($member, 'web')->get('/mypage')->assertOk()->assertDontSee(route('mypage.twoFactor'));
        $this->actingAs($this->staff(), 'admin')->get(route('admin.members.show', $member))
            ->assertOk()->assertDontSee('2段階認証の登録を解除する');
    }

    // ---- パスキーの登録の前の本人確認。その人の2段階目の方式で確かめる ----

    public function test_passkey_registration_asks_the_password_when_there_is_no_two_factor(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::None);
        $member = $this->member();

        $this->actingAs($member, 'web')->get('/mypage/passkeys')->assertOk()->assertSee('ログインのパスワードを入力してください');

        // 本人確認が済むまでは、登録に進めない
        $this->actingAs($member, 'web')->getJson('/mypage/passkeys/options')->assertStatus(422);

        $this->actingAs($member, 'web')->post('/mypage/passkeys/confirm', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->actingAs($member, 'web')->getJson('/mypage/passkeys/options')->assertStatus(422);

        $this->actingAs($member, 'web')->post('/mypage/passkeys/confirm', ['password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->actingAs($member, 'web')->getJson('/mypage/passkeys/options')->assertOk();
    }

    public function test_passkey_registration_asks_the_mailed_code_from_staff_with_email_method(): void
    {
        $this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->post('/admin/passkeys/code')->assertSessionHasNoErrors();
        $this->assertSame(['hanako@example.com'], $this->recipientsOf($this->lastMail()));

        $this->actingAs($staff, 'admin')->post('/admin/passkeys/confirm', ['code' => $this->lastVerificationCode()])
            ->assertSessionHasNoErrors();
        $this->actingAs($staff, 'admin')->getJson('/admin/passkeys/options')->assertOk();
    }

    public function test_passkey_registration_asks_the_authenticator_code_from_member_with_totp_method(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();

        // 認証アプリが未登録の間は、本人確認ができないので登録に進めない
        $this->actingAs($member, 'web')->get('/mypage/passkeys')->assertOk()->assertSee('認証アプリ）の登録が必要です');

        $secret = (new Google2FA())->generateSecretKey();
        $member->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();

        // 確認コードのメールは使わない
        $this->actingAs($member, 'web')->post('/mypage/passkeys/code')->assertNotFound();

        $this->actingAs($member, 'web')->post('/mypage/passkeys/confirm', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertSessionHasNoErrors();
        $this->actingAs($member, 'web')->getJson('/mypage/passkeys/options')->assertOk();
    }
}
