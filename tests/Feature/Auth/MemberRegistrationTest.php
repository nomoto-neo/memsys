<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 個人会員の登録。入力したメールアドレスに確認コードを送り、コードが合ってから会員を作る。
 */
class MemberRegistrationTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const INPUT = [
        'name' => '登録 太郎',
        'kana' => 'トウロク タロウ',
        'email' => 'new@example.com',
        'phone' => '090-1234-5678',
        'birthdate' => '1990-01-02',
        'prefecture' => 13,
        'notice_mail' => 1,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ];

    public function test_member_is_created_after_the_code_is_verified(): void
    {
        // 確認画面までは、何も作らず、メールも送らない
        $this->post('/regist/confirm', self::INPUT)->assertOk();
        $this->assertCount(0, $this->sentMails());

        // 確認コードを、入力したメールアドレスに送る。会員はまだ作らない
        $this->post('/regist/send', self::INPUT)->assertRedirect(route('regist.verify'));
        $this->assertSame(['new@example.com'], $this->recipientsOf($this->lastMail()));
        $this->assertSame(0, Member::count());

        // コードが合ったら会員を作り、そのままログインした状態にする
        $this->post('/regist/verify', ['code' => $this->lastVerificationCode()])->assertRedirect(route('mypage'));

        $member = Member::where('email', 'new@example.com')->sole();
        $this->assertSame('登録 太郎', $member->name);
        $this->assertTrue(Hash::check('new-password', $member->password));
        $this->assertTrue($member->receivesNoticeMail());
        $this->assertAuthenticatedAs($member, 'web');
    }

    public function test_wrong_code_does_not_create_the_member(): void
    {
        $this->post('/regist/send', self::INPUT);
        $wrong = $this->lastVerificationCode() === '000000' ? '111111' : '000000';

        $this->post('/regist/verify', ['code' => $wrong])
            ->assertRedirect(route('regist.verify'))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, Member::count());
        $this->assertGuest('web');
    }

    public function test_registered_address_is_rejected(): void
    {
        Member::factory()->create(['email' => 'new@example.com']);

        $this->post('/regist/send', self::INPUT)
            ->assertRedirect(route('regist.create'))
            ->assertSessionHasErrors('email');

        $this->assertCount(0, $this->sentMails());
        $this->assertSame(1, Member::count());
    }

    public function test_verification_needs_the_input_step(): void
    {
        $this->get('/regist/verify')->assertRedirect(route('regist.create'));
        $this->post('/regist/verify', ['code' => '123456'])->assertRedirect(route('regist.create'));
    }
}
