<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyStatus;
use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Company;
use App\Models\TeamInvitation;
use App\Models\CompanyUser;
use App\Models\OperationLog;
use App\Models\Staff;
use App\Support\TeamInvitationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
        config(['app.site_name' => 'テストサイト']);

        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->get('/company/mypage/users')->assertOk()->assertSee('山田 太郎');
        $this->actingAs($inviter, 'company')->get('/company/mypage/users/invite')->assertOk();

        // 招待のメールは、入力したアドレスに届く。DBには、リンクの値のハッシュ値だけを持つ
        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com'])
            ->assertRedirect(route('company.users.index'));

        $mail = $this->lastMail();
        $this->assertSame(['sato@example.com'], $this->recipientsOf($mail));
        $this->assertSame('【テストサイト】企業会員の担当者へのご招待', $mail->getSubject());

        // 招待した担当者の氏名を載せる。同じ企業の知り合いからの招待なので、企業名は添えない
        $this->assertStringContainsString('山田 太郎 様から、テストサイトの企業会員の担当者として招待されました。', $mail->getTextBody());
        $this->assertStringNotContainsString('株式会社acme', $mail->getTextBody());
        $url = $this->lastInvitationUrl();
        $invitation = TeamInvitation::sole();
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
        $this->assertSame(0, TeamInvitation::count());
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
        $invitation = TeamInvitation::sole();

        // 送り直すと、リンクが新しくなり、前のリンクは使えなくなる
        $this->actingAs($inviter, 'company')->post("/company/mypage/users/invitations/{$invitation->id}/resend")
            ->assertRedirect(route('company.users.index'));
        $second = $this->lastInvitationUrl();
        $this->assertNotSame($first, $second);
        $this->assertSame(1, TeamInvitation::count());

        // 取り消すと、送り直した方のリンクも使えなくなる
        $this->actingAs($inviter, 'company')->delete("/company/mypage/users/invitations/{$invitation->id}")
            ->assertRedirect(route('company.users.index'));
        $this->assertSame(0, TeamInvitation::count());
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
        $this->assertSame(1, TeamInvitation::count());
        $this->assertCount(2, $this->sentMails());
    }

    public function test_expired_link_is_rejected_and_cleaned_up(): void
    {
        $inviter = $this->user($this->company());

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();
        $this->app['auth']->guard('company')->logout();

        // 期限の直前までは使える
        $this->travel(TeamInvitationManager::VALID_DAYS)->days();
        $this->travel(-1)->minutes();
        $this->get($url)->assertOk();

        // 期限を過ぎると使えず、後片付けで行が消える
        $this->travel(2)->minutes();
        $this->get($url)->assertNotFound();
        $this->post($url, $this->registerInput())->assertNotFound();
        $this->assertSame(1, CompanyUser::count());

        $this->assertSame(1, TeamInvitationManager::deleteExpired());
        $this->assertSame(0, TeamInvitation::count());
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
        TeamInvitationManager::invite($otherCompany, 'guest@example.com');
        $invitation = TeamInvitation::sole();

        $this->actingAs($me, 'company')->get('/company/mypage/users')->assertOk()
            ->assertDontSee('sato@example.com')->assertDontSee('guest@example.com');
        $this->actingAs($me, 'company')->get("/company/mypage/users/{$otherUser->id}/edit")->assertNotFound();
        $this->actingAs($me, 'company')->patch("/company/mypage/users/{$otherUser->id}", ['name' => 'x', 'email' => 'x@example.com'])->assertNotFound();
        $this->actingAs($me, 'company')->delete("/company/mypage/users/{$otherUser->id}")->assertNotFound();
        $this->actingAs($me, 'company')->post("/company/mypage/users/invitations/{$invitation->id}/resend")->assertNotFound();
        $this->actingAs($me, 'company')->delete("/company/mypage/users/invitations/{$invitation->id}")->assertNotFound();

        $this->assertSame('山田 太郎', $otherUser->fresh()->name);
        $this->assertSame(1, TeamInvitation::count());
    }

    public function test_screens_need_a_company_login(): void
    {
        foreach (['/company/mypage/users', '/company/mypage/users/invite'] as $url) {
            $this->get($url)->assertRedirect(route('company.login'));
        }

        $this->post('/company/mypage/users/invite', ['email' => 'sato@example.com'])->assertRedirect(route('company.login'));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_opening_a_usable_link_logs_out_the_person_left_logged_in(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();

        // 同じパソコンに、招待した人と、管理画面のスタッフのログインが残っている
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $this->actingAs($staff, 'admin');
        $this->assertAuthenticatedAs($inviter, 'company');

        // 使えないリンクでは、ログインを終わらせない
        $this->get('/company/invitation/'.str_repeat('x', 64))->assertNotFound();
        $this->assertAuthenticatedAs($inviter, 'company');

        // 使えるリンクを開くと、マイページへは移さず、企業会員のログインを終わらせて登録の画面を出す。
        // 管理画面のログインは残る
        $this->get($url)->assertOk()->assertSee('sato@example.com');
        $this->assertGuest('company');
        $this->assertAuthenticatedAs($staff, 'admin');

        // そのまま登録すると、招待された人としてログインする
        $this->post($url, $this->registerInput())->assertRedirect(route('company.mypage'));
        $this->assertSame('sato@example.com', Auth::guard('company')->user()->email);
    }

    public function test_only_the_inviter_can_resend_but_anyone_in_the_company_can_cancel(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);
        $colleague = CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => 'suzuki',
            'name' => '鈴木 次郎',
            'email' => 'suzuki@example.com',
            // 同じテストの中で2人を切り替えるので、パスワードのハッシュ値を同じにしておく。
            // 違うと、auth.sessionが「パスワードが変わった」と見て、ログアウトさせる
            'password' => $inviter->password,
        ]);

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $invitation = TeamInvitation::sole();

        // 招待した人が、行に残る
        $this->assertSame(['company_user', $inviter->id], [$invitation->inviter_type, $invitation->inviter_id]);

        // 一覧には、招待した人の氏名が出る。送り直すボタンは、招待した本人にだけ出る
        $resendUrl = route('company.users.invitations.resend', $invitation);
        $this->actingAs($inviter, 'company')->get('/company/mypage/users')
            ->assertSee('招待した人')->assertSee($resendUrl, false);
        $this->actingAs($colleague, 'company')->get('/company/mypage/users')
            ->assertSee('山田 太郎')->assertDontSee($resendUrl, false);

        // ほかの担当者は、URLを直接送っても送り直せない
        $this->actingAs($colleague, 'company')->post($resendUrl)->assertNotFound();
        $this->assertCount(1, $this->sentMails());

        // ほかの担当者が同じ宛先を招くと、前の招待は消えて、新しい番号の招待になる。招待した人も変わる
        $this->actingAs($colleague, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $replaced = TeamInvitation::sole();
        $this->assertNotSame($invitation->id, $replaced->id);
        $this->assertSame($colleague->id, $replaced->inviter_id);
        $this->assertStringContainsString('鈴木 次郎 様から', $this->lastMail()->getTextBody());

        // 取り消しは、招待した本人でなくてもできる
        $this->actingAs($inviter, 'company')->delete("/company/mypage/users/invitations/{$replaced->id}")
            ->assertRedirect(route('company.users.index'));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_operation_logs_keep_the_invitation_number(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $invitation = TeamInvitation::sole();
        $url = $this->lastInvitationUrl();

        // 招待の送信の記録に、招待の番号が残る。宛先のメールアドレスは残らない
        $sent = OperationLog::where('action', OperationLogAction::InvitationSend)->sole();
        $this->assertSame(['invitation' => '招待ID:'.$invitation->id], $sent->detail);

        // 招待から登録した記録にも、同じ番号が残る。誰が送った招待から、誰が登録したかをたどれる
        $this->post($url, $this->registerInput())->assertRedirect(route('company.mypage'));
        $created = OperationLog::where('action', OperationLogAction::Create)->where('target_type', 'company_user')->latest('id')->first();
        $this->assertSame(['invitation' => '招待ID:'.$invitation->id], $created->detail);
    }

    public function test_an_invitation_from_the_staff_is_shown_as_from_the_operator(): void
    {
        $company = $this->company();
        $me = $this->user($company);
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);

        $this->actingAs($staff, 'admin')->post("/admin/companies/{$company->id}/invitations", ['email' => 'sato@example.com']);
        $invitation = TeamInvitation::sole();
        $this->assertSame(['staff', $staff->id], [$invitation->inviter_type, $invitation->inviter_id]);

        // 企業の側の画面には、スタッフの氏名を出さず「運営」と出す。担当者は送り直せないが、取り消せる
        $this->actingAs($me, 'company')->get('/company/mypage/users')
            ->assertSee('運営')
            ->assertDontSee('管理 花子')
            ->assertDontSee(route('company.users.invitations.resend', $invitation), false);
        $this->actingAs($me, 'company')->post(route('company.users.invitations.resend', $invitation))->assertNotFound();
        $this->actingAs($me, 'company')->delete("/company/mypage/users/invitations/{$invitation->id}")
            ->assertRedirect(route('company.users.index'));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_address_already_used_in_the_company_cannot_be_invited_or_given_to_a_colleague(): void
    {
        $company = $this->company();
        $me = $this->user($company);
        $colleague = CompanyUser::create([
            'company_id' => $company->id,
            'login_id' => 'suzuki',
            'name' => '鈴木 次郎',
            'email' => 'suzuki@example.com',
            'password' => $me->password,
        ]);
        $this->user($this->company('other'), 'sato', 'elsewhere@example.com');
        $error = ['email' => 'このメールアドレスは、すでにほかの担当者が使っています。'];

        // すでに担当者が使っているアドレスには、招待を送れない
        $this->actingAs($me, 'company')->post('/company/mypage/users/invite', ['email' => 'suzuki@example.com'])
            ->assertSessionHasErrors($error);
        $this->assertSame(0, TeamInvitation::count());

        // 別の企業の担当者と同じアドレスには、送れる
        $this->actingAs($me, 'company')->post('/company/mypage/users/invite', ['email' => 'elsewhere@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, TeamInvitation::count());

        // ほかの担当者のアドレスを、すでに使われているアドレスには変えられない
        $this->actingAs($me, 'company')
            ->patch("/company/mypage/users/{$colleague->id}", ['name' => '鈴木 次郎', 'email' => 'yamada@example.com'])
            ->assertSessionHasErrors($error);
        $this->assertSame('suzuki@example.com', $colleague->fresh()->email);
    }

    public function test_link_cannot_be_used_once_the_address_is_registered_in_the_company(): void
    {
        $company = $this->company();
        $inviter = $this->user($company);

        $this->actingAs($inviter, 'company')->post('/company/mypage/users/invite', ['email' => 'sato@example.com']);
        $url = $this->lastInvitationUrl();
        $this->post(route('company.logout'));

        // 招待を送った後に、同じアドレスの担当者ができた
        $this->user($company, 'sato', 'sato@example.com');

        $this->get($url)->assertNotFound();
        $this->post($url, $this->registerInput(['login_id' => 'sato2']))->assertNotFound();
        $this->assertSame(2, CompanyUser::count());
    }
}
