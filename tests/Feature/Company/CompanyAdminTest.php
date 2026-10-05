<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 管理画面の企業会員の管理。一覧・詳細・編集と、申請の承認・却下、利用の停止・再開。
 * 承認すると担当者がログインできるようになり、却下すると企業と担当者の行を消す。
 */
class CompanyAdminTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    private function staff(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make(self::PASSWORD),
            'acl' => StaffAcl::Staff,
        ]);
    }

    private function company(CompanyStatus $status, string $code = 'acme'): Company
    {
        return Company::create(['code' => $code, 'name' => '株式会社'.$code, 'tel' => '03-1234-5678', 'status' => $status]);
    }

    private function user(Company $company): CompanyUser
    {
        return CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => 'yamada',
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'password' => Hash::make(self::PASSWORD),
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
            'staff_memo' => '電話で確認済み',
        ];
    }

    public function test_list_can_be_narrowed_by_status(): void
    {
        $this->company(CompanyStatus::Pending, 'pending-co');
        $this->company(CompanyStatus::Approved, 'approved-co');
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->get('/admin/companies')->assertOk()
            ->assertSee('株式会社pending-co')->assertSee('株式会社approved-co');

        // 状態で絞ると、検索条件がセッションに残り、一覧に効く
        $this->actingAs($staff, 'admin')->post('/admin/companies', ['status' => 'pending'])->assertRedirect();
        $this->actingAs($staff, 'admin')->get('/admin/companies?page=1')->assertOk()
            ->assertSee('株式会社pending-co')->assertDontSee('株式会社approved-co');
    }

    public function test_detail_shows_users_and_is_recorded(): void
    {
        $company = $this->company(CompanyStatus::Approved);
        $this->user($company);
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->get("/admin/companies/{$company->id}")->assertOk()
            ->assertSee('株式会社acme')->assertSee('yamada@example.com');

        // 担当者の個人情報を出す画面なので、開いたことを操作ログに残す
        $log = OperationLog::where('action', OperationLogAction::View)->sole();
        $this->assertSame(['staff', $staff->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_company_info_is_updated_through_the_confirm_screen(): void
    {
        $company = $this->company(CompanyStatus::Approved);
        $staff = $this->staff();
        $input = $this->companyInput(['name' => '株式会社新社名']);

        $this->actingAs($staff, 'admin')->get("/admin/companies/{$company->id}/edit")->assertOk();
        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$company->id}/confirm", $input)->assertOk()->assertSee('株式会社新社名');

        // 企業IDと状態を一緒に送っても、保存しない
        $this->actingAs($staff, 'admin')
            ->patch("/admin/companies/{$company->id}/update", $input + ['code' => 'other', 'status' => 'suspended'])
            ->assertRedirect(route('admin.companies.index', ['back']));

        $company->refresh();
        $this->assertSame(['株式会社新社名', '電話で確認済み', $staff->id], [$company->name, $company->staff_memo, $company->staff_id]);
        $this->assertSame(['acme', CompanyStatus::Approved], [$company->code, $company->status]);

        // 企業の情報を変えても、担当者へのメールは送らない
        $this->assertCount(0, $this->sentMails());
    }

    public function test_approval_lets_the_user_log_in_and_tells_the_company_id(): void
    {
        $company = $this->company(CompanyStatus::Pending);
        $this->user($company);
        $staff = $this->staff();

        // 申請中の企業の詳細には、承認と却下のボタンが出る
        $this->actingAs($staff, 'admin')->get("/admin/companies/{$company->id}")->assertOk()
            ->assertSee('承認する')->assertSee('却下する');

        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$company->id}/approve")
            ->assertRedirect(route('admin.companies.show', $company));

        $this->assertSame(CompanyStatus::Approved, $company->fresh()->status);

        // 承認のお知らせは、担当者に届く。企業IDと担当者IDを載せる
        $mail = $this->lastMail();
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($mail));
        $this->assertSame('企業会員登録の承認のお知らせ', $mail->getSubject());
        $this->assertStringContainsString("■企業ID\nacme", str_replace("\r\n", "\n", $mail->getTextBody()));
        $this->assertStringContainsString('yamada', $mail->getTextBody());
        $this->assertStringContainsString(route('company.login'), $mail->getTextBody());

        // 操作ログには、状態が変わったことと、変わった後の状態の名前が残る
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame([['status'], ['status' => '承認済み']], [$log->changed_fields, $log->detail]);

        // 担当者が、ログインの1段階目を通れるようになる
        $this->post('/company/login', ['company_code' => 'acme', 'login_id' => 'yamada', 'password' => self::PASSWORD])
            ->assertRedirect(route('company.login.verify'));
    }

    public function test_rejection_sends_the_reason_and_deletes_the_rows(): void
    {
        $company = $this->company(CompanyStatus::Pending);
        $this->user($company);
        $staff = $this->staff();

        // 理由が無ければ、却下しない
        $this->actingAs($staff, 'admin')->from("/admin/companies/{$company->id}")
            ->delete("/admin/companies/{$company->id}/reject", ['reason' => ''])
            ->assertRedirect("/admin/companies/{$company->id}")
            ->assertSessionHasErrorsIn('reject', ['reason']);
        $this->assertSame(1, Company::count());

        $this->actingAs($staff, 'admin')
            ->delete("/admin/companies/{$company->id}/reject", ['reason' => '法人番号を確認できませんでした。'])
            ->assertRedirect(route('admin.companies.index', ['back']));

        // 企業と担当者の行を消す
        $this->assertSame(0, Company::count());
        $this->assertSame(0, CompanyUser::count());

        // 理由は、担当者へのメールに載せる
        $mail = $this->lastMail();
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($mail));
        $this->assertStringContainsString('株式会社acme 山田 太郎', $mail->getTextBody());
        $this->assertStringContainsString('法人番号を確認できませんでした。', $mail->getTextBody());

        $log = OperationLog::where('action', OperationLogAction::Delete)->sole();
        $this->assertSame(['staff', $staff->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_suspended_company_is_logged_out_and_can_be_resumed(): void
    {
        $company = $this->company(CompanyStatus::Approved);
        $user = $this->user($company);
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$company->id}/suspend")
            ->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(CompanyStatus::Suspended, $company->fresh()->status);

        // ログイン中の担当者も、次の操作から使えなくなる。お知らせのメールは送らない
        $this->actingAs($user->fresh(), 'company')->get('/company/mypage')->assertRedirect(route('company.login'));
        $this->assertCount(0, $this->sentMails());

        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$company->id}/resume")
            ->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(CompanyStatus::Approved, $company->fresh()->status);
    }

    public function test_status_buttons_work_only_from_the_expected_status(): void
    {
        $approved = $this->company(CompanyStatus::Approved);
        $this->user($approved);
        $staff = $this->staff();

        // 承認済みの企業は、承認も却下もできない。行も消えない
        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$approved->id}/approve")->assertSessionHas('error');
        $this->actingAs($staff, 'admin')->delete("/admin/companies/{$approved->id}/reject", ['reason' => '理由'])->assertSessionHas('error');
        $this->actingAs($staff, 'admin')->patch("/admin/companies/{$approved->id}/resume")->assertSessionHas('error');

        $this->assertSame(CompanyStatus::Approved, $approved->fresh()->status);
        $this->assertSame(1, CompanyUser::count());
        $this->assertCount(0, $this->sentMails());
    }

    public function test_staff_registers_a_company_and_the_first_user_is_invited(): void
    {
        $staff = $this->staff();
        $input = $this->companyInput(['name' => '株式会社運営登録', 'invite_email' => 'first@example.com']);

        $this->actingAs($staff, 'admin')->get('/admin/companies/create')->assertOk();
        $this->actingAs($staff, 'admin')->post('/admin/companies/confirm', $input)->assertOk()->assertSee('first@example.com');

        // 招待の宛先が無ければ、登録しない
        $this->actingAs($staff, 'admin')->post('/admin/companies/store', ['invite_email' => ''] + $input)
            ->assertSessionHasErrors('invite_email');
        $this->assertSame(0, Company::count());

        $this->actingAs($staff, 'admin')->post('/admin/companies/store', $input);

        // 運営が登録した企業は、初めから承認済み。企業IDは、idと同じ番号
        $company = Company::sole();
        $this->assertSame(['株式会社運営登録', CompanyStatus::Approved, (string) $company->id, $staff->id], [$company->name, $company->status, $company->code, $company->staff_id]);

        // 担当者は作らず、最初の担当者へ招待のメールを送る。パスワードは本人が決める
        $this->assertSame(0, CompanyUser::count());
        $this->assertSame(['first@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString('/company/invitation/', $this->lastMail()->getTextBody());
        $this->assertSame('first@example.com', CompanyInvitation::sole()->email);

        // 詳細の招待中の一覧に出る
        $this->actingAs($staff, 'admin')->get("/admin/companies/{$company->id}")->assertOk()->assertSee('first@example.com');
    }

    public function test_staff_can_invite_resend_and_cancel(): void
    {
        $company = $this->company(CompanyStatus::Approved);
        $other = $this->company(CompanyStatus::Approved, 'other');
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->post("/admin/companies/{$company->id}/invitations", ['email' => 'not-an-address'])
            ->assertSessionHasErrorsIn('invitation', ['email']);

        $this->actingAs($staff, 'admin')->post("/admin/companies/{$company->id}/invitations", ['email' => 'new@example.com'])
            ->assertRedirect(route('admin.companies.show', $company));
        $invitation = CompanyInvitation::sole();
        $this->assertSame([$company->id, 'new@example.com'], [$invitation->company_id, $invitation->email]);

        // 操作ログには、送ったスタッフと、企業が残る
        $log = OperationLog::where('action', OperationLogAction::InvitationSend)->sole();
        $this->assertSame(['staff', $staff->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);

        // URLの企業と、招待の企業が違えば、扱えない
        $this->actingAs($staff, 'admin')->delete("/admin/companies/{$other->id}/invitations/{$invitation->id}")->assertNotFound();

        $this->actingAs($staff, 'admin')->post("/admin/companies/{$company->id}/invitations/{$invitation->id}/resend")
            ->assertRedirect(route('admin.companies.show', $company));
        $this->assertCount(2, $this->sentMails());

        $this->actingAs($staff, 'admin')->delete("/admin/companies/{$company->id}/invitations/{$invitation->id}")
            ->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(0, CompanyInvitation::count());
    }

    public function test_pending_company_cannot_be_invited_from_the_admin(): void
    {
        $company = $this->company(CompanyStatus::Pending);

        $this->actingAs($this->staff(), 'admin')->post("/admin/companies/{$company->id}/invitations", ['email' => 'new@example.com'])
            ->assertSessionHas('error');

        $this->assertSame(0, CompanyInvitation::count());
        $this->assertCount(0, $this->sentMails());
    }

    public function test_csv_has_the_status_name(): void
    {
        $this->company(CompanyStatus::Pending);
        $staff = $this->staff();

        $csv = $this->actingAs($staff, 'admin')->get('/admin/companies/csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('株式会社acme', $csv);
        $this->assertStringContainsString('申請中', $csv);
    }

    public function test_screens_need_a_staff_login(): void
    {
        $company = $this->company(CompanyStatus::Pending);

        $this->get('/admin/companies')->assertRedirect(route('admin.login'));
        $this->get("/admin/companies/{$company->id}")->assertRedirect(route('admin.login'));
        $this->patch("/admin/companies/{$company->id}/approve")->assertRedirect(route('admin.login'));

        $this->assertSame(CompanyStatus::Pending, $company->fresh()->status);
    }
}
