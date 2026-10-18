<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Http\Controllers\Admin\NewsController;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Neobit\Core\Rules\MaxUploadCountRule;
use Neobit\Core\Upload\UploadFieldDefinitions;
use Neobit\Core\Upload\UploadType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * アップロードの欄の定義（UPLOAD_FILES。Neobit\Core\Upload\UploadFieldDefinitions）と、
 * いくつでも足せる欄の枠の数の上限（'max'）。
 */
class UploadFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_definitions_are_read_from_the_controller(): void
    {
        $listImage = UploadFieldDefinitions::find(NewsController::class, 'list_image');
        $this->assertSame(UploadType::Image, $listImage['type']);
        $this->assertSame('single', $listImage['kind']);
        $this->assertNull($listImage['max']);

        $attach = UploadFieldDefinitions::find(NewsController::class, 'attach');
        $this->assertSame(UploadType::File, $attach['type']);
        $this->assertSame('repeatable', $attach['kind']);
        $this->assertNull($attach['width']);
        $this->assertSame(5, $attach['max']);

        // エディタの欄は、画像の欄として読む
        $this->assertSame('wysiwyg', UploadFieldDefinitions::find(NewsController::class, 'body')['kind']);
    }

    /** 書き方の誤り。どれも最初に読んだときに例外にする */
    #[DataProvider('wrongDefinitions')]
    public function test_wrong_definition_is_rejected(string $class, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        UploadFieldDefinitions::of($class);
    }

    public static function wrongDefinitions(): array
    {
        return [
            'typeが無い' => [TestUploadNoType::class, "'type' => UploadType::Image"],
            '画像なのにwidthが無い' => [TestUploadImageWithoutWidth::class, "'width'に書いて"],
            '添付ファイルにwidth' => [TestUploadFileWithWidth::class, "'width'は書きません"],
            '「.*」なのにmaxが無い' => [TestUploadRepeatableWithoutMax::class, "'max'に書いて"],
            '1つだけの欄にmax' => [TestUploadSingleWithMax::class, "'max'は書きません"],
            '使えないキー' => [TestUploadUnknownKey::class, '使えないキー（size）'],
        ];
    }

    public function test_file_count_over_the_limit_is_rejected_on_save(): void
    {
        $rules = ['attach' => ['array', new MaxUploadCountRule('attach', 2)]];

        // ファイルのある枠が2つまでは通る。空の枠と、削除の印の立った枠は数えない
        $ok = [
            'attach' => ['a.pdf', '', 'c.pdf', ''],
            'attach_tmp' => ['', '', '', ''],
            'attach_del' => ['0', '0', '1', '0'],
        ];
        $ok['attach_tmp'][3] = 'd.pdf';
        $this->assertTrue(Validator::make($ok, $rules)->passes());

        // 3つあれば断る
        $over = [
            'attach' => ['a.pdf', 'b.pdf', ''],
            'attach_tmp' => ['', '', 'c.pdf'],
            'attach_del' => ['0', '0', '0'],
        ];
        $validator = Validator::make($over, $rules);
        $this->assertTrue($validator->fails());
        $this->assertSame('ファイルは2件までにしてください。', $validator->errors()->first('attach'));
    }

    public function test_screen_lets_only_repeatable_fields_choose_many_files(): void
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);

        $html = $this->actingAs($staff, 'admin')->get(route('admin.news.create'))->assertOk()->getContent();

        // 添付ファイル（いくつでも足せる欄）は、上限を持ち、まとめて選べる
        $this->assertStringContainsString('data-field="attach" data-max="5"', $html);
        $this->assertMatchesRegularExpression('/data-field="attach".*?class="ajax_file_input d-none" accept=""\s+multiple/s', $html);
        // 一覧の画像（1つだけの欄）は、まとめて選べない
        $this->assertMatchesRegularExpression('/data-field="list_image".*?class="ajax_file_input d-none" accept="image\/\*" >/s', $html);
    }
}

/** テスト用：書き方を誤った定義 */
class TestUploadNoType
{
    private const UPLOAD_FILES = ['photo' => ['width' => 100]];
}

class TestUploadImageWithoutWidth
{
    private const UPLOAD_FILES = ['photo' => ['type' => UploadType::Image]];
}

class TestUploadFileWithWidth
{
    private const UPLOAD_FILES = ['attach' => ['type' => UploadType::File, 'width' => 100]];
}

class TestUploadRepeatableWithoutMax
{
    private const UPLOAD_FILES = ['attach.*' => ['type' => UploadType::File]];
}

class TestUploadSingleWithMax
{
    private const UPLOAD_FILES = ['attach' => ['type' => UploadType::File, 'max' => 3]];
}

class TestUploadUnknownKey
{
    private const UPLOAD_FILES = ['attach' => ['type' => UploadType::File, 'size' => 3]];
}
