<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 企業会員の担当者のパスワードの変更（マイページ）と再設定（パスワードを忘れたとき）。
 * どちらも、担当者のメールアドレスに送る確認コードで本人を確かめてから変える。
 * 再設定は、企業ID・担当者ID・メールアドレスの3つが合う担当者にだけ、確認コードを送る。
 */
class CompanyPasswordTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const OLD_PASSWORD = 'old-password';

    private const NEW_PASSWORD = 'new-password';

    private function company(string $code = 'acme', CompanyStatus $status = CompanyStatus::Approved): Company
    {
        return Company::create(['code' => $code, 'name' => '株式会社'.$code, 'tel' => '03-1234-5678', 'status' => $status]);
    }

    private function user(Company $company, string $loginId = 'yamada', string $email = 'yamada@example.com'): CompanyUser
    {
        return CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => $loginId,
            'name' => '山田 太郎',
            'email' => $email,
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);
    }

    private function newPassword(string $code): array
    {
        return ['code' => $code, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];
    }

    private function forgotInput(array $override = []): array
    {
        return $override + ['company_code' => 'acme', 'login_id' => 'yamada', 'email' => 'yamada@example.com'];
    }

    public function test_change_in_mypage(): void
    {
        $user = $this->user($this->company());
        $user->trustedDevices()->create(['token_hash' => 'x', 'expires_at' => now()->addDay()]);

        // 変更の画面を開くと、本人のメールアドレスに確認コードが届く
        $this->actingAs($user, 'company')->get('/company/mypage/password')->assertOk();
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $code = $this->lastVerificationCode();

        // 画面を開き直しても、有効なコードがあれば送り直さない
        $this->actingAs($user, 'company')->get('/company/mypage/password')->assertOk();
        $this->assertCount(1, $this->sentMails());

        $this->actingAs($user, 'company')->patch('/company/mypage/password/update', $this->newPassword($code))
            ->assertRedirect(route('company.mypage'));

        // パスワードが変わり、記憶済みの端末が消え、お知らせのメールが届く
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(0, $user->trustedDevices()->count());
        $this->assertSame('パスワード変更のお知らせ', $this->lastMail()->getSubject());
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString(route('company.password.forgot'), $this->lastMail()->getTextBody());

        // 本人の操作として、操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['company_user', $user->id, 'company_user', $user->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);

        // 変えた本人は、ログインしたまま使い続けられる
        $this->get('/company/mypage')->assertOk();
        $this->assertAuthenticatedAs($user, 'company');
    }

    public function test_change_needs_the_right_code(): void
    {
        $user = $this->user($this->company());

        $this->actingAs($user, 'company')->get('/company/mypage/password');
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->actingAs($user, 'company')->patch('/company/mypage/password/update', $this->newPassword($wrong))
            ->assertRedirect(route('company.password.edit'))->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_when_forgotten(): void
    {
        $user = $this->user($this->company());

        $this->get('/company/password/forgot')->assertOk();
        $this->post('/company/password/forgot', $this->forgotInput())->assertRedirect(route('company.password.reset'));
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $code = $this->lastVerificationCode();

        $this->get('/company/password/reset')->assertOk();
        $this->post('/company/password/reset', $this->newPassword($code))->assertRedirect(route('company.mypage'));

        // パスワードが変わり、そのままログインした状態になる。個人会員としては、ログインしない
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertAuthenticatedAs($user, 'company');
        $this->assertGuest('web');
        $this->assertSame('パスワード変更のお知らせ', $this->lastMail()->getSubject());

        // ログインの前の操作でも、本人の操作として残る
        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['company_user', $user->id], [$log->operator_type, $log->operator_id]);
    }

    public function test_forgot_needs_all_three_inputs_to_match(): void
    {
        $this->user($this->company());

        // 企業ID・担当者ID・メールアドレスのどれが違っても、同じ画面へ進む。メールは送らない
        foreach ([['company_code' => 'nobody'], ['login_id' => 'nobody'], ['email' => 'nobody@example.com']] as $wrong) {
            $this->post('/company/password/forgot', $this->forgotInput($wrong))->assertRedirect(route('company.password.reset'));
        }

        $this->assertCount(0, $this->sentMails());
    }

    public function test_forgot_picks_one_user_even_if_the_address_is_shared(): void
    {
        // 同じメールアドレスを、同じ企業の別の担当者と、別の企業の同じ担当者IDの人が使っている
        $company = $this->company('acme');
        $yamada = $this->user($company, 'yamada', 'info@example.com');
        $sato = $this->user($company, 'sato', 'info@example.com');
        $other = $this->user($this->company('other'), 'yamada', 'info@example.com');

        $this->post('/company/password/forgot', $this->forgotInput(['email' => 'info@example.com']));
        $this->assertCount(1, $this->sentMails());
        $this->post('/company/password/reset', $this->newPassword($this->lastVerificationCode()));

        // 企業IDと担当者IDで決まる1人だけが変わる
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $yamada->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $sato->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $other->fresh()->password));
    }

    public function test_reset_needs_the_right_code(): void
    {
        $user = $this->user($this->company());

        $this->post('/company/password/forgot', $this->forgotInput());
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/company/password/reset', $this->newPassword($wrong))
            ->assertRedirect(route('company.password.reset'))->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        $this->assertGuest('company');
    }

    public function test_reset_does_not_log_in_a_user_of_a_stopped_company(): void
    {
        $user = $this->user($this->company('acme', CompanyStatus::Suspended));

        $this->post('/company/password/forgot', $this->forgotInput());
        $this->post('/company/password/reset', $this->newPassword($this->lastVerificationCode()))
            ->assertRedirect(route('company.login'));

        // パスワードは変わるが、止めた企業の担当者はログインさせない
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertGuest('company');
    }

    public function test_reset_clears_the_old_password_of_a_migrated_user(): void
    {
        // 既存のシステムから移して、まだ一度もログインしていない担当者
        $user = $this->user($this->company());
        $user->forceFill(['password' => null, 'legacy_password' => Hash::make(md5('old-system-password'))])->save();

        $this->post('/company/password/forgot', $this->forgotInput());
        $this->post('/company/password/reset', $this->newPassword($this->lastVerificationCode()));

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNull($user->legacy_password);
    }
}
