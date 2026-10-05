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
 * 企業会員の登録。担当者のメールアドレスに確認コードを送り、コードが合ってから、
 * 企業と最初の担当者を「申請中」で作る。運営が承認するまでは、ログインできない。
 */
class CompanyRegistrationTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const INPUT = [
        'name' => '株式会社新規',
        'kana' => 'カブシキガイシャシンキ',
        'representative' => '代表 一郎',
        'zip' => '100-0001',
        'prefecture' => 13,
        'address' => '千代田区千代田1-1',
        'tel' => '03-1234-5678',
        'url' => 'https://example.com/',
        'login_id' => 'yamada',
        'user_name' => '山田 太郎',
        'email' => 'yamada@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ];

    public function test_company_and_first_user_are_created_as_pending_after_the_code_is_verified(): void
    {
        $this->get('/company/regist')->assertOk();

        // 確認画面までは、何も作らず、メールも送らない
        $this->post('/company/regist/confirm', self::INPUT)->assertOk()->assertSee('株式会社新規');
        $this->assertCount(0, $this->sentMails());

        // 確認コードを、担当者のメールアドレスに送る。企業と担当者はまだ作らない
        $this->post('/company/regist/send', self::INPUT)->assertRedirect(route('company.regist.verify'));
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString('株式会社新規 山田 太郎', $this->lastMail()->getTextBody());
        $this->assertSame(0, Company::count());

        // コードが合ったら、企業と担当者を申請中で作る。ログインはさせず、承認待ちの案内へ
        $this->post('/company/regist/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.regist.thanks'));
        $this->get('/company/regist/thanks')->assertOk();

        $company = Company::sole();
        $user = CompanyUser::sole();
        $this->assertSame(['株式会社新規', '03-1234-5678', CompanyStatus::Pending], [$company->name, $company->tel, $company->status]);
        // 企業IDは、idと同じ番号
        $this->assertSame((string) $company->id, $company->code);
        $this->assertSame([$company->id, 'yamada', '山田 太郎', 'yamada@example.com'], [$user->company_id, $user->login_id, $user->name, $user->email]);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertGuest('company');

        // 運営に、申請があったことを知らせる
        $this->assertSame(['staff@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertSame('【企業会員の申請】株式会社新規', $this->lastMail()->getSubject());
        $this->assertStringContainsString(route('admin.companies.show', $company), $this->lastMail()->getTextBody());

        // 操作ログには、作った担当者を操作した人にして残す
        $log = OperationLog::where('action', OperationLogAction::Create)->sole();
        $this->assertSame(['company_user', $user->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_pending_company_cannot_log_in(): void
    {
        $this->post('/company/regist/send', self::INPUT);
        $this->post('/company/regist/verify', ['code' => $this->lastVerificationCode()]);

        $this->post('/company/login', ['company_code' => Company::sole()->code, 'login_id' => 'yamada', 'password' => 'new-password'])
            ->assertSessionHasErrors('company_code');

        $this->assertGuest('company');
    }

    public function test_wrong_code_does_not_create_the_company(): void
    {
        $this->post('/company/regist/send', self::INPUT);
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/company/regist/verify', ['code' => $wrong])
            ->assertRedirect(route('company.regist.verify'))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, Company::count());
        $this->assertSame(0, CompanyUser::count());
    }

    public function test_input_is_validated(): void
    {
        // 担当者IDには、企業IDとの区切りに使う「/」を使えない
        $this->post('/company/regist/send', ['login_id' => 'a/b', 'tel' => 'abc', 'name' => ''] + self::INPUT)
            ->assertRedirect(route('company.regist.create'))
            ->assertSessionHasErrors(['login_id', 'tel', 'name']);

        $this->assertCount(0, $this->sentMails());
    }

    public function test_same_login_id_and_address_as_another_company_are_allowed(): void
    {
        // 担当者IDは企業の中でだけ重ならなければよく、メールアドレスは重なっていてよい
        $other = Company::create(['code' => 'other', 'name' => '株式会社other', 'tel' => '03-0000-0000', 'status' => CompanyStatus::Approved]);
        CompanyUser::create(['company_id' => $other->id, 'login_id' => 'yamada', 'name' => '山田 花子', 'email' => 'yamada@example.com', 'password' => Hash::make('x')]);

        $this->post('/company/regist/send', self::INPUT)->assertRedirect(route('company.regist.verify'));
        $this->post('/company/regist/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('company.regist.thanks'));

        $this->assertSame(2, CompanyUser::where('login_id', 'yamada')->count());
    }

    public function test_verification_needs_the_input_step(): void
    {
        $this->get('/company/regist/verify')->assertRedirect(route('company.regist.create'));
        $this->post('/company/regist/verify', ['code' => '123456'])->assertRedirect(route('company.regist.create'));
    }
}
