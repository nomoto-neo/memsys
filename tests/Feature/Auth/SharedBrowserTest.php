<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 同じブラウザで、会員と管理画面の両方を使うとき。ガードは別でも、セッションは1つなので、
 * 片方の操作がもう片方に響かないことを確かめる。
 *
 * ログインは、実際の画面の流れで行う。actingAs()は、セッションを通さずにログイン中の人を
 * 決めるので、セッションの扱いを確かめるテストには使えない。
 */
class SharedBrowserTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    private string $secret;

    private function member(string $email = 'taro@example.com'): Member
    {
        return Member::factory()->create(['email' => $email, 'password' => Hash::make(self::PASSWORD)]);
    }

    private function staff(): Staff
    {
        $this->secret = (new Google2FA())->generateSecretKey();

        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make(self::PASSWORD),
            'acl' => StaffAcl::Manager,
        ]);
        $staff->registerTwoFactor($this->secret);

        return $staff;
    }

    private function loginAsMember(string $email = 'taro@example.com'): void
    {
        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()]);
    }

    private function loginAsStaff(): void
    {
        $this->post('/app/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->post('/app/admin/login/verify', ['code' => (new Google2FA())->getCurrentOtp($this->secret)]);
    }

    /**
     * 次のリクエストで、ログイン中の人をセッションから読み直させる。テストでは、リクエストを
     * またいでも同じアプリが使われ、前のリクエストで読んだ人を覚えたままになるため
     */
    private function forgetLoadedUsers(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_member_logout_keeps_the_staff_login(): void
    {
        $this->member();
        $this->staff();
        $this->loginAsMember();
        $this->loginAsStaff();

        $this->post('/logout')->assertRedirect('/');
        $this->forgetLoadedUsers();

        // 会員はログアウトされ、管理画面はログインしたまま
        $this->get('/mypage')->assertRedirect(route('login'));
        $this->get('/app/admin')->assertOk();
    }

    public function test_staff_logout_keeps_the_member_login(): void
    {
        $this->member();
        $this->staff();
        $this->loginAsMember();
        $this->loginAsStaff();

        $this->post('/app/admin/logout')->assertRedirect(route('admin.login'));
        $this->forgetLoadedUsers();

        $this->get('/app/admin')->assertRedirect(route('admin.login'));
        $this->get('/mypage')->assertOk();
    }

    public function test_logout_clears_what_the_user_left_in_the_session(): void
    {
        $this->staff();
        $this->loginAsStaff();

        // 管理画面で検索すると、検索条件がセッションに残る
        $this->post('/app/admin/members', ['q' => '検索した言葉']);
        $this->assertNotNull(session('search_session'));

        $this->post('/app/admin/logout');

        // ログアウトで消える。同じブラウザを次に使う人に、見えないようにするため
        $this->assertNull(session('search_session'));
    }

    public function test_another_member_can_log_in_after_logout(): void
    {
        $this->member('taro@example.com');
        $second = $this->member('jiro@example.com');

        $this->loginAsMember('taro@example.com');
        $this->post('/logout');
        $this->forgetLoadedUsers();

        // 前の人の値がセッションに残っていると、次の人が最初の画面でログアウトされてしまう
        $this->loginAsMember('jiro@example.com');
        $this->forgetLoadedUsers();

        $this->get('/mypage')->assertOk();
        $this->assertAuthenticatedAs($second, 'web');
    }

    public function test_login_returns_only_to_a_page_of_the_same_side(): void
    {
        $this->member();
        $this->staff();

        // ログインせずに管理画面を開こうとして、ログイン画面へ回された
        $this->get('/app/admin/members')->assertRedirect(route('admin.login'));

        // 会員としてログインしても、管理画面のURLへは飛ばない
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage'));

        // 記録は残っているので、スタッフとしてログインすると、開こうとしていた画面へ戻る
        $this->post('/app/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->post('/app/admin/login/verify', ['code' => (new Google2FA())->getCurrentOtp($this->secret)])
            ->assertRedirect(route('admin.members.index'));
    }

    public function test_each_side_remembers_its_own_page_to_return_to(): void
    {
        $this->member();
        $this->staff();

        // ログインせずに、管理画面と会員の画面を続けて開こうとした
        $this->get('/app/admin/members?page=2')->assertRedirect(route('admin.login'));
        $this->get('/mypage/edit')->assertRedirect(route('login'));

        // 後から会員の画面を開こうとしていても、スタッフのログインの後は、管理画面の元の画面へ戻る
        $this->post('/app/admin/login', ['login_id' => 'hanako', 'password' => self::PASSWORD]);
        $this->post('/app/admin/login/verify', ['code' => (new Google2FA())->getCurrentOtp($this->secret)])
            ->assertRedirect('/app/admin/members?page=2');

        // 会員のログインの後は、会員の元の画面へ戻る
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage.edit'));

        // 戻り先は1回使ったら消える。次のログインでは、既定の画面へ移る
        $this->post('/logout');
        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage'));
    }

    public function test_page_to_return_to_is_not_remembered_for_a_form_post(): void
    {
        $this->member();

        // フォームの送信先は、戻り先にしない
        $this->delete('/mypage/withdraw')->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'taro@example.com', 'password' => self::PASSWORD]);
        $this->post('/login/verify', ['code' => $this->lastVerificationCode()])
            ->assertRedirect(route('mypage'));
    }

    public function test_withdraw_keeps_the_staff_login(): void
    {
        $this->member();
        $this->staff();
        $this->loginAsMember();
        $this->loginAsStaff();

        $this->delete('/mypage/withdraw')->assertRedirect('/');
        $this->forgetLoadedUsers();

        $this->assertSame(0, Member::count());
        $this->get('/app/admin')->assertOk();
    }
}
