<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;
use App\Models\NewsPhoto;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use Neobit\Core\Crud\ChildTableDefinitions;
use Neobit\Core\Crud\ChildTables;
use Neobit\Core\Csv\CsvColumnSet;
use Neobit\Core\Csv\CsvImportSettings;
use Neobit\Core\Upload\UploadFieldDefinitions;
use Neobit\Core\Upload\UploadFilePath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Neobit\Core\Upload\UploadType;

/**
 * 子テーブル（Neobit\Core\Crud\ChildTables）。見本はニュースの写真（画像とキャプションの組）。
 * 行のidを保った保存、空の行の除き方、行の数、ファイルの確定と削除、書き換えた送信への備え。
 */
class ChildTablesTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(UploadFilePath::PRIVATE_DISK);
        Storage::fake(UploadFilePath::PUBLIC_DISK);

        $this->staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $this->category = Category::create(['name' => 'お知らせ', 'seq' => 0]);
    }

    /** 保存済みのニュースを1件作る */
    private function news(): News
    {
        $news = News::create([
            'title' => '既存の記事',
            'article_date' => '2026-10-20',
            'disp_flg' => true,
            'members_only' => false,
        ]);
        $news->categories()->attach($this->category->id);

        return $news;
    }

    /** ニュースの本体の入力。写真の組は$photosで足す。一覧用画像は必須なので、新しい画像を付ける */
    private function newsInput(array $photos): array
    {
        return [
            'title' => '写真のある記事',
            'article_date' => '2026-10-20',
            'disp_flg' => '1',
            'members_only' => '0',
            'category_ids' => [$this->category->id],
            'list_image_tmp' => $this->tmpImage(),
            'list_image_origin' => 'list.jpg',
            'photos_group' => '1',
        ] + $photos;
    }

    /**
     * 写真の組の列ごとの配列。1行は['id' => …, 'caption' => …, 'image' => …, 'tmp' => …, 'origin' => …, 'del' => …]
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function photoRows(array $rows): array
    {
        $columns = ['photos_id' => [], 'photos_caption' => [], 'photos_image' => [], 'photos_image_tmp' => [], 'photos_image_origin' => [], 'photos_image_del' => []];

        foreach ($rows as $row) {
            $columns['photos_id'][] = $row['id'] ?? '';
            $columns['photos_caption'][] = $row['caption'] ?? '';
            $columns['photos_image'][] = $row['image'] ?? '';
            $columns['photos_image_tmp'][] = $row['tmp'] ?? '';
            $columns['photos_image_origin'][] = $row['origin'] ?? '';
            $columns['photos_image_del'][] = $row['del'] ?? '';
        }

        return $columns;
    }

    /** アップロードしたばかりの一時ファイルを置き、その名前を返す */
    private function tmpImage(): string
    {
        $name = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::TMP_DISK)->put(UploadFilePath::TMP_DIR.'/'.$name, 'image');

        return $name;
    }

    /** 記事の保存先にあるファイルのパス */
    private function storedPath(News $news, string $filename): string
    {
        return UploadFilePath::directory(News::class, $news->id).'/'.$filename;
    }

    private function store(array $photoRows): TestResponse
    {
        return $this->actingAs($this->staff, 'admin')->post(route('admin.news.store'), $this->newsInput($this->photoRows($photoRows)));
    }

    private function update(News $news, array $input): TestResponse
    {
        return $this->actingAs($this->staff, 'admin')->patch(route('admin.news.update', $news), $input);
    }

    // ---- 登録 ----

    public function test_rows_are_saved_in_screen_order_without_empty_rows(): void
    {
        $tmp = $this->tmpImage();

        $this->store([
            ['caption' => '外観', 'tmp' => $tmp, 'origin' => 'gaikan.jpg'],
            [],
            ['caption' => '受付'],
        ])->assertRedirect();

        $news = News::sole();
        $photos = $news->photos;

        // 空の行は入らない。並び順は画面の順
        $this->assertSame(['外観', '受付'], $photos->pluck('caption')->all());
        $this->assertSame([0, 1], $photos->pluck('seq')->all());

        // 画像は記事の保存先へ移る
        $this->assertSame($tmp, $photos[0]->image);
        $this->assertSame('gaikan.jpg', $photos[0]->image_origin);
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists($this->storedPath($news, $tmp));
        $this->assertNull($photos[1]->image);
    }

    public function test_ids_posted_on_create_are_ignored(): void
    {
        $other = $this->news();
        $otherPhoto = $other->photos()->create(['caption' => 'ほかの記事', 'seq' => 0]);

        $this->store([['id' => $otherPhoto->id, 'caption' => '書き換え']]);

        // ほかの記事の行は変わらず、新しい記事に新しい行ができる
        $this->assertSame('ほかの記事', $otherPhoto->fresh()->caption);
        $news = News::where('id', '!=', $other->id)->sole();
        $this->assertSame(['書き換え'], $news->photos->pluck('caption')->all());
        $this->assertNotSame($otherPhoto->id, $news->photos[0]->id);
    }

    // ---- 更新 ----

    public function test_update_keeps_ids_and_adds_and_removes_rows(): void
    {
        $news = $this->news();
        $first = $news->photos()->create(['caption' => '1枚目', 'seq' => 0]);
        $second = $news->photos()->create(['caption' => '2枚目', 'seq' => 1]);

        // 1枚目のキャプションを直し、2枚目を消し、新しい行を先頭に足す
        $this->update($news, $this->newsInput($this->photoRows([
            ['caption' => '新しい行'],
            ['id' => $first->id, 'caption' => '1枚目（直した）'],
        ])))->assertRedirect();

        $photos = $news->fresh()->photos;
        $this->assertSame(['新しい行', '1枚目（直した）'], $photos->pluck('caption')->all());
        $this->assertSame($first->id, $photos[1]->id);
        $this->assertSame([0, 1], $photos->pluck('seq')->all());
        $this->assertNull(NewsPhoto::find($second->id));
    }

    public function test_replaced_and_removed_files_are_deleted(): void
    {
        $news = $this->news();
        $oldA = Str::random(40).'.jpg';
        $oldB = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $oldA), 'a');
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $oldB), 'b');
        $a = $news->photos()->create(['image' => $oldA, 'image_origin' => 'a.jpg', 'caption' => 'A', 'seq' => 0]);
        $b = $news->photos()->create(['image' => $oldB, 'image_origin' => 'b.jpg', 'caption' => 'B', 'seq' => 1]);
        $new = $this->tmpImage();

        // Aは新しい画像に差し替え、Bは画像だけ消す（キャプションは残すので行は残る）
        $this->update($news, $this->newsInput($this->photoRows([
            ['id' => $a->id, 'caption' => 'A', 'image' => $oldA, 'tmp' => $new, 'origin' => 'new.jpg', 'del' => '1'],
            ['id' => $b->id, 'caption' => 'B', 'image' => $oldB, 'del' => '1'],
        ])))->assertRedirect();

        $this->assertSame($new, $a->fresh()->image);
        $this->assertSame('new.jpg', $a->fresh()->image_origin);
        $this->assertNull($b->fresh()->image);
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing($this->storedPath($news, $oldA));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing($this->storedPath($news, $oldB));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists($this->storedPath($news, $new));
    }

    public function test_a_row_cannot_point_at_another_rows_file(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $owner = $news->photos()->create(['image' => $file, 'caption' => '持ち主', 'seq' => 0]);
        $other = $news->photos()->create(['caption' => 'ほかの行', 'seq' => 1]);

        // ほかの行のhiddenに、持ち主の行のファイル名を書いて送る
        $this->update($news, $this->newsInput($this->photoRows([
            ['id' => $owner->id, 'caption' => '持ち主', 'image' => $file],
            ['id' => $other->id, 'caption' => 'ほかの行', 'image' => $file],
        ])));

        $this->assertSame($file, $owner->fresh()->image);
        $this->assertNull($other->fresh()->image);
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists($this->storedPath($news, $file));
    }

    public function test_same_id_twice_makes_the_second_a_new_row(): void
    {
        $news = $this->news();
        $photo = $news->photos()->create(['caption' => '元', 'seq' => 0]);

        $this->update($news, $this->newsInput($this->photoRows([
            ['id' => $photo->id, 'caption' => '1つ目'],
            ['id' => $photo->id, 'caption' => '2つ目'],
        ])));

        $photos = $news->fresh()->photos;
        $this->assertSame(['1つ目', '2つ目'], $photos->pluck('caption')->all());
        $this->assertSame($photo->id, $photos[0]->id);
    }

    public function test_all_rows_can_be_removed_and_missing_marker_keeps_rows(): void
    {
        $news = $this->news();
        $news->photos()->create(['caption' => '残る', 'seq' => 0]);

        // 組の印が無ければ（組を送っていなければ）触らない
        $input = $this->newsInput([]);
        unset($input['photos_group']);
        $this->update($news, $input);
        $this->assertSame(1, $news->photos()->count());

        // 印があって行が1つも無ければ、全部消す
        $this->update($news, $this->newsInput([]));
        $this->assertSame(0, $news->photos()->count());
    }

    public function test_row_count_is_limited_by_max(): void
    {
        $rows = array_fill(0, 11, ['caption' => '写真']);

        $this->store($rows)->assertSessionHasErrors('photos_id');
        $this->assertSame(0, News::count());
    }

    // ---- 確認画面・削除・ファイルの表示 ----

    public function test_confirm_page_carries_the_rows_without_empty_ones(): void
    {
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.confirm.store'), $this->newsInput($this->photoRows([
                ['caption' => '外観'],
                [],
            ])))
            ->assertOk()
            ->assertSee('外観')
            ->assertSee('name="photos_group" value="1"', false)
            ->assertSee('name="photos_caption[]" value="外観"', false)
            ->assertDontSee('name="photos_caption[]" value=""', false);
    }

    public function test_edit_screen_shows_the_rows_in_order(): void
    {
        $news = $this->news();
        $news->photos()->create(['caption' => '2枚目', 'seq' => 1]);
        $news->photos()->create(['caption' => '1枚目', 'seq' => 0]);

        $html = $this->actingAs($this->staff, 'admin')->get(route('admin.news.edit', $news))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'value="2枚目"'), strpos($html, 'value="1枚目"'));
        $this->assertStringContainsString('class="repeatable_group" data-group="photos" data-max="10"', $html);
    }

    public function test_deleting_the_news_deletes_rows_and_files(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $news->photos()->create(['image' => $file, 'caption' => '消える', 'seq' => 0]);

        $this->actingAs($this->staff, 'admin')->delete(route('admin.news.destroy', $news))->assertRedirect();

        $this->assertSame(0, NewsPhoto::count());
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing($this->storedPath($news, $file));
    }

    public function test_private_photo_is_served_for_its_row(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $photo = $news->photos()->create(['image' => $file, 'caption' => '見える', 'seq' => 0]);

        $this->actingAs($this->staff, 'admin')->get($photo->image_url)->assertOk();

        // 行に無いファイル名では返さない
        $this->actingAs($this->staff, 'admin')
            ->get(UploadFilePath::url(News::class, $news->id, 'photos_image', Str::random(40).'.jpg'))
            ->assertNotFound();
    }

    public function test_admin_show_and_update_confirm_display_the_rows(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $photo = $news->photos()->create(['image' => $file, 'image_origin' => 'a.jpg', 'caption' => '詳細に出る', 'seq' => 0]);

        // 詳細画面は、読み取り専用で行を並べる
        $this->actingAs($this->staff, 'admin')->get(route('admin.news.show', $news))
            ->assertOk()
            ->assertSee('詳細に出る')
            ->assertSee($photo->image_url);

        // 更新の確認画面は、保存済みの画像のプレビューとhiddenを出す
        $this->actingAs($this->staff, 'admin')
            ->patch(route('admin.news.confirm.update', $news), $this->newsInput($this->photoRows([
                ['id' => $photo->id, 'caption' => '直した', 'image' => $file, 'origin' => 'a.jpg'],
            ])))
            ->assertOk()
            ->assertSee('直した')
            ->assertSee($photo->image_url)
            ->assertSee('name="photos_id[]" value="'.$photo->id.'"', false);
    }

    public function test_public_news_page_shows_photos_and_captions(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $photo = $news->photos()->create(['image' => $file, 'caption' => '公開の写真', 'seq' => 0]);
        $news->photos()->create(['caption' => 'キャプションだけ', 'seq' => 1]);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee($photo->image_url)
            ->assertSee('公開の写真')
            ->assertSee('キャプションだけ');

        // 一般公開の記事の写真は、ログインしていなくても見られる
        $this->get($photo->image_url)->assertOk();
    }

    public function test_rows_can_be_exported_with_the_csv_group_expansion(): void
    {
        $news = $this->news();
        $news->photos()->create(['image' => 'a.jpg', 'caption' => '1枚目', 'seq' => 0]);
        $news->photos()->create(['image' => 'b.jpg', 'caption' => '2枚目', 'seq' => 1]);

        $columns = new CsvColumnSet([
            '記事ID' => 'id',
            '写真:*' => ['photos', 'group' => ['キャプション' => 'caption', '画像' => 'image']],
        ]);
        $query = News::query()->with('photos');
        $columns->prepareExport($query, true);

        $this->assertSame(['記事ID', 'キャプション:1', '画像:1', 'キャプション:2', '画像:2'], $columns->headings());
        $this->assertSame([(string) $news->id, '1枚目', 'a.jpg', '2枚目', 'b.jpg'], array_map('strval', $columns->exportRow($query->first())));
    }

    // ---- 添付ファイル（ファイルだけの組。保存のたびに行を入れ直す） ----

    public function test_attachments_are_saved_with_their_original_names_in_screen_order(): void
    {
        $first = Str::random(40).'.pdf';
        $second = Str::random(40).'.pdf';
        Storage::disk(UploadFilePath::TMP_DISK)->put(UploadFilePath::TMP_DIR.'/'.$first, 'a');
        Storage::disk(UploadFilePath::TMP_DISK)->put(UploadFilePath::TMP_DIR.'/'.$second, 'b');

        // 空の行は保存しない
        $input = $this->newsInput([]) + [
            'attach_group' => '1',
            'attach_id' => ['', '', ''],
            'attach_file' => ['', '', ''],
            'attach_file_tmp' => [$first, '', $second],
            'attach_file_origin' => ['案内.pdf', '', '申込書.pdf'],
            'attach_file_del' => ['', '', ''],
        ];
        $this->actingAs($this->staff, 'admin')->post(route('admin.news.store'), $input)->assertRedirect();

        $news = News::firstOrFail();
        $this->assertSame(['案内.pdf', '申込書.pdf'], $news->attach->pluck('file_origin')->all());
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists($this->storedPath($news, $first));

        // 管理画面からは、行のファイルを返す。行に無いファイル名では返さない
        $this->actingAs($this->staff, 'admin')->get($news->attach->first()->url)->assertOk();
        $this->actingAs($this->staff, 'admin')
            ->get(UploadFilePath::url(News::class, $news->id, 'attach_file', Str::random(40).'.pdf'))
            ->assertNotFound();

        // 訪問者の詳細の画面には、元のファイル名で出す
        $this->get(route('news.show', $news))->assertOk()->assertSeeInOrder(['案内.pdf', '申込書.pdf']);
    }

    public function test_attachments_are_reinserted_and_removed_row_deletes_its_file(): void
    {
        $news = $this->news();
        $kept = Str::random(40).'.pdf';
        $removed = Str::random(40).'.pdf';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $kept), 'a');
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $removed), 'b');
        $keptRow = $news->attach()->create(['file' => $kept, 'file_origin' => '残す.pdf']);
        $news->attach()->create(['file' => $removed, 'file_origin' => '消す.pdf']);

        $input = $this->newsInput([]) + [
            'attach_group' => '1',
            'attach_id' => [(string) $keptRow->id],
            'attach_file' => [$kept],
            'attach_file_tmp' => [''],
            'attach_file_origin' => ['残す.pdf'],
            'attach_file_del' => [''],
        ];
        $this->update($news, $input)->assertRedirect();

        // 残した行は、新しいidで入れ直される。ファイルと表示名はそのまま
        $rows = $news->attach()->get();
        $this->assertCount(1, $rows);
        $this->assertNotSame($keptRow->id, $rows[0]->id);
        $this->assertSame([$kept, '残す.pdf'], [$rows[0]->file, $rows[0]->file_origin]);
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertExists($this->storedPath($news, $kept));
        Storage::disk(UploadFilePath::PRIVATE_DISK)->assertMissing($this->storedPath($news, $removed));
    }

    public function test_attachment_put_in_an_empty_middle_row_keeps_screen_order(): void
    {
        $news = $this->news();
        $first = Str::random(40).'.pdf';
        $last = Str::random(40).'.pdf';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $first), 'a');
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $last), 'c');
        $firstRow = $news->attach()->create(['file' => $first, 'file_origin' => '1つ目.pdf']);
        $lastRow = $news->attach()->create(['file' => $last, 'file_origin' => '3つ目.pdf']);
        $middle = Str::random(40).'.pdf';
        Storage::disk(UploadFilePath::TMP_DISK)->put(UploadFilePath::TMP_DIR.'/'.$middle, 'b');

        // 2つの行の間の空いた行に、新しいファイルを入れる
        $input = $this->newsInput([]) + [
            'attach_group' => '1',
            'attach_id' => [(string) $firstRow->id, '', (string) $lastRow->id],
            'attach_file' => [$first, '', $last],
            'attach_file_tmp' => ['', $middle, ''],
            'attach_file_origin' => ['1つ目.pdf', '2つ目.pdf', '3つ目.pdf'],
            'attach_file_del' => ['', '', ''],
        ];
        $this->update($news, $input)->assertRedirect();

        $this->assertSame(['1つ目.pdf', '2つ目.pdf', '3つ目.pdf'], $news->attach()->pluck('file_origin')->all());
    }

    public function test_same_attachment_id_twice_keeps_the_file_only_in_the_first_row(): void
    {
        $news = $this->news();
        $file = Str::random(40).'.pdf';
        Storage::disk(UploadFilePath::PRIVATE_DISK)->put($this->storedPath($news, $file), 'a');
        $row = $news->attach()->create(['file' => $file, 'file_origin' => 'a.pdf']);

        // 同じidを2回送っても、ファイルが残るのは1行目だけ。2行目は新しい行になり、ファイルは付かない
        $input = $this->newsInput([]) + [
            'attach_group' => '1',
            'attach_id' => [(string) $row->id, (string) $row->id],
            'attach_file' => [$file, $file],
            'attach_file_tmp' => ['', ''],
            'attach_file_origin' => ['a.pdf', 'a.pdf'],
            'attach_file_del' => ['', ''],
        ];
        $this->update($news, $input)->assertRedirect();

        $this->assertSame([$file, null], $news->attach()->pluck('file')->all());
    }

    public function test_attachments_are_exported_to_csv_and_ignored_on_import(): void
    {
        Storage::fake(CsvImportSettings::TMP_DISK);
        $news = $this->news();
        $news->attach()->create(['file' => 'a.pdf', 'file_origin' => '案内.pdf']);

        // 書き出しには、表示名と保存ファイル名の組が出る
        $csv = $this->actingAs($this->staff, 'admin')->get(route('admin.news.csv'))->streamedContent();
        $csv = mb_convert_encoding($csv, 'UTF-8', 'UTF-8, SJIS-win');
        $this->assertStringContainsString('添付ファイル:1', $csv);
        $this->assertStringContainsString('案内.pdf', $csv);

        // 取り込みでは、添付ファイルの列を無視する（組の取り込みはまだ受け持たない）
        $file = UploadedFile::fake()->createWithContent('news.csv', "\xEF\xBB\xBF".implode("\r\n", [
            '記事ID,タイトル,記事日付,状態,公開範囲,カテゴリー,添付ファイル:1,保存ファイル名:1',
            "{$news->id},書き換えた記事,2026/10/20,表示,一般公開,お知らせ,別の名前.pdf,b.pdf",
        ])."\r\n");
        $confirm = $this->actingAs($this->staff, 'admin')->post(route('admin.news.csv-import.confirm'), ['csv_file' => $file])->assertOk();
        $this->assertNotNull($confirm->viewData('token'));
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.csv-import.execute'), ['confirm_token' => $confirm->viewData('token')])
            ->assertSessionMissing('error');

        $this->assertSame('書き換えた記事', $news->fresh()->title);
        $this->assertSame([['a.pdf', '案内.pdf']], $news->attach()->get()->map(fn ($row) => [$row->file, $row->file_origin])->all());
    }

    // ---- 固定の枠（添付ファイルは4枠） ----

    /** 新規登録・編集の画面のHTMLのうち、添付ファイルの組の部分 */
    private function attachGroupHtml(string $html): string
    {
        $start = strpos($html, 'data-group="attach"');
        $end = strpos($html, 'data-group="photos"');

        return substr($html, $start, $end - $start);
    }

    public function test_fixed_slots_are_always_shown_without_add_and_remove_buttons(): void
    {
        $html = $this->actingAs($this->staff, 'admin')->get(route('admin.news.create'))->assertOk()->getContent();
        $attach = $this->attachGroupHtml($html);

        // 4枠と、行のひな型の1つ。「ファイルを追加」と×は出さない
        $this->assertStringContainsString('data-fixed="1"', $attach);
        $this->assertSame(5, substr_count($attach, 'name="attach_id[]"'));
        $this->assertStringNotContainsString('ファイルを追加', $attach);
        $this->assertStringNotContainsString('repeatable_remove', $attach);

        // 写真（固定の枠ではない）は、今までどおり「写真を追加」と×を出す
        $this->assertStringContainsString('写真を追加', $html);
        $this->assertStringContainsString('class="repeatable_remove"', $html);
    }

    public function test_edit_screen_fills_the_fixed_slots_and_shows_extra_rows(): void
    {
        // 1件なら、1件と空の3枠
        $news = $this->news();
        $news->attach()->create(['file' => 'a.pdf', 'file_origin' => 'a.pdf']);
        $attach = $this->attachGroupHtml($this->actingAs($this->staff, 'admin')->get(route('admin.news.edit', $news))->getContent());
        $this->assertSame(5, substr_count($attach, 'name="attach_id[]"'));

        // 枠より多い5件は、5件とも出す。そのまま保存すると、4件までで断る
        $many = $this->news();
        foreach (range(1, 5) as $number) {
            $many->attach()->create(['file' => "f{$number}.pdf", 'file_origin' => "f{$number}.pdf"]);
        }
        $attach = $this->attachGroupHtml($this->actingAs($this->staff, 'admin')->get(route('admin.news.edit', $many))->getContent());
        $this->assertSame(6, substr_count($attach, 'name="attach_id[]"'));

        $rows = $many->attach()->get();
        $input = $this->newsInput([]) + [
            'attach_group' => '1',
            'attach_id' => $rows->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'attach_file' => $rows->pluck('file')->all(),
            'attach_file_tmp' => array_fill(0, 5, ''),
            'attach_file_origin' => $rows->pluck('file_origin')->all(),
            'attach_file_del' => array_fill(0, 5, ''),
        ];
        $this->update($many, $input)->assertSessionHasErrors('attach_id');
        $this->assertSame(5, $many->attach()->count());
    }

    // ---- 1:多のテーブルだけ ----

    public function test_many_to_many_relation_is_rejected_without_touching_the_related_table(): void
    {
        $news = $this->news();
        $other = Category::create(['name' => 'イベント', 'seq' => 1]);

        try {
            (new TestChildTablesManyToManyController())->saveFor($news, [
                'categories_group' => '1',
                'categories_id' => [(string) $this->category->id],
                'categories_name' => ['書き換えた名前'],
            ]);
            $this->fail('例外になりませんでした。');
        } catch (LogicException $e) {
            $this->assertSame(TestChildTablesManyToManyController::class.'のCHILD_TABLESには1:多のテーブルしか定義できません。News::categories()はBelongsToManyです。', $e->getMessage());
        }

        // カテゴリーは作られも、消されも、書き換えられもしない
        $this->assertSame([[$this->category->id, 'お知らせ'], [$other->id, 'イベント']], Category::orderBy('id')->get()->map(fn ($c) => [$c->id, $c->name])->all());
        $this->assertSame([$this->category->id], $news->categories()->pluck('t_categories.id')->all());
    }

    // ---- 定義の書き方 ----

    public function test_definitions_are_read_from_the_controller(): void
    {
        $group = ChildTableDefinitions::find(NewsController::class, 'photos');
        $this->assertSame(['caption'], $group['texts']);
        $this->assertSame(['image'], $group['files']);
        $this->assertSame('seq', $group['order']);
        $this->assertSame(10, $group['max']);
        $this->assertFalse($group['delete_insert']);
        $this->assertNull($group['fixed']);

        // 添付ファイルは、保存のたびに行を入れ直す組。並び順の列は持たない
        $attach = ChildTableDefinitions::find(NewsController::class, 'attach');
        $this->assertTrue($attach['delete_insert']);
        $this->assertNull($attach['order']);

        // 添付ファイルは4枠の固定の枠。上限も4
        $this->assertSame(4, $attach['fixed']);
        $this->assertSame(4, $attach['max']);

        $upload = UploadFieldDefinitions::find(NewsController::class, 'photos_image');
        $this->assertSame('group', $upload['kind']);
        $this->assertSame('photos', $upload['group']);
        $this->assertSame('image', $upload['column']);
    }

    #[DataProvider('wrongDefinitions')]
    public function test_wrong_definition_is_rejected(string $class, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        ChildTableDefinitions::of($class);
    }

    public static function wrongDefinitions(): array
    {
        return [
            'maxが無い' => [TestGroupWithoutMax::class, "'max'に1以上の数"],
            '組に無いファイルの欄' => [TestGroupUnknownFileField::class, "'fields'にも書いて"],
            'AjaxFileUploadが無い' => [TestGroupWithoutUpload::class, 'AjaxFileUploadを使って'],
            'delete_insertが真偽値でない' => [TestGroupDeleteInsertNotBool::class, "'delete_insert'は、trueかfalse"],
            'fixedとmaxを同時に' => [TestGroupFixedWithMax::class, "'fixed'と'max'を同時に書けません。'fixed'を書くと、行の数の上限もその数になります。"],
            'fixedが1未満' => [TestGroupFixedZero::class, "固定の枠の数を'fixed'に1以上の数で"],
        ];
    }
}

