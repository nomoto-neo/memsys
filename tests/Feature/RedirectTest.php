<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Models\Redirect;
use Tests\TestCase;

/**
 * リダイレクト（Neobit\Core\Http\Middleware\ApplyRedirects・Neobit\Core\Seo\RedirectResolver・RedirectChecker・
 * Neobit\Core\Http\Controllers\RedirectController）。登録した古いURLを新しいURLへ移し、当たらず末尾が/なら外す。
 */
class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://example.com', 'neobit.seo.redirects' => true]);
        url()->forceRootUrl('https://example.com');
        url()->forceScheme('https');
    }

    private function manager(StaffAcl $acl = StaffAcl::Manager): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => $acl,
        ]);
    }

    private function redirect(string $old, string $new, array $override = []): Redirect
    {
        return Redirect::create($override + ['old_url' => $old, 'new_url' => $new, 'status_code' => 301, 'enabled' => true]);
    }

    /** URLをそのまま送る。テストのget()は末尾の/を外して送るので、末尾が/のURLを試すときに使う */
    private function getRaw(string $uri): TestResponse
    {
        return TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle(Request::create('https://example.com'.$uri, 'GET')));
    }

    // ---- 当て方 ----

    public function test_exact_redirect_carries_the_query_and_counts_hits(): void
    {
        $redirect = $this->redirect('/old-page', '/news', ['status_code' => 302]);
        $updatedAt = $redirect->updated_at;
        $this->travel(1)->minutes();

        $this->get('/old-page?utm_source=mail')->assertRedirect('https://example.com/news?utm_source=mail')->assertStatus(302);

        // 当たった回数と日時が増え、更新日時は変わらない
        $redirect->refresh();
        $this->assertSame(1, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
        $this->assertTrue($redirect->updated_at->equalTo($updatedAt));

        // 新しいURLに?があれば、引き継がない
        $this->redirect('/campaign', '/news?from=campaign');
        $this->get('/campaign?utm_source=mail')->assertRedirect('https://example.com/news?from=campaign');
    }

    public function test_exact_wins_and_the_longest_wildcard_wins(): void
    {
        $this->redirect('/blog/*', '/news/*');
        $this->redirect('/blog/special/*', '/topics');
        $this->redirect('/blog/special/2020', '/aboutus');

        $this->get('/blog/2020/abc')->assertRedirect('https://example.com/news/2020/abc');
        $this->getRaw('/blog/')->assertRedirect('https://example.com/news/');
        $this->get('/blog/special/2021')->assertRedirect('https://example.com/topics');
        $this->get('/blog/special/2020')->assertRedirect('https://example.com/aboutus');

        // 末尾の/が無い/blogには、/blog/*は当たらない。/blogは開ける画面が無いので404
        $this->get('/blog')->assertNotFound();
    }

    public function test_trailing_slash_is_a_different_url(): void
    {
        $this->redirect('/company/', '/aboutus');

        // 登録した末尾が/のURLは、登録のとおりに移す
        $this->getRaw('/company/')->assertRedirect('https://example.com/aboutus');

        // 登録の無い末尾が/のURLは、/を外して移す。?の後ろは残す
        $this->getRaw('/news/?page=2')->assertRedirect('https://example.com/news?page=2')->assertStatus(301);

        // 設定で止めると、登録は見ずに、/を外すだけ
        config(['neobit.seo.redirects' => false]);
        $this->getRaw('/company/')->assertRedirect('https://example.com/company');
        $this->get('/news')->assertOk();
    }

    public function test_only_get_and_enabled_redirects_apply(): void
    {
        $this->redirect('/old-contact', '/contact');
        $this->redirect('/paused', '/news', ['enabled' => false]);

        $this->assertFalse($this->post('/old-contact')->isRedirect());
        $this->get('/paused')->assertNotFound();
    }

    public function test_japanese_urls_match_in_either_form(): void
    {
        $this->redirect('/お知らせ/*', '/news');

        $this->get('/'.rawurlencode('お知らせ').'/'.rawurlencode('秋'))->assertRedirect('https://example.com/news');

        // 日本語の新しいURLは、%の符号にして返す
        $this->redirect('/old-guide', '/ガイド');
        $this->assertSame('https://example.com/'.rawurlencode('ガイド'), $this->get('/old-guide')->headers->get('Location'));
    }

    // ---- 画面 ----

    public function test_manager_adds_edits_tests_and_deletes(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'admin')->get(route('admin.redirects.index'))->assertOk()->assertSee('data-redirect-add', false);

        // 追加。%の符号は日本語に戻して保存する
        $this->actingAs($manager, 'admin')
            ->post(route('admin.redirects.store'), ['old_url' => '/blog/*', 'new_url' => '/news/*', 'status_code' => '301', 'enabled' => '1'])
            ->assertRedirect(route('admin.redirects.index'));
        $redirect = Redirect::firstOrFail();
        $this->assertSame('/news/*', $redirect->new_url);

        // URLを試す
        $this->actingAs($manager, 'admin')->get(route('admin.redirects.index', ['test' => 'https://example.com/blog/a?x=1']))
            ->assertSee('当たるリダイレクト：/blog/*（ワイルドカード）')
            ->assertSee('301 で /news/a?x=1 へ移ります。');
        $this->actingAs($manager, 'admin')->get(route('admin.redirects.index', ['test' => '/contact']))
            ->assertSee('リダイレクトしません。サイトの画面が開きます。');
        $this->actingAs($manager, 'admin')->get(route('admin.redirects.index', ['test' => '/contact/']))
            ->assertSee('末尾の / を外して /contact へ移ります。');

        // 編集。一覧の行の中から送り、見ていた一覧（検索）に戻る
        $listUrl = route('admin.redirects.index', ['q' => 'blog']);
        $this->actingAs($manager, 'admin')->get($listUrl)->assertSee('data-redirect-row', false)->assertSee('data-editing="0"', false);
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.redirects.update', $redirect), ['editing_id' => $redirect->id, 'old_url' => '/blog/*', 'new_url' => '/news', 'status_code' => '302', 'enabled' => '0'])
            ->assertRedirect($listUrl);
        $redirect->refresh();
        $this->assertSame([302, false], [$redirect->status_code, $redirect->enabled]);

        // 入力エラーのときは、その行を開いた状態にして、エラーを出す
        $this->actingAs($manager, 'admin')->from($listUrl)
            ->patch(route('admin.redirects.update', $redirect), ['editing_id' => $redirect->id, 'old_url' => '/contact', 'new_url' => '/news', 'status_code' => '301', 'enabled' => '1'])
            ->assertRedirect($listUrl);
        $this->actingAs($manager, 'admin')->get($listUrl)
            ->assertSee('data-editing="1"', false)
            ->assertSee('この古いURLは、今ある画面（/contact）と重なります。');

        // 削除
        $this->actingAs($manager, 'admin')->delete(route('admin.redirects.destroy', $redirect))->assertRedirect($listUrl);
        $this->assertSame(0, Redirect::count());

        $this->assertSame(
            [OperationLogAction::Create->value, OperationLogAction::Update->value, OperationLogAction::Delete->value],
            OperationLog::where('target_type', 'redirect')->orderBy('id')->get()->map(fn (OperationLog $log) => $log->action->value)->all(),
        );
    }

    public function test_wrong_redirects_are_rejected(): void
    {
        $manager = $this->manager();
        $this->redirect('/a', '/news');
        $this->redirect('/old-news/*', '/archive/*');

        $cases = [
            // 形
            [['old_url' => 'blog', 'new_url' => '/news'], 'old_url', '古いURLは、/ から始めてください。? と # は書けません。'],
            [['old_url' => '/blog?x=1', 'new_url' => '/news'], 'old_url', '古いURLは、/ から始めてください。? と # は書けません。'],
            [['old_url' => '/b*log', 'new_url' => '/news'], 'old_url', '* は、末尾に1つだけ書けます。'],
            [['old_url' => '/blog', 'new_url' => '/news/*'], 'new_url', '古いURLに * が無いときは、新しいURLに * を書けません。'],
            [['old_url' => '/blog', 'new_url' => 'javascript:alert(1)'], 'new_url', '新しいURLは、/ から始まるサイトの中のパスか、http(s):// から始まるURLにしてください。'],
            // 二重・同じ
            [['old_url' => '/a', 'new_url' => '/contact'], 'old_url', 'この古いURLは、もう登録されています。'],
            [['old_url' => '/same', 'new_url' => '/same'], 'new_url', '古いURLと新しいURLが同じです。'],
            // 今ある画面との重なり
            [['old_url' => '/contact', 'new_url' => '/news'], 'old_url', 'この古いURLは、今ある画面（/contact）と重なります。'],
            [['old_url' => '/news*', 'new_url' => '/topics'], 'old_url', 'この古いURLは、今ある画面（/news・/newsletter・/newsletter/done・/newsletter/verify）を巻き込みます。'],
            // 輪とつながり
            [['old_url' => '/blog/*', 'new_url' => '/blog/new/*'], 'new_url', '移した先が、また /blog/* に当たり、くり返しになります。'],
            [['old_url' => '/x', 'new_url' => '/a'], 'new_url', '移した先の /a は、ほかのリダイレクト（/a → /news）に当たります。/news へ直接移すようにしてください。'],
            [['old_url' => '/news-old', 'new_url' => '/old-news/1'], 'new_url', '移した先の /old-news/1 は、ほかのリダイレクト（/old-news/* → /archive/*）に当たります。/archive/* へ直接移すようにしてください。'],
            // 開ける画面が無い。固定ページの/{slug}は英小文字だけなので、大文字のURLは当たらない
            [['old_url' => '/y', 'new_url' => '/No_Such'], 'new_url', '新しいURLの /No_Such は、サイトの中で開ける画面がありません。'],
        ];

        foreach ($cases as [$input, $field, $message]) {
            $this->actingAs($manager, 'admin')
                ->post(route('admin.redirects.store'), $input + ['status_code' => '301', 'enabled' => '1'])
                ->assertSessionHasErrors([$field => $message]);
        }

        // 輪。/b → /c があるときに /c → /b
        $this->redirect('/b', '/c');
        $this->actingAs($manager, 'admin')
            ->post(route('admin.redirects.store'), ['old_url' => '/c', 'new_url' => '/b', 'status_code' => '301', 'enabled' => '1'])
            ->assertSessionHasErrors(['new_url' => '移した先が、ほかのリダイレクト（/b → /c）を通ってこの古いURLに戻り、くり返しになります。']);

        // つながりの反対。/d → /e があるときに /e → /news
        $this->redirect('/d', '/e');
        $this->actingAs($manager, 'admin')
            ->post(route('admin.redirects.store'), ['old_url' => '/e', 'new_url' => '/news', 'status_code' => '301', 'enabled' => '1'])
            ->assertSessionHasErrors(['old_url' => 'この古いURLは、ほかのリダイレクト（/d → /e）の移した先です。そちらの新しいURLを先に直してください。']);
    }

    public function test_disabled_setting_is_shown_on_the_screen(): void
    {
        config(['neobit.seo.redirects' => false]);

        $this->actingAs($this->manager(), 'admin')->get(route('admin.redirects.index'))
            ->assertSee('設定（seo.redirects）で無効化されているので、登録しても機能しません。');
    }

    public function test_staff_who_is_not_a_manager_is_sent_away(): void
    {
        $staff = $this->manager(StaffAcl::Staff);

        $this->actingAs($staff, 'admin')->get(route('admin.redirects.index'))->assertRedirect(route('admin.staff.edit', $staff));
    }

    // ---- CSV ----

    /** CSVを確認画面まで送り、結果と実行の合言葉を返す */
    private function confirmCsv(Staff $manager, array $lines): array
    {
        $file = UploadedFile::fake()->createWithContent('redirects.csv', "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n");
        $confirm = $this->actingAs($manager, 'admin')->post(route('admin.redirects.csv-import.confirm'), ['csv_file' => $file])->assertOk();

        return [$confirm->viewData('result'), $confirm->viewData('token')];
    }

    public function test_csv_download_and_import(): void
    {
        $manager = $this->manager();
        $this->redirect('/a', '/news');

        // 書き出し
        $csv = $this->actingAs($manager, 'admin')->get(route('admin.redirects.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('古いURL,新しいURL,種類,有効', $csv);
        $this->assertStringContainsString('/a,/news,301,有効', $csv);

        // 取り込み。/aは更新、/blog/*は追加
        $this->actingAs($manager, 'admin')->get(route('admin.redirects.csv-import'))->assertOk()->assertSee(route('admin.redirects.index'), false);
        [$result, $token] = $this->confirmCsv($manager, [
            '古いURL,新しいURL,種類,有効',
            '/a,/contact,302,無効',
            '/blog/*,/news/*,301,有効',
        ]);
        $this->assertFalse($result->hasErrors());
        $this->actingAs($manager, 'admin')
            ->post(route('admin.redirects.csv-import.execute'), ['confirm_token' => $token])
            ->assertSessionHas('status', 'リダイレクト：追加 1件・更新 1件を取り込みました（変更なし 0件）。');

        $this->assertSame(['/contact', 302, false], [Redirect::where('old_url', '/a')->value('new_url'), Redirect::where('old_url', '/a')->value('status_code'), Redirect::where('old_url', '/a')->first()->enabled]);
        $this->get('/blog/x')->assertRedirect('https://example.com/news/x');
    }

    public function test_csv_rows_are_checked_together(): void
    {
        $manager = $this->manager();

        // CSVの中の重なりと、CSVのほかの行とのつながり
        [$result, $token] = $this->confirmCsv($manager, [
            '古いURL,新しいURL,種類,有効',
            '/p,/news,301,有効',
            '/p,/contact,301,有効',
            '/q,/p,301,有効',
        ]);

        $this->assertNull($token);
        $messages = collect($result->errorRows())->flatMap(fn ($row) => array_column($row->errors, 1))->implode(' ');
        $this->assertStringContainsString('同じ古いURLが2行目にもあります。', $messages);
        $this->assertStringContainsString('移した先の /p は、ほかのリダイレクト（/p → /news）に当たります。', $messages);
        $this->assertSame(0, Redirect::count());
    }
}
