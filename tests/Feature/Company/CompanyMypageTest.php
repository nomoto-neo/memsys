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
 * 企業会員のマイページ。企業の情報の変更、担当者自身の情報の変更、パスキーの管理の画面。
 * 担当者に権限の区別は無く、どの担当者も企業の情報を変えられる。
 */
class CompanyMypageTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private function company(string $code = 'acme'): Company
    {
        return Company::create([
            'code' => $code,
            'name' => '株式会社'.$code,
            'tel' => '03-1234-5678',
            'status' => CompanyStatus::Approved,
        ]);
    }

    private function user(Company $company, string $loginId = 'yamada', string $email = 'old@example.com'): CompanyUser
    {
        return CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => $loginId,
            'name' => '山田 太郎',
            'email' => $email,
            'password' => Hash::make('correct-password'),
        ]);
    }

    private function companyInput(array $override = []): array
    {
        return $override + [
            'name' => '株式会社acme',
            'kana' => 'カブシキガイシャアクメ',
            'representative' => '代表 一郎',
            'zip' => '100-0001',
            'prefecture' => 13,
            'address' => '千代田区千代田1-1',
            'tel' => '03-1234-5678',
            'url' => 'https://example.com/',
        ];
    }

    public function test_any_user_can_change_the_company_info(): void
    {
        $company = $this->company();
        $user = $this->user($company);

        $this->actingAs($user, 'company')->get('/company/mypage/edit')->assertOk()->assertSee('株式会社acme');

        $this->actingAs($user, 'company')
            ->patch('/company/mypage/update', $this->companyInput(['name' => '株式会社新社名', 'tel' => '03-9999-0000']))
            ->assertRedirect(route('company.mypage'));

        $company->refresh();
        $this->assertSame(['株式会社新社名', '03-9999-0000', '代表 一郎'], [$company->name, $company->tel, $company->representative]);

        // 企業の情報の変更では、メールは送らない。誰が変えたかは、操作ログに残る
        $this->assertCount(0, $this->sentMails());
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame(['company_user', $user->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
        $this->assertContains('name', $log->changed_fields);
        $this->assertContains('tel', $log->changed_fields);
    }

    public function test_company_id_and_status_cannot_be_changed_from_the_company_side(): void
    {
        $company = $this->company();
        $user = $this->user($company);

        // 企業IDと状態を一緒に送っても、保存しない
        $this->actingAs($user, 'company')
            ->patch('/company/mypage/update', $this->companyInput(['code' => 'other', 'status' => CompanyStatus::Suspended->value]))
            ->assertRedirect(route('company.mypage'));

        $company->refresh();
        $this->assertSame('acme', $company->code);
        $this->assertSame(CompanyStatus::Approved, $company->status);
    }

    public function test_company_info_is_validated(): void
    {
        $user = $this->user($this->company());

        $this->actingAs($user, 'company')
            ->from('/company/mypage/edit')
            ->patch('/company/mypage/update', $this->companyInput(['name' => '', 'tel' => 'abc', 'zip' => '1234', 'url' => 'not-a-url']))
            ->assertRedirect('/company/mypage/edit')
            ->assertSessionHasErrors(['name', 'tel', 'zip', 'url']);
    }

    public function test_only_own_company_is_changed(): void
    {
        $acme = $this->company('acme');
        $other = $this->company('other');
        $user = $this->user($acme);

        $this->actingAs($user, 'company')->patch('/company/mypage/update', $this->companyInput(['name' => '株式会社新社名']));

        $this->assertSame('株式会社other', $other->fresh()->name);
    }

    public function test_own_profile_change_is_notified_to_old_and_new_address(): void
    {
        $user = $this->user($this->company());

        $this->actingAs($user, 'company')->get('/company/mypage/profile')->assertOk()->assertSee('old@example.com');

        // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む。コードは新しいアドレスに届く
        $this->actingAs($user, 'company')
            ->patch('/company/mypage/profile', ['name' => '山田 次郎', 'email' => 'new@example.com'])
            ->assertRedirect(route('company.mypage.profile.email'));

        $this->assertSame(['山田 太郎', 'old@example.com'], [$user->fresh()->name, $user->fresh()->email]);
        $this->assertSame(['new@example.com'], $this->recipientsOf($this->lastMail()));
        $this->actingAs($user, 'company')->get('/company/mypage/profile/email/verify')->assertOk()->assertSee('new@example.com');

        // コードが合えば、メールアドレスも氏名もまとめて保存される
        $this->actingAs($user, 'company')
            ->post('/company/mypage/profile/email/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage'));

        $user->refresh();
        $this->assertSame(['山田 次郎', 'new@example.com', 'yamada'], [$user->name, $user->email, $user->login_id]);

        // お知らせは、変わる前と後の両方のアドレスに届く。本文に、変わる前のアドレスは載せない
        $notices = $this->sentMails()->slice(1);
        $recipients = $notices->map(fn ($mail) => $this->recipientsOf($mail)[0])->sort()->values()->all();
        $this->assertSame(['new@example.com', 'old@example.com'], $recipients);
        $this->assertSame(['担当者情報変更のお知らせ', '担当者情報変更のお知らせ'], array_slice($this->sentSubjects(), 1));
        $this->assertStringNotContainsString('old@example.com', $this->lastMail()->getTextBody());
        $this->assertStringContainsString('株式会社acme 山田 次郎', $this->lastMail()->getTextBody());
        $this->assertStringContainsString(route('company.password.forgot'), $this->lastMail()->getTextBody());

        // 操作ログには、変わった列の名前だけが残る
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame(['company_user', $user->id, 'company_user', $user->id, ['name', 'email']], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id, $log->changed_fields]);
    }

    public function test_same_address_as_another_user_is_allowed(): void
    {
        $company = $this->company();
        $this->user($company, 'sato', 'info@example.com');
        $user = $this->user($company, 'yamada', 'old@example.com');

        // 企業の代表アドレスを、複数の担当者が使ってもよい
        $this->actingAs($user, 'company')
            ->patch('/company/mypage/profile', ['name' => '山田 太郎', 'email' => 'info@example.com'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user, 'company')
            ->post('/company/mypage/profile/email/verify', ['code' => $this->lastVerificationCode()])
            ->assertSessionHasNoErrors();

        $this->assertSame('info@example.com', $user->fresh()->email);
    }

    public function test_own_email_is_not_changed_without_the_right_code(): void
    {
        $user = $this->user($this->company());

        $this->actingAs($user, 'company')
            ->patch('/company/mypage/profile', ['name' => '山田 太郎', 'email' => 'new@example.com'])
            ->assertRedirect(route('company.mypage.profile.email'));

        $wrongCode = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->actingAs($user, 'company')
            ->post('/company/mypage/profile/email/verify', ['code' => $wrongCode])
            ->assertRedirect(route('company.mypage.profile.email'))
            ->assertSessionHasErrors('code');

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_saving_without_change_sends_nothing(): void
    {
        $user = $this->user($this->company());

        $this->actingAs($user, 'company')
            ->patch('/company/mypage/profile', ['name' => '山田 太郎', 'email' => 'old@example.com'])
            ->assertRedirect(route('company.mypage'));

        $this->assertCount(0, $this->sentMails());
    }

    public function test_passkey_screen_sends_a_code_for_identity_check(): void
    {
        $user = $this->user($this->company());

        // パスキーの管理の画面は開ける。登録の前の本人確認は、メールの確認コード
        $this->actingAs($user, 'company')->get('/company/mypage/passkeys')->assertOk();

        $this->actingAs($user, 'company')->post('/company/mypage/passkeys/code')->assertRedirect(route('company.mypage.passkeys'));
        $this->assertSame(['old@example.com'], $this->recipientsOf($this->lastMail()));

        // コードが合えば、登録に進める状態になる
        $this->actingAs($user, 'company')->post('/company/mypage/passkeys/confirm', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.mypage.passkeys'))
            ->assertSessionHasNoErrors();
    }

    public function test_screens_need_a_company_login(): void
    {
        foreach (['/company/mypage/edit', '/company/mypage/profile', '/company/mypage/password', '/company/mypage/passkeys'] as $url) {
            $this->get($url)->assertRedirect(route('company.login'));
        }
    }
}