/** テスト用：書き方を誤った定義 */
class TestGroupWithoutMax
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['caption']]];
}

class TestGroupUnknownFileField
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['caption'], 'max' => 3]];

    private const UPLOAD_FILES = ['photos.*.image' => ['type' => UploadType::Image, 'width' => 100]];
}

class TestGroupWithoutUpload
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['image'], 'max' => 3]];

    private const UPLOAD_FILES = ['photos.*.image' => ['type' => UploadType::Image, 'width' => 100]];
}

class TestGroupDeleteInsertNotBool
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['caption'], 'delete_insert' => 1, 'max' => 3]];
}

/** テスト用：多対多のリレーションをCHILD_TABLESに書いたコントローラー */
class TestChildTablesManyToManyController extends Controller
{
    use ChildTables;

    private const CHILD_TABLES = [
        'categories' => ['relation' => 'categories', 'fields' => ['name'], 'max' => 10],
    ];

    /** 子テーブルの保存だけを呼ぶ */
    public function saveFor(News $news, array $validated): void
    {
        $this->saveChildTables($news, $validated);
    }
}

class TestGroupFixedWithMax
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['caption'], 'fixed' => 4, 'max' => 4]];
}

class TestGroupFixedZero
{
    private const CHILD_TABLES = ['photos' => ['relation' => 'photos', 'fields' => ['caption'], 'fixed' => 0]];
}
