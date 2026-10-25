<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Category;
use App\Models\News;
use App\Models\Page;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Neobit\Core\Models\SeoMeta;
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

    public function test_unknown_name_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        seo('keywords');
    }
}
