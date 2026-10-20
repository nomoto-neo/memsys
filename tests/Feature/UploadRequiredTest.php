<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Neobit\Core\Crud\FormFlow;
use Neobit\Core\Crud\RepeatableGroup;
use Neobit\Core\Upload\AjaxFileUpload;
use Neobit\Core\Upload\UploadFilePath;
use Neobit\Core\Upload\UploadType;
use Tests\TestCase;

/**
 * アップロードの欄の必須（UPLOAD_FILESの'required' => true。Neobit\Core\Rules\UploadRequiredRule）。
 * 見本はニュースの一覧用画像。組の中の欄は、テスト用のコントローラーで確かめる。
 */
class UploadRequiredTest extends TestCase
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

        // 組の中の必須の欄を確かめる、テスト用のコントローラーの入口
        Route::middleware('web')->post('/_test/upload-required', [TestUploadRequiredController::class, 'check']);
        Route::middleware('web')->post('/_test/upload-required/{news}', [TestUploadRequiredController::class, 'check']);
    }

    /** アップロードしたばかりの一時ファイルを置き、その名前を返す */
    private function tmpImage(): string
    {
        $name = Str::random(40).'.jpg';
        Storage::disk(UploadFilePath::TMP_DISK)->put(UploadFilePath::TMP_DIR.'/'.$name, 'image');

        return $name;
    }

    /** ニュースの本体の入力。一覧用画像の4本は$listImageで渡す */
    private function newsInput(array $listImage): array
    {
        return [
            'title' => '記事',
            'article_date' => '2026-10-21',
            'disp_flg' => '1',
            'members_only' => '0',
            'category_ids' => [$this->category->id],
        ] + $listImage;
    }

    /** 一覧用画像を持つ、保存済みのニュース */
    private function newsWithListImage(string $filename): News
    {
        $news = News::create([
            'title' => '既存の記事',
            'article_date' => '2026-10-21',
            'disp_flg' => true,
            'members_only' => false,
            'list_image' => $filename,
        ]);
        $news->categories()->attach($this->category->id);

        return $news;
    }

    // ---- 1つだけの欄（ニュースの一覧用画像） ----

    public function test_news_without_list_image_cannot_go_to_confirm(): void
    {
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.confirm.store'), $this->newsInput([]))
            ->assertSessionHasErrors(['list_image' => 'この項目は必須です。']);
    }

    public function test_new_upload_passes_only_when_the_tmp_file_exists(): void
    {
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.confirm.store'), $this->newsInput(['list_image_tmp' => $this->tmpImage()]))
            ->assertOk()
            ->assertSessionHasNoErrors();

        // 一時ファイルの名前だけで、ファイルが無ければ断る
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.confirm.store'), $this->newsInput(['list_image_tmp' => Str::random(40).'.jpg']))
            ->assertSessionHasErrors('list_image');
    }

    public function test_current_file_passes_unless_deleted_or_rewritten(): void
    {
        $news = $this->newsWithListImage('current.jpg');
        $other = $this->newsWithListImage('other.jpg');

        // 今のファイルを残せば通る
        $this->actingAs($this->staff, 'admin')
            ->patch(route('admin.news.confirm.update', $news), $this->newsInput(['list_image' => 'current.jpg']))
            ->assertOk()
            ->assertSessionHasNoErrors();

        // 「ファイルを削除」で削除の印を立てれば断る
        $this->actingAs($this->staff, 'admin')
            ->patch(route('admin.news.confirm.update', $news), $this->newsInput(['list_image' => 'current.jpg', 'list_image_del' => '1']))
            ->assertSessionHasErrors('list_image');

        // ほかの記事のファイル名に書き換えても断る
        $this->actingAs($this->staff, 'admin')
            ->patch(route('admin.news.confirm.update', $news), $this->newsInput(['list_image' => $other->list_image]))
            ->assertSessionHasErrors('list_image');
    }

    public function test_save_is_rejected_without_the_confirm_screen(): void
    {
        $this->actingAs($this->staff, 'admin')
            ->post(route('admin.news.store'), $this->newsInput([]))
            ->assertSessionHasErrors('list_image');
        $this->assertSame(0, News::count());

        $news = $this->newsWithListImage('current.jpg');
        $this->actingAs($this->staff, 'admin')
            ->patch(route('admin.news.update', $news), $this->newsInput(['list_image' => 'current.jpg', 'list_image_del' => '1']))
            ->assertSessionHasErrors('list_image');
        $this->assertSame('current.jpg', $news->fresh()->list_image);
    }

    public function test_required_mark_and_error_area_are_on_the_screen(): void
    {
        $html = $this->actingAs($this->staff, 'admin')->get(route('admin.news.create'))->assertOk()->getContent();

        $this->assertStringContainsString('一覧用画像 <span class="text-danger required-mark">※必須</span>', $html);
        $this->assertStringContainsString('<div class="invalid-feedback mb-2" data-item="list_image">', $html);
    }

    // ---- 組の中の欄 ----

    public function test_group_rows_need_a_file(): void
    {
        $news = $this->newsWithListImage('current.jpg');
        $kept = $news->photos()->create(['image' => 'kept.jpg', 'caption' => '残す', 'seq' => 0]);

        $input = [
            'photos_group' => '1',
            'photos_id' => [(string) $kept->id, '', ''],
            'photos_caption' => ['残す', '画像の無い行', '新しい画像'],
            'photos_image' => ['kept.jpg', '', ''],
            'photos_image_tmp' => ['', '', $this->tmpImage()],
            'photos_image_origin' => ['', '', 'new.jpg'],
            'photos_image_del' => ['', '', ''],
        ];

        // 今のファイルを残した行と、新しいファイルの行は通り、画像の無い行だけがエラーになる
        $this->actingAs($this->staff, 'admin')->postJson("/_test/upload-required/{$news->id}", $input)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos_image.1'])
            ->assertJsonMissingValidationErrors(['photos_image.0', 'photos_image.2']);

        // 新規登録では今のファイルが無いので、ファイル名だけを送っても断る
        $this->actingAs($this->staff, 'admin')->postJson('/_test/upload-required', [
            'photos_group' => '1',
            'photos_id' => [''],
            'photos_caption' => ['書き換え'],
            'photos_image' => ['kept.jpg'],
            'photos_image_tmp' => [''],
            'photos_image_origin' => [''],
            'photos_image_del' => [''],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos_image.0']);
    }
}

/** テスト用：組の中の欄を必須にしたコントローラー。検証だけをして、結果を返す */
class TestUploadRequiredController extends Controller
{
    use AjaxFileUpload;
    use FormFlow;
    use RepeatableGroup;

    private const UPLOAD_FILES = [
        'photos.*.image' => ['type' => UploadType::Image, 'width' => 100, 'required' => true],
    ];

    private const REPEATABLE_GROUPS = [
        'photos' => ['relation' => 'photos', 'fields' => ['image', 'caption'], 'max' => 10],
    ];

    public function check(Request $request, ?News $news = null): JsonResponse
    {
        return response()->json($this->validatedInput($request, $news));
    }

    private function rules(): array
    {
        return [
            'photos_caption.*' => ['nullable', 'string'],
        ] + $this->ajaxUploadRules() + $this->repeatableGroupRules();
    }
}
