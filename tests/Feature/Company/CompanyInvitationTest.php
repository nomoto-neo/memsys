<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use App\Support\CompanyInvitationManager;
use App\Support\TemporaryDataCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 企業会員の担当者の管理と招待。担当者は、招待のメールのリンクから本人が登録して足す。
 * どの担当者も、招待と、ほかの担当者の編集・削除ができる。自分自身は削除できない。
 */
class CompanyInvitationTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

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
            'password' => Hash::make('correct-password'),
        ]);
    }

    /** 最後に送ったメールの本文にある、招待の登録の画面のURL */
    private function lastInvitationUrl(): string
    {
        $found = preg_match('#https?://\S+/company/invitation/\S+#', (string) $this->lastMail()->getTextBody(), $matches);

        $this->assertSame(1, $found, '招待のリンクが本文にありません。');

        return $matches[0];
    }

    private function registerInput(array $override = []): array
    {
        return $override + [
            'login_id' => 'sato',
            'name' => '佐藤 花子',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];
    }

    public function test_invited_person_registers_from_the_link_and_is_logged_in(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->get('/company/mypage/users')->assertOk()->assertSee('山田 太郎');
        $this->actingAs($inviter, 'company')->get('/company/mypage/users/invite')->assertOk();

        // 招待のメールは、入力したアドレスに届く。DBには、リンクの値のハッシュ値だけを持つ
        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com'])
            ->assertRedirect(route('company.users.index'));

        $mail = $this->lastMail();
        $this->assertSame(['sato@example.com'], $this->recipientsOf($mail));
        $this->assertSame('【株式会社acme】企業会員の担当者へのご招待', $mail->getSubject());
        $url = $this->lastInvitationUrl();
        $invitation = CompanyInvitation::sole();
        $this->assertStringNotContainsString($invitation->token_hash, $url);

        // 招待中の一覧に出る。誰が招待したかは、操作ログに残る
        $this->actingAs($inviter, 'company')->get('/company/mypage/users')->assertSee('sato@example.com');
        $log = OperationLog::where('action', OperationLogAction::InvitationSend)->sole();
        $this->assertSame(['company_user', $inviter->id, 'company', $company->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);

        // 招待された人が、リンクから担当者ID・氏名・パスワードを決める
        $this->app['auth']->guard('company')->logout();
        $this->get($url)->assertOk()->assertSee('sato@example.com')->assertSee('株式会社acme');
        $this->post($url, $this->registerInput())->assertRedirect(route('company.mypage'));

        $user = CompanyUser::where('login_id', 'sato')->sole();
        $this->assertSame([$company->id, '佐藤 花子', 'sato@example.com'], [$user->company_id, $user->name, $user->email]);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertAuthenticatedAs($user, 'company');

        // 招待は1回使ったら消える。同じリンクは、もう使えない
        $this->assertSame(0, CompanyInvitation::count());
        $this->app['auth']->guard('company')->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_login_id_must_be_unique_within_the_company(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);
        // 別の企業の同じ担当者IDは、重なりに数えない
        $this->user($this->company('other'), 'sato');

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();
        $this->app['auth']->guard('company')->logout();

        $this->post($url, $this->registerInput(['login_id' => 'yamada']))->assertSessionHasErrors('login_id');
        $this->post($url, $this->registerInput(['login_id' => 'a/b']))->assertSessionHasErrors('login_id');
        $this->assertSame(1, CompanyUser::where('company_id', $company->id)->count());

        $this->post($url, $this->registerInput(['login_id' => 'sato']))->assertRedirect(route('company.mypage'));
        $this->assertSame(2, CompanyUser::where('company_id', $company->id)->count());
    }

    public function test_resend_replaces_the_link_and_cancel_disables_it(): void
    {
        $inviter = $this->user($this->company());

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $first = $this->lastInvitationUrl();
        $invitation = CompanyInvitation::sole();

        // 送り直すと、リンクが新しくなり、前のリンクは使えなくなる
        $this->actingAs($inviter, 'company')->post("/company/mypage/users/invitations/{$invitation->id}/resend")
            ->assertRedirect(route('company.users.index'));
        $second = $this->lastInvitationUrl();
        $this->assertNotSame($first, $second);
        $this->assertSame(1, CompanyInvitation::count());

        // 取り消すと、送り直した方のリンクも使えなくなる
        $this->actingAs($inviter, 'company')->delete("/company/mypage/users/invitations/{$invitation->id}")
            ->assertRedirect(route('company.users.index'));
        $this->assertSame(0, CompanyInvitation::count());
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::InvitationCancel)->count());

        $this->app['auth']->guard('company')->logout();
        $this->get($first)->assertNotFound();
        $this->get($second)->assertNotFound();
    }

    public function test_same_address_is_invited_only_once(): void
    {
        $inviter = $this->user($this->company());

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);

        // 同じ宛先にもう一度招待すると、前の招待を置き換える
        $this->assertSame(1, CompanyInvitation::count());
        $this->assertCount(2, $this->sentMails());
    }

    public function test_expired_link_is_rejected_and_cleaned_up(): void
    {
        $inviter = $this->user($this->company());

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();
        $this->app['auth']->guard('company')->logout();

        // 期限の直前までは使える
        $this->travel(CompanyInvitationManager::VALID_DAYS)->days();
        $this->travel(-1)->minutes();
        $this->get($url)->assertOk();

        // 期限を過ぎると使えず、後片付けで行が消える
        $this->travel(2)->minutes();
        $this->get($url)->assertNotFound();
        $this->post($url, $this->registerInput())->assertNotFound();
        $this->assertSame(1, CompanyUser::count());

        $this->assertSame(1, TemporaryDataCleaner::expiredCompanyInvitations());
        $this->assertSame(0, CompanyInvitation::count());
    }

    public function test_link_of_a_suspended_company_is_rejected(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();
        $this->app['auth']->guard('company')->logout();

        $company->update(['status' => CompanyStatus::Suspended]);

        $this->post($url, $this->registerInput())->assertNotFound();
        $this->assertSame(1, CompanyUser::count());
    }

    public function test_other_user_is_edited_and_notified(): void
    {
        $company = $this->company();
        $me = $this->user($company);
        $other = $this->user($company, 'sato', 'sato@example.com');

        $this->actingAs($me, 'company')->get("/company/mypage/users/{$other->id}/edit")->assertOk()->assertSee('sato@example.com');

        // 担当者IDとパスワードを一緒に送っても、保存しない
        $this->actingAs($me, 'company')
            ->patch("/company/mypage/users/{$other->id}", ['name' => '佐藤 次郎', 'email' => 'new@example.com', 'login_id' => 'x', 'password' => 'hacked-password'])
            ->assertRedirect(route('company.users.index'));

        $other->refresh();
        $this->assertSame(['佐藤 次郎', 'new@example.com', 'sato'], [$other->name, $other->email, $other->login_id]);
        $this->assertTrue(Hash::check('correct-password', $other->password));

        // 変えられた担当者の、変わる前と後の両方のアドレスに知らせる
        $recipients = $this->sentMails()->map(fn ($mail) => $this->recipientsOf($mail)[0])->sort()->values()->all();
        $this->assertSame(['new@example.com', 'sato@example.com'], $recipients);

        // 操作ログには、変えた担当者と、変えられた担当者が残る
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame(['company_user', $me->id, 'company_user', $other->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_other_user_can_be_deleted_but_not_oneself(): void
    {
        $company = $this->company();
        $me = $this->user($company);
        $other = $this->user($company, 'sato', 'sato@example.com');

        // 自分自身は削除できない。自分の情報は、専用の画面で変える
        $this->actingAs($me, 'company')->delete("/company/mypage/users/{$me->id}")
            ->assertRedirect(route('company.users.index'))->assertSessionHas('error');
        $this->actingAs($me, 'company')->get("/company/mypage/users/{$me->id}/edit")->assertRedirect(route('company.mypage.profile'));
        $this->assertSame(2, CompanyUser::count());

        $this->actingAs($me, 'company')->delete("/company/mypage/users/{$other->id}")
            ->assertRedirect(route('company.users.index'))->assertSessionHas('status');
        $this->assertSame([$me->id], CompanyUser::pluck('id')->all());
    }

    public function test_users_and_invitations_of_another_company_are_not_found(): void
    {
        $me = $this->user($this->company('acme'));
        $otherCompany = $this->company('other');
        $otherUser = $this->user($otherCompany, 'sato', 'sato@example.com');
        CompanyInvitationManager::invite($otherCompany, 'guest@example.com');
        $invitation = CompanyInvitation::sole();

        $this->actingAs($me, 'company')->get('/company/mypage/users')->assertOk()
            ->assertDontSee('sato@example.com')->assertDontSee('guest@example.com');
        $this->actingAs($me, 'company')->get("/company/mypage/users/{$otherUser->id}/edit")->assertNotFound();
        $this->actingAs($me, 'company')->patch("/company/mypage/users/{$otherUser->id}", ['name' => 'x', 'email' => 'x@example.com'])->assertNotFound();
        $this->actingAs($me, 'company')->delete("/company/mypage/users/{$otherUser->id}")->assertNotFound();
        $this->actingAs($me, 'company')->post("/company/mypage/users/invitations/{$invitation->id}/resend")->assertNotFound();
        $this->actingAs($me, 'company')->delete("/company/mypage/users/invitations/{$invitation->id}")->assertNotFound();

        $this->assertSame('山田 太郎', $otherUser->fresh()->name);
        $this->assertSame(1, CompanyInvitation::count());
    }

    public function test_screens_need_a_company_login(): void
    {
        foreach (['/company/mypage/users', '/company/mypage/users/invite'] as $url) {
            $this->get($url)->assertRedirect(route('company.login'));
        }

        $this->post('/company/mypage/users/invite', ['email' => 'sato@example.com'])->assertRedirect(route('company.login'));
        $this->assertSame(0, CompanyInvitation::count());
    }
}
