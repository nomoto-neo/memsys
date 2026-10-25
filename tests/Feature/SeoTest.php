<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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

    public function test_unknown_name_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        seo('keywords');
    }
}
