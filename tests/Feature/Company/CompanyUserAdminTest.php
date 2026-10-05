<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use App\Models\Staff;
use App\Support\TrustedDeviceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 管理画面の、企業会員の担当者の確認・編集・削除。担当者を足す画面は無い。
 * パスワードを変えたときだけ、担当者へメールで知らせる。
 */
class CompanyUserAdminTest extends TestCase
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

    private function company(string $code = 'acme'): Company
    {
        return Company::create(['code' => $code, 'name' => '株式会社'.$code, 'tel' => '03-1234-5678', 'status' => CompanyStatus::Approved]);
    }

    private function user(Company $company, string $loginId = 'yamada'): CompanyUser
    {
        return CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => $loginId,
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function url(CompanyUser $user, string $path = ''): string
    {
        return "/admin/companies/{$user->company_id}/users/{$user->id}{$path}";
    }

    public function test_detail_is_recorded(): void
    {
        $user = $this->user($this->company());
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->get($this->url($user))->assertOk()
            ->assertSee('yamada@example.com')->assertSee('この1人だけです');

        $log = OperationLog::where('action', OperationLogAction::View)->sole();
        $this->assertSame(['staff', $staff->id, 'company_user', $user->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_name_and_address_are_updated_without_mail(): void
    {
        $user = $this->user($this->company());
        $staff = $this->staff();
        $input = ['name' => '山田 次郎', 'email' => 'new@example.com', 'password' => '', 'password_confirmation' => ''];

        $this->actingAs($staff, 'admin')->get($this->url($user, '/edit'))->assertOk();
        $this->actingAs($staff, 'admin')->patch($this->url($user, '/confirm'), $input)->assertOk()->assertSee('山田 次郎');

        // 担当者IDを一緒に送っても、保存しない
        $this->actingAs($staff, 'admin')->patch($this->url($user, '/update'), $input + ['login_id' => 'other'])
            ->assertRedirect(route('admin.companies.show', $user->company_id));

        $user->refresh();
        $this->assertSame(['山田 次郎', 'new@example.com', 'yamada'], [$user->name, $user->email, $user->login_id]);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        // 管理画面から氏名やメールアドレスを変えても、担当者へは知らせない
        $this->assertCount(0, $this->sentMails());
    }

    public function test_password_change_is_notified_and_resets_trusted_devices(): void
    {
        $user = $this->user($this->company());
        $staff = $this->staff();
        TrustedDeviceManager::forMember($user)->remember($user);

        $this->actingAs($staff, 'admin')->patch($this->url($user, '/update'), [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'password' => 'changed-password',
            'password_confirmation' => 'changed-password',
        ])->assertRedirect(route('admin.companies.show', $user->company_id));

        $user->refresh();
        $this->assertTrue(Hash::check('changed-password', $user->password));
        $this->assertSame(0, $user->trustedDevices()->count());

        // お知らせには、運営のスタッフが変えたことを書く
        $mail = $this->lastMail();
        $this->assertSame(['yamada@example.com'], $this->recipientsOf($mail));
        $this->assertSame('パスワード変更のお知らせ', $mail->getSubject());
        $this->assertStringContainsString('運営スタッフ', $mail->getTextBody());

        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['staff', $staff->id, 'company_user', $user->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_last_user_can_be_deleted(): void
    {
        $company = $this->company();
        $user = $this->user($company);
        $staff = $this->staff();
        TrustedDeviceManager::forMember($user)->remember($user);

        $this->actingAs($staff, 'admin')->delete($this->url($user, '/delete'))
            ->assertRedirect(route('admin.companies.show', $company));

        // 担当者と、その信頼済みの端末を消す。企業は残る
        $this->assertSame(0, CompanyUser::count());
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->assertSame(1, Company::count());
        $this->assertCount(0, $this->sentMails());

        $log = OperationLog::where('action', OperationLogAction::Delete)->sole();
        $this->assertSame(['staff', $staff->id, 'company_user', $user->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);

        // 担当者がいなくなった企業の詳細も開ける
        $this->actingAs($staff, 'admin')->get("/admin/companies/{$company->id}")->assertOk()->assertSee('担当者がいません。');
    }

    public function test_user_of_another_company_is_not_found(): void
    {
        $user = $this->user($this->company('acme'));
        $other = $this->company('other');
        $staff = $this->staff();

        // URLの企業と、担当者の企業が違えば、開けない
        $this->actingAs($staff, 'admin')->get("/admin/companies/{$other->id}/users/{$user->id}")->assertNotFound();
        $this->actingAs($staff, 'admin')->delete("/admin/companies/{$other->id}/users/{$user->id}/delete")->assertNotFound();

        $this->assertSame(1, CompanyUser::count());
    }

    public function test_screens_need_a_staff_login(): void
    {
        $user = $this->user($this->company());

        $this->get($this->url($user))->assertRedirect(route('admin.login'));
        $this->delete($this->url($user, '/delete'))->assertRedirect(route('admin.login'));

        $this->assertSame(1, CompanyUser::count());
    }
}
