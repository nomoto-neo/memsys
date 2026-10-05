<?php

namespace Tests\Feature\Auth;

use App\Enums\OperationLogAction;
use App\Models\Member;
use App\Models\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 個人会員のパスワードの変更（マイページ）と再設定（パスワードを忘れたとき）。
 * どちらも、メールの確認コードで本人を確かめてから変える。変えた後は、記憶済みの端末を
 * 無効にして、お知らせのメールを送る。
 */
class MemberPasswordTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const OLD_PASSWORD = 'old-password';

    private const NEW_PASSWORD = 'new-password';

    private function member(): Member
    {
        return Member::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);
    }

    public function test_change_in_mypage(): void
    {
        $member = $this->member();
        $member->trustedDevices()->create(['token_hash' => 'x', 'expires_at' => now()->addDay()]);

        // 変更の画面を開くと、本人のメールアドレスに確認コードが届く
        $this->actingAs($member, 'web')->get('/mypage/password')->assertOk();
        $this->assertSame(['taro@example.com'], $this->recipientsOf($this->lastMail()));
        $code = $this->lastVerificationCode();

        // 画面を開き直しても、有効なコードがあれば送り直さない
        $this->actingAs($member, 'web')->get('/mypage/password')->assertOk();
        $this->assertCount(1, $this->sentMails());

        $this->actingAs($member, 'web')->patch('/mypage/password/update', [
            'code' => $code,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('mypage'));

        // パスワードが変わり、記憶済みの端末が消え、お知らせのメールが届く
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $member->fresh()->password));
        $this->assertSame(0, $member->trustedDevices()->count());
        $this->assertSame('パスワード変更のお知らせ', $this->lastMail()->getSubject());
        $this->assertSame(['taro@example.com'], $this->recipientsOf($this->lastMail()));

        // 本人の操作として、操作ログに残る
        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['member', $member->id, 'member', $member->id], [$log->operator_type, $log->operator_id, $log->target_type, $log->target_id]);

        // 変えた本人は、ログインしたまま使い続けられる
        $this->get('/mypage')->assertOk();
        $this->assertAuthenticatedAs($member, 'web');
    }

    public function test_change_needs_the_right_code(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')->get('/mypage/password');
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->actingAs($member, 'web')->patch('/mypage/password/update', [
            'code' => $wrong,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('password.edit'))->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $member->fresh()->password));
    }

    public function test_reset_when_forgotten(): void
    {
        $member = $this->member();

        $this->post('/password/forgot', ['email' => 'taro@example.com'])->assertRedirect(route('password.reset'));
        $code = $this->lastVerificationCode();

        $this->post('/password/reset', [
            'code' => $code,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('mypage'));

        // パスワードが変わり、そのままログインした状態になる
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $member->fresh()->password));
        $this->assertAuthenticatedAs($member, 'web');
        $this->assertSame('パスワード変更のお知らせ', $this->lastMail()->getSubject());

        // ログインの前の操作でも、本人の操作として残る
        $log = OperationLog::where('action', OperationLogAction::PasswordChange)->sole();
        $this->assertSame(['member', $member->id], [$log->operator_type, $log->operator_id]);
    }

    public function test_forgot_does_not_reveal_whether_the_address_is_registered(): void
    {
        $this->member();

        // 登録の無いメールアドレスでも、同じ画面へ進む。メールは送らない
        $this->post('/password/forgot', ['email' => 'nobody@example.com'])->assertRedirect(route('password.reset'));
        $this->assertCount(0, $this->sentMails());
    }

    public function test_reset_needs_the_right_code(): void
    {
        $member = $this->member();

        $this->post('/password/forgot', ['email' => 'taro@example.com']);
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/password/reset', [
            'code' => $wrong,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('password.reset'))->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $member->fresh()->password));
        $this->assertGuest('web');
    }
}
