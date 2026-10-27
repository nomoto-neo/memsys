<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * パスキーの登録の案内。パスワードでログインした直後に、パスキーを持っていない人へ1回だけ出す
 * （Neobit\Core\Auth\PasskeySuggestion）。見せたかどうかは、ブラウザのCookieで覚える。
 */
class PasskeySuggestionTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    /** 案内の見出し */
    private const HEADLINE = 'パスキーを登録しませんか';

    /** 見せた印を入れるCookieの名前（個人会員） */
    private const COOKIE = 'member_passkey_suggested';

    private function member(): Member
    {
        return Member::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    /** 個人会員として、パスワードと確認コードでログインする。2段階目の返事を返す */
    private function loginAsMember(string $password = self::PASSWORD): TestResponse
    {
        $this->post('/login', ['email' => 'taro@example.com', 'password' => $password]);

        return $this->post('/login/verify', ['code' => $this->lastVerificationCode()]);
    }

    public function test_suggestion_is_shown_once_right_after_the_login(): void
    {
        $this->member();

        $this->loginAsMember()
            ->assertRedirect(route('mypage'))
            ->assertSessionHas('passkeySuggest', 'mypage.passkeys')
            ->assertCookie(self::COOKIE);

        // ログインの次に開いた画面の上に出る。パスキーの画面へのボタンが付く
        $this->get('/mypage')->assertOk()->assertSee(self::HEADLINE)->assertSee(route('mypage.passkeys'));

        // 出るのは1回だけ
        $this->get('/mypage')->assertOk()->assertDontSee(self::HEADLINE);
    }

    public function test_suggestion_is_not_shown_again_on_the_same_browser(): void
    {
        $this->member();

        $cookie = $this->loginAsMember()->getCookie(self::COOKIE)->getValue();
        $this->post('/logout');

        $this->withCookie(self::COOKIE, $cookie)->loginAsMember()
            ->assertRedirect(route('mypage'))
            ->assertSessionMissing('passkeySuggest');
    }

    public function test_suggestion_is_shown_again_after_the_password_is_changed(): void
    {
        $member = $this->member();

        $cookie = $this->loginAsMember()->getCookie(self::COOKIE)->getValue();
        $this->post('/logout');

        // パスワードを変えると、パスキーは消される。登録し直してもらえるよう、もう1回勧める
        $member->forceFill(['password' => Hash::make('new-password-123')])->save();

        $this->withCookie(self::COOKIE, $cookie)->loginAsMember('new-password-123')
            ->assertSessionHas('passkeySuggest', 'mypage.passkeys');
    }

    public function test_suggestion_is_not_shown_to_one_who_already_has_a_passkey(): void
    {
        $member = $this->member();

        DB::table('passkeys')->insert([
            'authenticatable_type' => 'member',
            'authenticatable_id' => $member->id,
            'name' => 'テストのパスキー',
            'credential_id' => 'test-credential-id',
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->loginAsMember()
            ->assertRedirect(route('mypage'))
            ->assertSessionMissing('passkeySuggest')
            ->assertCookieMissing(self::COOKIE);
    }

    public function test_suggestion_is_shown_right_after_registering_the_authenticator(): void
    {
        // 認証アプリをまだ登録していないスタッフ。ログインの途中で登録する
        Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make(self::PASSWORD),
            'acl' => StaffAcl::Manager,
        ]);

        $this->post('/app/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->get('/app/admin/login/verify');
        $this->post('/app/admin/login/verify', ['code' => (new Google2FA())->getCurrentOtp(session('staff.login.pending_secret'))])
            ->assertRedirect(route('admin.dashboard'));

        // 登録が済んで最初に開く画面で出る
        $this->get('/app/admin')->assertOk()->assertSee(self::HEADLINE)->assertSee(route('admin.passkeys'));
        $this->get('/app/admin')->assertOk()->assertDontSee(self::HEADLINE);
    }
}
