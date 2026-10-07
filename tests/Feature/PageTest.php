<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Page;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 固定ページ。管理画面で本文を書き、訪問者の側に /aboutus のようなURLで出す。
 */
class PageTest extends TestCase
{
    use RefreshDatabase;

    private const INPUT = [
        'title' => '会社概要',
        'slug' => 'aboutus',
        'body' => '<h2>会社概要</h2><p>本文</p>',
        'disp_flg' => '1',
    ];

    private function staff(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    private function page(array $override = []): Page
    {
        return Page::create($override + [
            'title' => '会社概要',
            'slug' => 'aboutus',
            'body' => '<p>本文です。</p>',
            'disp_flg' => true,
        ]);
    }

    // ---- 訪問者の側 ----

    public function test_visitor_opens_the_page_by_its_slug(): void
    {
        $this->page();

        $this->get('/aboutus')
            ->assertOk()
            ->assertSee('会社概要')
            ->assertSee('<p>本文です。</p>', false);
    }

    public function test_hidden_page_and_unknown_slug_are_not_found(): void
    {
        $this->page(['disp_flg' => false]);

        $this->get('/aboutus')->assertNotFound();
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_page_route_does_not_take_over_other_screens(): void
    {
        // ほかの画面と同じ名前の行がDBにあっても、ほかの画面が先に当たる
        $this->page(['slug' => 'login', 'title' => '乗っ取り']);

        $this->get('/login')->assertOk()->assertDontSee('乗っ取り');
        $this->get('/')->assertOk();
    }

    public function test_script_in_the_body_is_removed_when_shown(): void
    {
        // 保存のときの無害化を通っていない本文でも、表示のときに取り除く
        $this->page(['body' => '<p>本文</p><script>alert(1)</script><img src="x" onerror="alert(2)">']);

        $this->get('/aboutus')
            ->assertOk()
            ->assertSee('<p>本文</p>', false)
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('onerror', false);
    }

    // ---- 管理画面 ----

    public function test_staff_registers_a_page_through_the_confirm_screen(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')->get(route('admin.pages.create'))->assertOk();
        $this->actingAs($staff, 'admin')->post(route('admin.pages.confirm.create'), self::INPUT)
            ->assertOk()
            ->assertSee('固定ページ登録の確認');
        $this->assertSame(0, Page::count());

        $this->actingAs($staff, 'admin')->post(route('admin.pages.store'), self::INPUT)
            ->assertRedirect(route('admin.pages.index', ['back']));

        $page = Page::sole();
        $this->assertSame('aboutus', $page->slug);
        $this->assertTrue($page->disp_flg);

        $this->get('/aboutus')->assertOk()->assertSee('<h2>会社概要</h2>', false);
    }

    public function test_script_in_the_body_is_removed_when_saved(): void
    {
        $this->actingAs($this->staff(), 'admin')
            ->post(route('admin.pages.store'), ['body' => '<p>本文</p><script>alert(1)</script>'] + self::INPUT)
            ->assertSessionHasNoErrors();

        $this->assertSame('<p>本文</p>', Page::sole()->body);
    }

    public function test_slug_must_be_usable_as_a_url(): void
    {
        $staff = $this->staff();
        $this->page(['slug' => 'taken']);

        // ほかの画面で使っている名前、使えない文字、ほかのページと同じ名前は断る
        foreach (['login', 'admin', 'news', 'About', 'about us', 'a/b', '-about', 'taken'] as $slug) {
            $this->actingAs($staff, 'admin')
                ->post(route('admin.pages.store'), ['slug' => $slug] + self::INPUT)
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(1, Page::count());
    }

    public function test_staff_updates_and_deletes_a_page(): void
    {
        $staff = $this->staff();
        $page = $this->page();

        $this->actingAs($staff, 'admin')->get(route('admin.pages.index'))->assertOk()->assertSee('/aboutus');
        $this->actingAs($staff, 'admin')->get(route('admin.pages.show', $page))->assertOk();
        $this->actingAs($staff, 'admin')->get(route('admin.pages.edit', $page))->assertOk();

        // 自分の名前のままで更新できる。URLの名前を変えると、古いURLは開けなくなる
        $this->actingAs($staff, 'admin')
            ->patch(route('admin.pages.update', $page), ['title' => '私たちについて', 'slug' => 'about'] + self::INPUT)
            ->assertSessionHasNoErrors();

        $this->get('/about')->assertOk()->assertSee('私たちについて');
        $this->get('/aboutus')->assertNotFound();

        $this->actingAs($staff, 'admin')->delete(route('admin.pages.destroy', $page))
            ->assertRedirect(route('admin.pages.index', ['back']));
        $this->assertSame(0, Page::count());
    }

    public function test_screens_need_a_staff_login(): void
    {
        $this->get(route('admin.pages.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.pages.store'), self::INPUT)->assertRedirect(route('admin.login'));
    }
}
