<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use App\Support\Legacy\Md5Password;
use App\Support\LegacyPasswordUserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;
use Illuminate\Testing\TestResponse;

/**
 * 既存のシステムから移した企業の、初回のログインでの登録。最初の担当者は氏名とメールアドレスが
 * 空なので、ログインの1段階目の後、2段階目の代わりに登録の画面へ回す。企業の電話番号を照合してから、
 * 入力されたメールアドレスに確認コードを送り、コードが合ったら保存してログインを完了する。
 */
class CompanyFirstLoginSetupTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const OLD_PASSWORD = 'shared-password';

    /** 移した企業と、氏名とメールアドレスが空の最初の担当者 */
    private function migratedUser(?string $tel = '03-1234-5678', CompanyStatus $status = CompanyStatus::Approved): CompanyUser
    {
        $company = Company::create(['code' => 'ABC001', 'name' => '株式会社移行', 'tel' => (string) $tel, 'status' => $status]);

        return CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => 'admin',
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);
    }

    private function login(): TestResponse
    {
        return $this->post('/company/login', ['company_code' => 'ABC001', 'login_id' => 'admin', 'password' => self::OLD_PASSWORD]);
    }

    private function setupInput(array $override = []): array
    {
        return $override + [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'login_id' => 'yamada',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'identity' => '03-1234-5678',
        ];
    }

    public function test_first_login_goes_to_setup_and_completes_after_the_code(): void
    {
        $user = $this->migratedUser();

        // 1段階目が通ると、2段階目の代わりに登録の画面へ回る。確認コードは、まだ送らない
        $this->login()->assertRedirect(route('company.login.setup'));
        $this->assertGuest('company');
        $this->assertCount(0, $this->sentMails());
        $this->get('/company/login/setup')->assertOk()->assertSee('株式会社移行');

        // 電話番号が合ったら、入力したメールアドレスに確認コードを送る。まだ保存はしない
        $this->post('/company/login/setup', $this->setupInput())->assertRedirect(route('company.login.setup.verify'));
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString('株式会社移行 山田 太郎', $this->lastMail()->getTextBody());
        $this->assertNull($user->fresh()->email);
        $this->get('/company/login/setup/verify')->assertOk()->assertSee('yamada@example.com');

        // コードが合ったら保存して、ログインを完了する
        $this->post('/company/login/setup/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage'));

        $user->refresh();
        $this->assertSame(['山田 太郎', 'yamada@example.com', 'yamada'], [$user->name, $user->email, $user->login_id]);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertAuthenticatedAs($user, 'company');

        // 操作ログには、本人を操作した人にして、変わった列の名前を残す
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame(['company_user', $user->id, 'company_user', $user->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
        $this->assertEqualsCanonicalizing(['name', 'email', 'login_id', 'password'], $log->changed_fields);

        // 次からは、新しい担当者IDとパスワードで、普通の2段階目になる
        $this->post('/company/logout');
        $this->post('/company/login', ['company_code' => 'ABC001', 'login_id' => 'yamada', 'password' => 'new-password'])
            ->assertRedirect(route('company.login.verify'));
    }

    public function test_identity_is_compared_ignoring_hyphens_and_full_width(): void
    {
        $this->migratedUser('03-1234-5678');
        $this->login();

        $this->post('/company/login/setup', $this->setupInput(['identity' => '０３１２３４ ５６７８']))
            ->assertRedirect(route('company.login.setup.verify'));
    }

    public function test_wrong_identity_is_rejected_recorded_and_throttled(): void
    {
        $user = $this->migratedUser();
        $this->login();

        $this->post('/company/login/setup', $this->setupInput(['identity' => '03-0000-0000']))
            ->assertRedirect(route('company.login.setup'))
            ->assertSessionHasErrors('identity');

        $this->assertCount(0, $this->sentMails());
        $this->assertNull($user->fresh()->email);

        // 1段階目は通っているので、誰の失敗かが操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->sole();
        $this->assertSame(['company_user', $user->id], [$log->operator_type, $log->operator_id]);

        // 続けて間違えると、正しい値でも通らなくなる
        for ($i = 0; $i < 4; $i++) {
            $this->post('/company/login/setup', $this->setupInput(['identity' => '03-0000-0000']));
        }

        $this->post('/company/login/setup', $this->setupInput())->assertSessionHasErrors('identity');
        $this->assertCount(0, $this->sentMails());
    }

    public function test_old_password_cannot_be_kept(): void
    {
        $this->migratedUser();
        $this->login();

        $this->post('/company/login/setup', $this->setupInput(['password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD]))
            ->assertRedirect(route('company.login.setup'))
            ->assertSessionHasErrors('password');

        $this->assertCount(0, $this->sentMails());
    }

    public function test_company_without_the_identity_value_cannot_set_up(): void
    {
        $this->migratedUser('');
        $this->login()->assertRedirect(route('company.login.setup'));

        // フォームを出さずに、問い合わせを案内する。送信されても通さない
        $this->get('/company/login/setup')->assertOk()->assertSee('画面からの登録ができません')->assertDontSee('確認コードを送信する');
        $this->post('/company/login/setup', $this->setupInput(['identity' => '']))->assertSessionHasErrors('identity');
        $this->post('/company/login/setup', $this->setupInput(['identity' => '-']))->assertSessionHasErrors('identity');
        $this->assertCount(0, $this->sentMails());
    }

    public function test_wrong_code_does_not_save(): void
    {
        $user = $this->migratedUser();
        $this->login();
        $this->post('/company/login/setup', $this->setupInput());
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/company/login/setup/verify', ['code' => $wrong])
            ->assertRedirect(route('company.login.setup.verify'))
            ->assertSessionHasErrors('code');

        $this->assertSame(['admin', null], [$user->fresh()->login_id, $user->fresh()->email]);
        $this->assertGuest('company');
    }

    public function test_setup_screens_need_the_first_step(): void
    {
        $this->migratedUser();

        // 1段階目を通っていなければ、ログイン画面へ戻す
        $this->get('/company/login/setup')->assertRedirect(route('company.login'));
        $this->post('/company/login/setup', $this->setupInput())->assertRedirect(route('company.login'));
        $this->get('/company/login/setup/verify')->assertRedirect(route('company.login.setup'));
        $this->post('/company/login/setup/verify', ['code' => '123456'])->assertRedirect(route('company.login.setup'));

        $this->assertCount(0, $this->sentMails());
    }

    public function test_abandoned_setup_starts_again_at_the_next_login(): void
    {
        $user = $this->migratedUser();

        $this->login()->assertRedirect(route('company.login.setup'));
        $this->flushSession();

        // 途中でやめても、メールアドレスが空のままなので、次のログインでもう一度登録へ回る
        $this->login()->assertRedirect(route('company.login.setup'));
        $this->assertNull($user->fresh()->email);
    }

    public function test_legacy_password_is_replaced_at_setup(): void
    {
        // 古い方式（MD5）のパスワードで移した担当者
        config(['members.company.legacy_passwords' => [Md5Password::class]]);
        $user = $this->migratedUser();
        $user->forceFill(['password' => null, 'legacy_password' => LegacyPasswordUserProvider::wrap(md5(self::OLD_PASSWORD))])->save();

        $this->login()->assertRedirect(route('company.login.setup'));
        $this->post('/company/login/setup', $this->setupInput())->assertRedirect(route('company.login.setup.verify'));
        $this->post('/company/login/setup/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage'));

        $user->refresh();
        $this->assertNull($user->legacy_password);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->password));
    }

    public function test_registered_user_does_not_go_to_setup(): void
    {
        $user = $this->migratedUser();
        $user->forceFill(['name' => '山田 太郎', 'email' => 'yamada@example.com'])->save();

        // メールアドレスがある担当者は、普通の2段階目へ進む
        $this->login()->assertRedirect(route('company.login.verify'));
        $this->get('/company/login/setup')->assertRedirect(route('company.login'));
    }
}
