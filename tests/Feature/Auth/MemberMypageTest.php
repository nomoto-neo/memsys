<?php

namespace Tests\Feature\Auth;

use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 個人会員のマイページまわりで、認証の共通部品を通る所。会員情報の変更のお知らせ、退会、
 * パスキーの管理の画面、スタッフによるパスワードの変更。
 */
class MemberMypageTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::factory()->create([
            'name' => '検証 太郎',
            'email' => 'old@example.com',
            'phone' => '03-1111-2222',
            'password' => Hash::make('old-password'),
        ]);
    }

    private function profileOf(Member $member, array $override = []): array
    {
        return $override + [
            'name' => $member->name,
            'kana' => $member->kana,
            'email' => $member->email,
            'phone' => $member->phone,
            'birthdate' => $member->birthdate?->format('Y-m-d'),
            'prefecture' => $member->prefecture,
        ];
    }

    public function test_profile_change_is_notified_to_old_and_new_address(): void
    {
        $member = $this->member();

        // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む。コードは新しいアドレスに届く
        $this->actingAs($member, 'web')
            ->patch('/mypage/update', $this->profileOf($member, ['email' => 'new@example.com', 'phone' => '03-3333-4444']))
            ->assertRedirect(route('mypage.email'));

        $this->assertSame(['old@example.com', '03-1111-2222'], [$member->fresh()->email, $member->fresh()->phone]);
        $this->assertSame(['new@example.com'], $this->recipientsOf($this->lastMail()));
        $this->actingAs($member, 'web')->get('/mypage/email/verify')->assertOk()->assertSee('new@example.com');

        // コードが合えば、メールアドレスもほかの項目もまとめて保存される
        $this->actingAs($member, 'web')
            ->post('/mypage/email/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage'));

        $this->assertSame(['new@example.com', '03-3333-4444'], [$member->fresh()->email, $member->fresh()->phone]);

        // お知らせは、変わる前と後の両方のアドレスに届く。本文に、変わる前のアドレスは載せない
        $notices = $this->sentMails()->slice(1);
        $recipients = $notices->map(fn ($mail) => $this->recipientsOf($mail)[0])->sort()->values()->all();
        $this->assertSame(['new@example.com', 'old@example.com'], $recipients);
        $this->assertSame(['会員情報変更のお知らせ', '会員情報変更のお知らせ'], array_slice($this->sentSubjects(), 1));
        $this->assertStringNotContainsString('old@example.com', $this->lastMail()->getTextBody());
        $this->assertStringContainsString('検証 太郎', $this->lastMail()->getTextBody());

        // 操作ログには、変わった列の名前だけが残る
        $log = OperationLog::where('action', OperationLogAction::Update)->sole();
        $this->assertSame(['member', $member->id, ['email', 'phone']], [$log->operator_type, $log->operator_id, $log->changed_fields]);
    }

    public function test_email_is_not_changed_without_the_right_code(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')
            ->patch('/mypage/update', $this->profileOf($member, ['email' => 'new@example.com']))
            ->assertRedirect(route('mypage.email'));

        $wrongCode = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->actingAs($member, 'web')
            ->post('/mypage/email/verify', ['code' => $wrongCode])
            ->assertRedirect(route('mypage.email'))
            ->assertSessionHasErrors('code');

        $this->assertSame('old@example.com', $member->fresh()->email);

        // 「入力内容を修正する」で、入力内容を持って編集画面へ戻る。その後は、コードの入力画面を開けない
        $this->actingAs($member, 'web')
            ->post('/mypage/email/verify/back')
            ->assertRedirect(route('mypage.edit'))
            ->assertSessionHasInput('email', 'new@example.com');
        $this->actingAs($member, 'web')->get('/mypage/email/verify')->assertRedirect(route('mypage.edit'));
    }

    public function test_email_taken_while_waiting_for_the_code_is_rejected(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')
            ->patch('/mypage/update', $this->profileOf($member, ['email' => 'new@example.com']))
            ->assertRedirect(route('mypage.email'));

        // コードの入力を待つ間に、同じアドレスで別の会員が登録された
        Member::factory()->create(['email' => 'new@example.com']);

        $this->actingAs($member, 'web')
            ->post('/mypage/email/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage.edit'))
            ->assertSessionHasErrors('email');

        $this->assertSame('old@example.com', $member->fresh()->email);
    }

    public function test_saving_without_email_change_skips_the_code(): void
    {
        $member = $this->member();

        // メールアドレスが変わらなければ、確認を挟まずにすぐ保存する。確認コードは送らない
        $this->actingAs($member, 'web')
            ->patch('/mypage/update', $this->profileOf($member, ['phone' => '03-3333-4444']))
            ->assertRedirect(route('mypage'));

        $this->assertSame('03-3333-4444', $member->fresh()->phone);
        $this->assertSame(['会員情報変更のお知らせ'], $this->sentSubjects());
    }

    public function test_saving_without_change_sends_nothing(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')->patch('/mypage/update', $this->profileOf($member))->assertRedirect(route('mypage'));

        $this->assertCount(0, $this->sentMails());
    }

    public function test_withdraw_deletes_the_member_and_notifies(): void
    {
        $member = $this->member();
        $member->trustedDevices()->create(['token_hash' => 'x', 'expires_at' => now()->addDay()]);

        $this->actingAs($member, 'web')->delete('/mypage/withdraw')->assertRedirect('/');

        $this->assertSame(0, Member::count());
        $this->assertGuest('web');
        $this->assertSame(['old@example.com'], $this->recipientsOf($this->lastMail()));

        // 退会は、本人による削除として操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::Delete)->sole();
        $this->assertSame(['member', $member->id, 'member', $member->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }

    public function test_passkey_screen_sends_a_code_for_identity_check(): void
    {
        $member = $this->member();

        // パスキーの管理の画面は開ける。登録の前の本人確認は、メールの確認コード
        $this->actingAs($member, 'web')->get('/mypage/passkeys')->assertOk();

        $this->actingAs($member, 'web')->post('/mypage/passkeys/code')->assertRedirect(route('mypage.passkeys'));
        $this->assertSame(['old@example.com'], $this->recipientsOf($this->lastMail()));

        // コードが合えば、登録に進める状態になる
        $this->actingAs($member, 'web')->post('/mypage/passkeys/confirm', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage.passkeys'))
            ->assertSessionHasNoErrors();
    }

    public function test_staff_changing_the_password_notifies_the_member(): void
    {
        $member = $this->member();
        $member->trustedDevices()->create(['token_hash' => 'x', 'expires_at' => now()->addDay()]);
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);

        $this->actingAs($staff, 'admin')
            ->patch('/admin/members/'.$member->id.'/update', $this->profileOf($member, [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]))
            ->assertSessionHasNoErrors();

        // パスワードが変わり、記憶済みの端末が消え、本人にお知らせが届く。担当者が変えたことも書く
        $this->assertTrue(Hash::check('new-password', $member->fresh()->password));
        $this->assertSame(0, $member->trustedDevices()->count());
        $this->assertSame(['パスワード変更のお知らせ'], $this->sentSubjects());
        $this->assertSame(['old@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertStringContainsString('担当者', $this->lastMail()->getTextBody());

        // スタッフの操作として残る
        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['staff', $staff->id, 'member', $member->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);
    }
}
