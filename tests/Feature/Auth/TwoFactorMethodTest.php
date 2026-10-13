<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Auth\BackupCodeGenerator;
use Neobit\Core\Auth\TrustedDeviceManager;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Enums\TwoFactorMethod;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Models\Passkey;
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

        // 認証アプリのコードで登録が済むと、本ログインになる。バックアップコードは、自動では発行しない
        $this->post('/login/verify', ['code' => $this->codeForPendingSecret('member')])
            ->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');
        $this->assertTrue($member->refresh()->hasTwoFactorConfirmed());
        $this->assertSame(0, $member->backupCodes()->count());
    }

    public function test_unregistered_member_is_not_let_through_even_on_a_trusted_device(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();

        // メールの方式の頃に信頼した端末が残っていても、認証アプリの登録は省かない
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

        // 1回目のログインで登録する。秘密鍵は、登録の画面を開いたときに作られる
        $this->loginAsMember();
        $this->get('/login/verify');
        $secret = session('member.login.pending_secret');
        $this->post('/login/verify', ['code' => (new Google2FA())->getCurrentOtp($secret)]);
        $this->post('/logout');

        // 2回目からは、認証アプリのコードの入力になる。バックアップコードの入力欄は、発行するまで出ない
        $this->loginAsMember();
        $this->get('/login/verify')->assertOk()->assertDontSee('バックアップコード')->assertDontSee('QR');
        $this->post('/login/verify', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');

        // 自分の画面で、認証アプリのコードを入れて発行する。発行したコードは、1回だけ見られる
        $this->get('/mypage/two-factor')->assertOk()->assertSee('バックアップコードを発行する');
        $backupCodes = $this->post('/mypage/two-factor/backup-codes/regenerate', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('twoFactor.backupCodes'))
            ->getSession()->get('newBackupCodes');
        $this->assertCount(10, $backupCodes);
        $this->get('/mypage/two-factor/backup-codes')->assertOk()->assertSee($backupCodes[0]);
        $this->get('/mypage/two-factor/backup-codes')->assertRedirect(route('mypage'));
        $this->get('/mypage/two-factor')->assertOk()->assertSee('バックアップコードを再発行する');
        $this->post('/logout');

        // 発行した後は、2段階目の画面に入力欄が出る
        $this->loginAsMember();
        $this->get('/login/verify')->assertOk()->assertSee('バックアップコード');
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
        $member->registerTwoFactor($secret);

        // マイページに入口が出る
        $this->actingAs($member, 'web')->get('/mypage')
            ->assertOk()->assertSee(route('mypage.twoFactor'))->assertSee('>2段階認証</a>', false);
        $this->actingAs($member, 'web')->get('/mypage/two-factor')->assertOk()->assertSee('2段階認証の登録を解除する');

        // 今のコードが要る
        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['code' => '000000'])
            ->assertRedirect(route('mypage.twoFactor'));
        $this->assertTrue($member->refresh()->hasTwoFactorConfirmed());

        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('mypage.twoFactor'));
        $this->assertFalse($member->refresh()->hasTwoFactorConfirmed());
        $this->assertNull($member->totpSecret());

        // 本人による解除は、操作ログに残る
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::TwoFactorReset)
            ->where('operator_type', 'member')->where('operator_id', $member->id)->count());
    }

    public function test_member_can_reset_the_authenticator_by_a_backup_code(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();
        $member->registerTwoFactor((new Google2FA())->generateSecretKey());
        $backupCodes = (new BackupCodeGenerator())->generateFor($member);

        // 間違ったバックアップコードでは解除できない
        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['backup_code' => '0000-0000'])
            ->assertSessionHasErrors('backup_code', errorBag: 'selfResetTwoFactor');
        $this->assertTrue($member->refresh()->hasTwoFactorConfirmed());

        // 認証アプリが手元に無くても、バックアップコードで解除できる
        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['backup_code' => $backupCodes[0]])
            ->assertRedirect(route('mypage.twoFactor'));
        $this->assertFalse($member->refresh()->hasTwoFactorConfirmed());
        $this->assertSame(0, $member->backupCodes()->count());
    }

    public function test_backup_code_is_not_accepted_for_regenerating_backup_codes(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();
        $member->registerTwoFactor((new Google2FA())->generateSecretKey());
        $backupCodes = (new BackupCodeGenerator())->generateFor($member);

        // 作り直しは、今の認証アプリのコードだけで確かめる
        $this->actingAs($member, 'web')->post('/mypage/two-factor/backup-codes/regenerate', ['backup_code' => $backupCodes[0]])
            ->assertSessionHasErrors('code', errorBag: 'regenerateBackupCodes');
        $this->assertSame(10, $member->backupCodes()->whereNull('used_at')->count());
    }

    public function test_staff_who_lost_the_phone_logs_in_and_resets_by_backup_codes(): void
    {
        // 見本のサイトの方式のまま（スタッフは認証アプリ）。管理者が1人だけでも、自分で解除できる
        $staff = $this->staff();
        $staff->registerTwoFactor((new Google2FA())->generateSecretKey());
        $backupCodes = (new BackupCodeGenerator())->generateFor($staff);

        // バックアップコードでログインする
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->post('/admin/login/verify', ['backup_code' => $backupCodes[0]])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff, 'admin');

        // 使ったコードは、もう使えない
        $this->delete('/admin/2fa', ['backup_code' => $backupCodes[0]])
            ->assertSessionHasErrors('backup_code', errorBag: 'selfResetTwoFactor');

        // 別のバックアップコードで、自分の認証アプリの登録を解除する
        $this->delete('/admin/2fa', ['backup_code' => $backupCodes[1]])
            ->assertRedirect(route('admin.staff.show', $staff));
        $this->assertFalse($staff->refresh()->hasTwoFactorConfirmed());
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::TwoFactorReset)
            ->where('operator_type', 'staff')->where('operator_id', $staff->id)->count());

        // 次のログインで、QRコードの登録を求められる
        $this->post('/admin/logout');
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.login.verify'));
        $this->get('/admin/login/verify')->assertOk()->assertSee('QR');
    }

    public function test_staff_can_reset_the_authenticator_of_a_member(): void
    {
        $this->useTwoFactorMethod(Member::class, TwoFactorMethod::Totp);
        $member = $this->member();
        $member->registerTwoFactor((new Google2FA())->generateSecretKey());
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

    public function test_reset_is_not_offered_while_the_method_is_not_totp(): void
    {
        // 見本のサイトの方式（会員はメール）では、解除のボタンは出ない。バックアップコードの発行だけが出る
        $member = $this->member();

        $this->actingAs($member, 'web')->get('/mypage')
            ->assertOk()->assertSee(route('mypage.twoFactor'))->assertSee('>バックアップコード</a>', false);
        $this->actingAs($member, 'web')->get('/mypage/two-factor')
            ->assertOk()->assertSee('<title>バックアップコード</title>', false)
            ->assertSee('バックアップコードを発行する')->assertDontSee('2段階認証の登録を解除する');
        $this->actingAs($member, 'web')->delete('/mypage/two-factor', ['code' => '000000'])->assertNotFound();
        $this->actingAs($this->staff(), 'admin')->get(route('admin.members.show', $member))
            ->assertOk()->assertDontSee('2段階認証の登録を解除する');
    }

    // ---- バックアップコード。使うかどうかは、モデルの定数で選ぶ ----

    public function test_member_with_email_method_issues_backup_codes_and_logs_in_by_one(): void
    {
        // 見本のサイトの方式（会員はメール）。発行の前の本人確認は、メールの確認コード
        $member = $this->member();

        // 確認コードを送る前は、発行できない
        $this->actingAs($member, 'web')->post('/mypage/two-factor/backup-codes/regenerate', ['code' => '000000'])
            ->assertSessionHasErrors('code', errorBag: 'regenerateBackupCodes');
        $this->assertSame(0, $member->backupCodes()->count());

        // 確認コードを送ると、入力欄が出る
        $this->post('/mypage/two-factor/backup-codes/code')->assertRedirect(route('mypage.twoFactor'));
        $this->assertStringContainsString('バックアップコードの発行', (string) $this->lastMail()->getTextBody());
        $this->get('/mypage/two-factor')->assertOk()->assertSee('メールでお送りした確認コード');

        // 届いたコードで発行する
        $backupCodes = $this->post('/mypage/two-factor/backup-codes/regenerate', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('twoFactor.backupCodes'))
            ->getSession()->get('newBackupCodes');
        $this->assertSame(10, $member->backupCodes()->count());
        $this->post('/logout');

        // ログインの2段階目を、メールの確認コードの代わりにバックアップコードで通る。同じコードは2回使えない
        $this->loginAsMember();
        $this->get('/login/verify')->assertOk()->assertSee('バックアップコード');
        $this->post('/login/verify', ['backup_code' => $backupCodes[0]])->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');
        $this->post('/logout');

        $this->loginAsMember();
        $this->post('/login/verify', ['backup_code' => $backupCodes[0]])->assertSessionHasErrors('backup_code');
        $this->assertGuest('web');
    }

    public function test_login_code_is_not_accepted_for_issuing_backup_codes(): void
    {
        // ログインのために送った確認コードは、発行の本人確認には使えない
        $member = $this->member();
        $this->loginAsMember();
        $loginCode = $this->lastVerificationCode();

        $this->actingAs($member, 'web')->post('/mypage/two-factor/backup-codes/regenerate', ['code' => $loginCode])
            ->assertSessionHasErrors('code', errorBag: 'regenerateBackupCodes');
        $this->assertSame(0, $member->backupCodes()->count());
    }

    public function test_backup_codes_are_not_used_by_a_model_that_turns_them_off(): void
    {
        $this->useBackupCodes(Staff::class, false);
        $staff = $this->staff();
        $secret = (new Google2FA())->generateSecretKey();
        $staff->registerTwoFactor($secret);

        // 使う設定の頃に発行したコードが残っていても、通さない
        $backupCodes = (new BackupCodeGenerator())->generateFor($staff);

        // ログインの2段階目に入力欄が出ず、送っても通らない
        $this->post('/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->get('/admin/login/verify')->assertOk()->assertDontSee('バックアップコード');
        $this->post('/admin/login/verify', ['backup_code' => $backupCodes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('admin');

        // 自分の画面にも、発行のボタンと入力欄が出ない。解除は、認証アプリのコードだけで確かめる
        $this->post('/admin/login/verify', ['code' => (new Google2FA())->getCurrentOtp($secret)]);
        $this->get(route('admin.staff.show', $staff))
            ->assertOk()->assertDontSee('バックアップコード')->assertSee('2段階認証の登録を解除する');
        $this->post('/admin/2fa/backup-codes/regenerate', ['code' => (new Google2FA())->getCurrentOtp($secret)])->assertNotFound();
        $this->delete('/admin/2fa', ['backup_code' => $backupCodes[1]])
            ->assertSessionHasErrors('code', errorBag: 'selfResetTwoFactor');
        $this->assertTrue($staff->refresh()->hasTwoFactorConfirmed());
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
        $member->registerTwoFactor($secret);

        // 確認コードのメールは使わない
        $this->actingAs($member, 'web')->post('/mypage/passkeys/code')->assertNotFound();

        $this->actingAs($member, 'web')->post('/mypage/passkeys/confirm', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertSessionHasNoErrors();
        $this->actingAs($member, 'web')->getJson('/mypage/passkeys/options')->assertOk();
    }
}
