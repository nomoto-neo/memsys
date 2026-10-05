<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Member;
use App\Models\OperationLog;
use App\Support\Legacy\Md5Password;
use App\Support\LegacyPasswordUserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 企業会員のログインの流れ。企業ID・担当者ID・パスワードを確かめ、担当者のメールアドレスに送る
 * 確認コードで本ログインにする。ログインできるのは、承認済みの企業の担当者だけ。
 */
class CompanyLoginTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

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
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function credentials(array $override = []): array
    {
        return $override + ['company_code' => 'acme', 'login_id' => 'yamada', 'password' => self::PASSWORD];
    }

    public function test_three_inputs_then_verification_code_logs_in(): void
    {
        $user = $this->user($this->company());

        // 1段階目。3つが合っても、まだログインにはならない
        $this->post('/company/login', $this->credentials())->assertRedirect(route('company.login.verify'));
        $this->assertGuest('company');

        // 確認コードは、担当者のメールアドレスに届く。宛名には企業名も入る
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString('株式会社acme 山田 太郎', $this->lastMail()->getTextBody());
        $this->get('/company/login/verify')->assertOk();

        // 2段階目
        $this->post('/company/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage'));
        $this->assertAuthenticatedAs($user, 'company');
        $this->get('/company/mypage')->assertOk()->assertSee('株式会社acme')->assertSee('yamada');

        // 個人会員としては、ログインしていない
        $this->assertGuest('web');

        // 操作ログには、担当者の操作として残る
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::Login)
            ->where('operator_type', 'company_user')->where('operator_id', $user->id)->count());
    }

    public function test_user_id_is_unique_only_inside_a_company(): void
    {
        // 別の企業に、同じ担当者IDの人がいる。企業IDで、どちらの人かが決まる
        $acme = $this->user($this->company('acme'), 'yamada', 'acme@example.com');
        $other = $this->user($this->company('other'), 'yamada', 'other@example.com');

        $this->post('/company/login', $this->credentials(['company_code' => 'other']));
        $this->assertSame(['other@example.com'], $this->recipientsOf($this->lastMail()));

        $this->post('/company/login/verify', ['code' => $this->lastVerificationCode()]);
        $this->assertAuthenticatedAs($other, 'company');
        $this->assertFalse($acme->is(auth('company')->user()));
    }

    public function test_wrong_input_is_rejected_without_telling_which(): void
    {
        $this->user($this->company());

        // 企業ID・担当者ID・パスワードのどれが違っても、同じ文言で断る
        foreach ([['company_code' => 'nobody'], ['login_id' => 'nobody'], ['password' => 'wrong-password']] as $wrong) {
            $response = $this->post('/company/login', $this->credentials($wrong));
            $response->assertSessionHasErrors(['company_code' => '企業ID・担当者ID・パスワードのいずれかが正しくありません。']);
        }
        $this->assertGuest('company');
        $this->assertCount(0, $this->sentMails());

        // 誰か分からない失敗として、入力された企業IDと担当者IDと一緒に残る
        $log = OperationLog::where('action', OperationLogAction::LoginFailed)->latest('id')->first();
        $this->assertNull($log->operator_id);
        $this->assertSame(['login_id' => 'acme/yamada'], $log->detail);
    }

    public function test_only_approved_company_can_log_in(): void
    {
        $this->user($this->company('pending', CompanyStatus::Pending), 'p');
        $this->user($this->company('stopped', CompanyStatus::Suspended), 's');

        // 申請中の企業。パスワードが合った人には、承認を待つよう伝える
        $this->post('/company/login', ['company_code' => 'pending', 'login_id' => 'p', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('company_code');
        $this->assertStringContainsString('承認', session('errors')->first('company_code'));

        // 止めた企業
        $this->post('/company/login', ['company_code' => 'stopped', 'login_id' => 's', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('company_code');

        $this->assertGuest('company');
        $this->assertCount(0, $this->sentMails());
    }

    public function test_suspending_the_company_stops_a_logged_in_user(): void
    {
        $company = $this->company();
        $this->user($company);
        $this->post('/company/login', $this->credentials());
        $this->post('/company/login/verify', ['code' => $this->lastVerificationCode()]);
        $this->get('/company/mypage')->assertOk();

        // ログインした後で、運営が企業を止めた。次に画面を開いたときに、ログアウトされる
        $company->update(['status' => CompanyStatus::Suspended]);
        $this->app['auth']->forgetGuards();

        $this->get('/company/mypage')->assertRedirect(route('company.login'));
        $this->app['auth']->forgetGuards();
        $this->assertGuest('company');
    }

    public function test_remembered_device_skips_the_verification_code(): void
    {
        $user = $this->user($this->company());

        $this->post('/company/login', $this->credentials());
        $response = $this->post('/company/login/verify', ['code' => $this->lastVerificationCode(), 'remember_device' => '1']);
        $cookie = $response->getCookie('company_trusted_device');
        $this->assertSame('/', $cookie->getPath());

        $this->post('/company/logout')->assertRedirect(route('company.login'));
        $this->assertGuest('company');

        $this->withCookie('company_trusted_device', $cookie->getValue())
            ->post('/company/login', $this->credentials())
            ->assertRedirect(route('company.mypage'));
        $this->assertAuthenticatedAs($user, 'company');
    }

    public function test_company_id_and_user_id_can_be_remembered(): void
    {
        $this->user($this->company());

        // チェックを付けてログインすると、企業IDと担当者IDをCookieに覚える
        $response = $this->post('/company/login', $this->credentials(['remember_ids' => '1']));
        $cookie = $response->getCookie('company_login_ids');
        $this->assertNotNull($cookie);

        // 次にログイン画面を開くと、入力済みで出る。パスワードは覚えない
        $page = $this->withCookie('company_login_ids', $cookie->getValue())->get('/company/login')->assertOk();
        $page->assertSee('value="acme"', false)->assertSee('value="yamada"', false);
        $page->assertDontSee(self::PASSWORD);

        // チェックを外してログインすると、覚えた値を消す
        $response = $this->post('/company/login', $this->credentials());
        $this->assertLessThan(now()->timestamp, $response->getCookie('company_login_ids')->getExpiresTime());
    }

    public function test_login_page_of_another_side_is_not_mixed(): void
    {
        $this->user($this->company());
        Member::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make(self::PASSWORD)]);

        // 企業会員の画面を開こうとすると、企業会員のログイン画面へ回される
        $this->get('/company/mypage')->assertRedirect(route('company.login'));

        // 個人会員としてログインしても、企業会員の画面へは飛ばず、企業会員としてはログインしていない
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])->assertRedirect(route('mypage'));
        $this->assertGuest('company');

        // 企業の担当者としてログインすると、開こうとしていた画面へ戻る。個人会員のログインも残る
        $this->post('/company/login', $this->credentials());
        $this->post('/company/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage'));
        $this->app['auth']->forgetGuards();
        $this->get('/mypage')->assertOk();

        // 企業会員をログアウトしても、個人会員はログインしたまま
        $this->post('/company/logout');
        $this->app['auth']->forgetGuards();
        $this->get('/mypage')->assertOk();
        $this->get('/company/mypage')->assertRedirect(route('company.login'));
    }

    public function test_old_password_of_a_migrated_user_is_replaced(): void
    {
        config(['members.company.legacy_passwords' => [Md5Password::class]]);
        $user = $this->user($this->company());
        $user->forceFill(['password' => null, 'legacy_password' => LegacyPasswordUserProvider::wrap(md5('old-system-password'))])->save();

        $this->post('/company/login', $this->credentials(['password' => 'old-system-password']))
            ->assertRedirect(route('company.login.verify'));

        $user->refresh();
        $this->assertTrue(Hash::check('old-system-password', $user->password));
        $this->assertNull($user->legacy_password);
    }

    public function test_new_company_gets_its_id_as_the_company_id(): void
    {
        // 企業IDを書かずに作ると、idと同じ番号が企業IDになる
        $company = Company::create(['name' => '新規株式会社', 'tel' => '03-0000-0000', 'status' => CompanyStatus::Pending]);

        $this->assertSame((string) $company->id, $company->fresh()->code);
    }
}
