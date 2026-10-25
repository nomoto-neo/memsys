<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Category;
use App\Models\Member;
use App\Models\News;
use App\Models\Page;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Models\SeoMeta;
use Neobit\Core\Models\Setting;
use Tests\TestCase;

/**
 * SEOのタグ（Neobit\Core\Seo\Seo・neobit::_seo_head・ヘルパーのseo()）。記事の詳細では記事から、
 * ほかの画面では@section('title')とサイトの既定（config/neobit.phpのseo）から、タイトル・説明文・OGPを出す。
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.site_name' => 'デモサイト', 'app.url' => 'https://example.com']);
        url()->forceRootUrl('https://example.com');
        url()->forceScheme('https');
    }

    private function news(array $override = []): News
    {
        $news = News::create($override + [
            'title' => '秋のイベント',
            'article_date' => '2026-10-01',
            'disp_flg' => true,
            'members_only' => false,
            'body' => '<p>10月に<strong>イベント</strong>を開きます。</p><p>ぜひお越しください。</p>',
            'list_image' => 'list.jpg',
        ]);
        $news->categories()->attach(Category::create(['name' => 'お知らせ', 'seq' => 0])->id);

        return $news;
    }

    public function test_news_page_uses_the_article(): void
    {
        $news = $this->news();

        $this->get(route('news.show', $news).'?from=top')
            ->assertOk()
            ->assertSee('<title>秋のイベント｜デモサイト</title>', false)
            // 説明文は、本文のタグを除き、改行や空白を1つにまとめたもの
            ->assertSee('<meta name="description" content="10月にイベントを開きます。 ぜひお越しください。">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            // 画像とURLは絶対のURL。URLは?の後ろを除く
            ->assertSee('<meta property="og:image" content="https://example.com'.$news->list_image_url.'">', false)
            ->assertSee('<meta property="og:url" content="'.route('news.show', $news).'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_long_body_is_cut_to_the_length(): void
    {
        config(['neobit.seo.description_length' => 10]);
        $news = $this->news(['body' => '<p>'.str_repeat('あ', 30).'</p>']);

        $this->get(route('news.show', $news))
            ->assertSee('<meta name="description" content="'.str_repeat('あ', 10).'…">', false);
    }

    public function test_page_without_image_uses_the_small_card(): void
    {
        Page::create(['title' => '会社概要', 'slug' => 'aboutus', 'body' => '<p>本文です。</p>', 'disp_flg' => true]);

        $this->get('/aboutus')
            ->assertOk()
            ->assertSee('<title>会社概要｜デモサイト</title>', false)
            ->assertSee('<meta name="description" content="本文です。">', false)
            ->assertDontSee('og:image', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    public function test_other_pages_use_the_site_defaults(): void
    {
        // 記事も@section('title')も無い画面は、サイト名だけ。既定の説明文が空なら、説明文のタグを出さない
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>デモサイト</title>', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertDontSee('name="description"', false);

        // 既定の説明文と画像を決めれば、それを出す
        config(['neobit.seo.description' => 'デモサイトの説明', 'neobit.seo.image' => '/images/ogp.png']);
        $this->get('/')
            ->assertSee('<meta name="description" content="デモサイトの説明">', false)
            ->assertSee('<meta property="og:image" content="https://example.com/images/ogp.png">', false);

        // @section('title')のある画面は、タイトルの形に当てはめる
        $this->get(route('login'))->assertSee('<title>ログイン｜デモサイト</title>', false);
    }

    public function test_article_does_not_leak_into_the_next_request(): void
    {
        $news = $this->news();

        $this->get(route('news.show', $news))->assertSee('<title>秋のイベント｜デモサイト</title>', false);
        $this->get('/')->assertSee('<title>デモサイト</title>', false);
    }

    // ---- 記事ごとのSEOの欄 ----

    private function manager(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    /** ニュースの今の値の入力。SEOの欄は$seoで足す */
    private function newsInput(News $news, array $seo): array
    {
        return [
            'title' => $news->title,
            'article_date' => $news->article_date->format('Y-m-d'),
            'disp_flg' => '1',
            'members_only' => '0',
            'category_ids' => $news->categories()->pluck('t_categories.id')->all(),
            'list_image' => $news->list_image,
        ] + $seo;
    }

    public function test_seo_fields_override_the_automatic_values(): void
    {
        $news = $this->news();
        $manager = $this->manager();

        // 編集の画面に、閉じた「SEOの設定」の枠が出る
        $this->actingAs($manager, 'admin')->get(route('admin.news.edit', $news))
            ->assertOk()
            ->assertSee('<details class="mb-3 border rounded p-2" >', false)
            ->assertSee('SEOの設定（空欄なら自動）');

        $input = $this->newsInput($news, [
            'seo_title' => '秋のイベントのご案内',
            'seo_description' => '検索結果に出す説明',
            'seo_image' => '/images/event.png',
            'seo_noindex' => '1',
        ]);

        // 確認画面に、入れた値が出る
        $this->actingAs($manager, 'admin')->patch(route('admin.news.confirm.update', $news), $input)
            ->assertOk()->assertSee('秋のイベントのご案内')->assertSee('検索に出さない');

        $this->actingAs($manager, 'admin')->patch(route('admin.news.update', $news), $input)->assertRedirect();

        // 訪問者の側は、SEOの欄の値で出る
        $this->get(route('news.show', $news))
            ->assertSee('<title>秋のイベントのご案内｜デモサイト</title>', false)
            ->assertSee('<meta name="description" content="検索結果に出す説明">', false)
            ->assertSee('<meta property="og:image" content="https://example.com/images/event.png">', false)
            ->assertSee('<meta name="robots" content="noindex">', false);

        // 編集の画面では、値があるので枠が開いた状態で出る
        $this->actingAs($manager, 'admin')->get(route('admin.news.edit', $news))
            ->assertSee('<details class="mb-3 border rounded p-2"  open >', false)
            ->assertSee('value="秋のイベントのご案内"', false);
    }

    public function test_emptied_seo_fields_remove_the_row(): void
    {
        $news = $this->news();
        $news->seoMeta()->create(['title' => '前のタイトル']);

        $this->actingAs($this->manager(), 'admin')
            ->patch(route('admin.news.update', $news), $this->newsInput($news, ['seo_title' => '', 'seo_description' => '', 'seo_image' => '', 'seo_noindex' => '0']))
            ->assertRedirect();

        $this->assertNull($news->fresh()->seoMeta);
        $this->get(route('news.show', $news))
            ->assertSee('<title>秋のイベント｜デモサイト</title>', false)
            ->assertDontSee('name="robots"', false);
    }

    public function test_wrong_image_url_is_rejected(): void
    {
        $news = $this->news();
        $manager = $this->manager();

        // サイトの中のパスはスラッシュから。プロトコルを省いた//や、http(s)のほかは断る
        foreach (['images/event.png', 'javascript:alert(1)', '//evil.example.com/a.png'] as $image) {
            $this->actingAs($manager, 'admin')
                ->from(route('admin.news.edit', $news))
                ->patch(route('admin.news.confirm.update', $news), $this->newsInput($news, ['seo_image' => $image]))
                ->assertSessionHasErrors('seo_image');
        }
    }

    public function test_deleting_the_news_deletes_the_seo_row(): void
    {
        $news = $this->news();
        $news->seoMeta()->create(['title' => '消えるタイトル']);

        $this->actingAs($this->manager(), 'admin')->delete(route('admin.news.destroy', $news))->assertRedirect();

        $this->assertSame(0, SeoMeta::count());
    }

    // ---- 自動のnoindex・canonical・サイトマップ・robots.txt ----

    public function test_pages_needing_login_and_listed_routes_are_noindex(): void
    {
        // ログインしないと開けない画面（authのミドルウェア）
        $member = Member::factory()->create();
        $this->actingAs($member, 'web')->get(route('mypage'))->assertSee('<meta name="robots" content="noindex">', false);

        // 設定に並べたルート（見本ではお問い合わせの完了）
        $this->get(route('contact.thanks'))->assertSee('<meta name="robots" content="noindex">', false);

        // 普通の画面には付けない
        $this->get(route('news.index'))->assertDontSee('name="robots"', false);
    }

    public function test_filtered_list_is_noindex(): void
    {
        $category = Category::create(['name' => 'イベント', 'seq' => 0]);

        // 絞り込みをした一覧は、コントローラーがSeo::noindex()を呼ぶ
        $this->post(route('news.search'), ['category_id' => (string) $category->id]);
        $this->get(route('news.index', ['page' => 1]))->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_canonical_drops_the_query_except_the_page(): void
    {
        $this->get(route('news.index', ['page' => 2, 'utm_source' => 'mail']))
            ->assertSee('<link rel="canonical" href="https://example.com/news?page=2">', false);

        $news = $this->news();
        $this->get(route('news.show', $news).'?from=top')
            ->assertSee('<link rel="canonical" href="'.route('news.show', $news).'">', false);
    }

    public function test_sitemap_lists_public_pages_and_articles(): void
    {
        $visible = $this->news(['title' => '公開の記事']);
        $membersOnly = $this->news(['title' => '会員限定の記事', 'members_only' => true]);
        $hidden = $this->news(['title' => '非表示の記事', 'disp_flg' => false]);
        $noindex = $this->news(['title' => '検索に出さない記事']);
        $noindex->seoMeta()->create(['noindex' => true]);
        Page::create(['title' => '会社概要', 'slug' => 'aboutus', 'body' => '<p>本文</p>', 'disp_flg' => true]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $xml = $response->getContent();

        // 記事を持たないページ（設定のルート）と、公開の記事・固定ページ
        foreach (['https://example.com', route('news.index'), route('contact.create'), route('news.show', $visible), route('pages.show', 'aboutus')] as $url) {
            $this->assertStringContainsString("<loc>{$url}</loc>", $xml);
        }
        $this->assertStringContainsString('<lastmod>'.$visible->updated_at->toAtomString().'</lastmod>', $xml);

        // 会員限定・非表示・SEOの欄で検索に出さないにした記事は載せない
        foreach ([$membersOnly, $hidden, $noindex] as $news) {
            $this->assertStringNotContainsString('<loc>'.route('news.show', $news).'</loc>', $xml);
        }
    }

    public function test_robots_txt_depends_on_the_environment(): void
    {
        // 本番以外は、全部を巡回させない
        $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /\n", false);

        // 本番は、設定の場所を巡回させず、サイトマップの場所を伝える
        app()['env'] = 'production';
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("Disallow: /admin\nDisallow: /company\nDisallow: /mypage\n\nSitemap: https://example.com/sitemap.xml", false);
    }

    public function test_unknown_name_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        seo('keywords');
    }

    // ---- SEO設定の画面 ----

    /**
     * SEO設定の画面の入力。ページは設定のseo.page_routesの順に、番号で並べる。$pagesはルート名 => 値
     *
     * @param  array<string, array<string, string>>  $pages
     */
    private function seoSettingsInput(array $defaults = [], array $pages = []): array
    {
        $input = $defaults + ['title_format' => '', 'description' => '', 'image' => '', 'site_verification' => '', 'pages' => []];

        foreach (array_keys((array) config('neobit.seo.page_routes')) as $routeName) {
            $input['pages'][] = ($pages[$routeName] ?? []) + ['route' => $routeName, 'title' => '', 'description' => '', 'image' => '', 'noindex' => '0'];
        }

        return $input;
    }

    public function test_seo_settings_override_the_config(): void
    {
        $manager = $this->manager();

        // 先に開いて、画面の設定（空）をキャッシュに置く。保存で作り直されることを確かめるため
        $this->get('/')->assertSee('<title>デモサイト</title>', false);

        // 空欄のときに使う設定ファイルの値を、入力欄の見本に出す
        $this->actingAs($manager, 'admin')
            ->get(route('admin.seo-settings.index'))
            ->assertOk()
            ->assertSee('placeholder="{title}｜{site}"', false)
            ->assertSee('トップページ')
            ->assertSee('https://example.com/news');

        // 確認タグは、Search Consoleが示すタグを丸ごと貼ってもよい
        $input = $this->seoSettingsInput([
            'title_format' => '{title} - {site}',
            'description' => 'サイトの説明',
            'image' => '/images/site.png',
            'site_verification' => '<meta name="google-site-verification" content="abc_DEF-123" />',
        ], [
            'top' => ['title' => 'トップの題', 'description' => 'トップの説明'],
            'news.index' => ['description' => 'お知らせの説明'],
        ]);
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.seo-settings.update'), $input)
            ->assertRedirect(route('admin.seo-settings.index'));

        // 閉じたページの枠に、設定したページの目印を出す
        $this->actingAs($manager, 'admin')->get(route('admin.seo-settings.index'))->assertSee('設定あり');

        $this->get('/')
            ->assertSee('<title>トップの題 - デモサイト</title>', false)
            ->assertSee('<meta name="description" content="トップの説明">', false)
            ->assertSee('<meta property="og:image" content="https://example.com/images/site.png">', false)
            ->assertSee('<meta name="google-site-verification" content="abc_DEF-123">', false);

        // ページの説明文が無いページは、既定の説明文
        $this->get(route('news.index'))->assertSee('<meta name="description" content="お知らせの説明">', false);
        $this->get(route('contact.create'))->assertSee('<meta name="description" content="サイトの説明">', false);

        // 操作ログに1件。同じ値で保存し直したときは残さない
        $this->actingAs($manager, 'admin')->patch(route('admin.seo-settings.update'), $input);
        $logs = OperationLog::where('action', OperationLogAction::SeoSettingsChange->value)->get();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('タイトルの形', json_encode($logs->first()->detail, JSON_UNESCAPED_UNICODE));
    }

    public function test_page_settings_win_over_the_section_title_but_not_the_article(): void
    {
        config(['neobit.seo.page_routes' => ['newsletter.create' => 'メルマガの登録', 'news.show' => 'お知らせの詳細']]);
        $manager = $this->manager();
        $news = $this->news();

        // 引数の要るルートは、URLを出さずに並べる
        $this->actingAs($manager, 'admin')->get(route('admin.seo-settings.index'))->assertOk()->assertSee('お知らせの詳細');

        $this->actingAs($manager, 'admin')->patch(route('admin.seo-settings.update'), $this->seoSettingsInput([], [
            'newsletter.create' => ['title' => 'メルマガのご案内'],
            'news.show' => ['title' => '詳細の題'],
        ]));

        // 画面の値は@section('title')より先。記事のあるページは記事の値
        $this->get(route('newsletter.create'))->assertSee('<title>メルマガのご案内｜デモサイト</title>', false);
        $this->get(route('news.show', $news))->assertSee('<title>秋のイベント｜デモサイト</title>', false);
    }

    public function test_emptied_settings_fall_back_to_the_config(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager, 'admin')->patch(route('admin.seo-settings.update'), $this->seoSettingsInput(
            ['title_format' => '{title} - {site}', 'site_verification' => 'abc'],
            ['top' => ['title' => 'トップの題']],
        ));

        // 全部空にすると、値はnullで、ページは持たない
        $this->actingAs($manager, 'admin')->patch(route('admin.seo-settings.update'), $this->seoSettingsInput());

        $this->assertNull(Setting::where('key', 'seo.title_format')->value('value'));
        $this->assertSame([], Setting::where('key', 'seo.pages')->value('value'));
        $this->get('/')
            ->assertSee('<title>デモサイト</title>', false)
            ->assertDontSee('google-site-verification', false);
        $this->get(route('newsletter.create'))->assertSee('<title>メルマガの登録｜デモサイト</title>', false);
    }

    public function test_page_noindex_from_the_screen(): void
    {
        $this->actingAs($this->manager(), 'admin')->patch(route('admin.seo-settings.update'), $this->seoSettingsInput([], [
            'top' => ['noindex' => '1'],
        ]));

        // 検索に出さず、サイトマップにも載せない
        $this->get('/')->assertSee('<meta name="robots" content="noindex">', false);
        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('<loc>https://example.com</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('news.index').'</loc>', $xml);
    }

    public function test_wrong_settings_are_rejected(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'admin')
            ->patch(route('admin.seo-settings.update'), $this->seoSettingsInput(['title_format' => 'タイトル', 'image' => 'images/a.png'], [
                'top' => ['image' => '//evil.example.com/a.png'],
            ]))
            ->assertSessionHasErrors(['title_format', 'image', 'pages.0.image']);

        $this->actingAs($manager, 'admin')
            ->patch(route('admin.seo-settings.update'), $this->seoSettingsInput(['site_verification' => '<script>alert(1)</script>']))
            ->assertSessionHasErrors('site_verification');

        // 設定のseo.page_routesに無いルートの値は、捨てる
        $input = $this->seoSettingsInput();
        $input['pages'][] = ['route' => 'login', 'title' => '外の題', 'description' => '', 'image' => '', 'noindex' => '0'];
        $this->actingAs($manager, 'admin')->patch(route('admin.seo-settings.update'), $input)->assertSessionHasNoErrors();
        $this->assertSame([], Setting::where('key', 'seo.pages')->value('value'));
    }

    public function test_staff_who_is_not_a_manager_is_sent_away(): void
    {
        $staff = $this->manager();
        $staff->update(['acl' => StaffAcl::Staff]);

        $this->actingAs($staff, 'admin')
            ->get(route('admin.seo-settings.index'))
            ->assertRedirect(route('admin.staff.edit', $staff));
    }
}
