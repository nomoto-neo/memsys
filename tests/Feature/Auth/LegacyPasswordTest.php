<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Support\Legacy\Md5Password;
use App\Support\Legacy\Sha1Password;
use App\Support\Legacy\Sha256Password;
use App\Support\LegacyPasswordUserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 既存のシステムから移した会員の、古い方式のパスワード。古いハッシュ値を今の方式で包んで持ち、
 * 本人がログインしたときに、今の方式のパスワードに置き換える。
 */
class LegacyPasswordTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'old-system-password';

    /** 既存のシステムから移した会員。今の方式のパスワードは持たず、古いハッシュ値を包んだものを持つ */
    private function migratedMember(string $legacyHash): Member
    {
        $member = Member::factory()->create(['email' => 'taro@example.com']);
        $member->forceFill([
            'password' => null,
            'legacy_password' => LegacyPasswordUserProvider::wrap($legacyHash),
        ])->save();

        return $member;
    }

    public function test_old_password_logs_in_and_is_replaced(): void
    {
        config(['members.member.legacy_passwords' => [Md5Password::class]]);
        $member = $this->migratedMember(md5(self::PASSWORD));

        // 今までのパスワードで、1段階目を通る
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));

        // 今の方式のパスワードに置き換わり、古い方式の値は消える
        $member->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $member->password));
        $this->assertNull($member->legacy_password);

        // 2段階目も、ふだんどおり
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])->assertRedirect(route('mypage'));
        $this->assertAuthenticatedAs($member, 'web');

        // 次からは、標準の照合で通る
        $this->post('/logout');
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
    }

    public function test_wrong_password_is_rejected_and_nothing_changes(): void
    {
        config(['members.member.legacy_passwords' => [Md5Password::class]]);
        $member = $this->migratedMember(md5(self::PASSWORD));
        $wrapped = $member->legacy_password;

        $this->post('/login', ['email' => 'taro@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $member->refresh();
        $this->assertNull($member->password);
        $this->assertSame($wrapped, $member->legacy_password);
    }

    public function test_methods_are_tried_in_order(): void
    {
        // 方式が混ざっているデータ。書いた順に試して、通ったものを採る
        config(['members.member.legacy_passwords' => [Md5Password::class, Sha1Password::class, Sha256Password::class]]);
        $member = $this->migratedMember(hash('sha256', self::PASSWORD));

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));

        $this->assertTrue(Hash::check(self::PASSWORD, $member->fresh()->password));
    }

    public function test_method_not_in_the_setting_does_not_pass(): void
    {
        // 設定に無い方式では通らない。新しく始めるサイト（設定が空）では、古い方式は働かない
        config(['members.member.legacy_passwords' => [Md5Password::class]]);
        $this->migratedMember(sha1(self::PASSWORD));

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        config(['members.member.legacy_passwords' => []]);
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
    }

    public function test_old_hash_itself_is_not_stored_and_does_not_pass(): void
    {
        config(['members.member.legacy_passwords' => [Md5Password::class]]);
        $member = $this->migratedMember(md5(self::PASSWORD));

        // DBにあるのは、今の方式で包んだ値。古いハッシュ値そのものは残っていない
        $this->assertStringStartsWith('$2y$', $member->legacy_password);
        $this->assertStringNotContainsString(md5(self::PASSWORD), $member->legacy_password);

        // 古いハッシュ値を知っているだけでは、ログインできない
        $this->post('/login', ['email' => 'taro@example.com', 'password' => md5(self::PASSWORD)])
            ->assertSessionHasErrors('email');
    }

    public function test_member_with_a_current_password_is_not_affected(): void
    {
        config(['members.member.legacy_passwords' => [Md5Password::class]]);

        // 今の方式のパスワードを持つ会員は、古い方式の値があっても、今のパスワードだけで照合する
        $member = Member::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make('current-password')]);
        $member->forceFill(['legacy_password' => LegacyPasswordUserProvider::wrap(md5(self::PASSWORD))])->save();

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'taro@example.com', 'password' => 'current-password'])
            ->assertRedirect(route('login.verify'));
    }

    public function test_password_reset_clears_the_old_password(): void
    {
        config(['members.member.legacy_passwords' => [Md5Password::class]]);
        $member = $this->migratedMember(md5(self::PASSWORD));

        // 一度もログインしないまま、パスワードを再設定した
        $this->post('/password/forgot', ['email' => 'taro@example.com']);
        $this->post('/password/reset', [
            'code' => $this->lastVerificationCode(),
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('mypage'));

        // 古い方式の値は消え、今までのパスワードでは入れなくなる
        $member->refresh();
        $this->assertNull($member->legacy_password);
        $this->assertTrue(Hash::check('brand-new-password', $member->password));

        $this->post('/logout');
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
    }
}
