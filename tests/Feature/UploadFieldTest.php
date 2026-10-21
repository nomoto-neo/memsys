<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Http\Controllers\Admin\NewsController;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Neobit\Core\Upload\UploadFieldDefinitions;
use Neobit\Core\Upload\UploadType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * アップロードの欄の定義（UPLOAD_FILES。Neobit\Core\Upload\UploadFieldDefinitions）と、
 * ファイルをまとめて選べる欄の出し分け。
 */
class UploadFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_definitions_are_read_from_the_controller(): void
    {
        $listImage = UploadFieldDefinitions::find(NewsController::class, 'list_image');
        $this->assertSame(UploadType::Image, $listImage['type']);
        $this->assertSame('single', $listImage['kind']);
        $this->assertFalse($listImage['multiple']);
        $this->assertTrue($listImage['required']);

        // 添付ファイルは、ファイルだけの組の欄。ファイルの項目が1つだけの組なので、まとめて選べる
        $attach = UploadFieldDefinitions::find(NewsController::class, 'attach_file');
        $this->assertSame(UploadType::File, $attach['type']);
        $this->assertSame('group', $attach['kind']);
        $this->assertSame('attach', $attach['group']);
        $this->assertSame('file', $attach['column']);
        $this->assertNull($attach['width']);
        $this->assertTrue($attach['multiple']);

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
            '組を使わない「.*」' => [TestUploadRepeatableField::class, 'CHILD_TABLESに組を書き'],
            '1つだけの欄にmax' => [TestUploadSingleWithMax::class, '使えないキー（max）'],
            '使えないキー' => [TestUploadUnknownKey::class, '使えないキー（size）'],
            'requiredが真偽値でない' => [TestUploadRequiredNotBool::class, "'required'は、trueかfalse"],
        ];
    }

    public function test_screen_lets_only_group_fields_choose_many_files(): void
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);

        $html = $this->actingAs($staff, 'admin')->get(route('admin.news.create'))->assertOk()->getContent();

        // 添付ファイル（ファイルだけの組）は、行の数の上限を持ち、まとめて選べる
        $this->assertStringContainsString('data-group="attach" data-max="5"', $html);
        $this->assertMatchesRegularExpression('/data-field="attach_file".*?class="ajax_file_input d-none" accept=""\s+multiple/s', $html);
        // 一覧の画像（1つだけの欄）は、まとめて選べない
        $this->assertMatchesRegularExpression('/data-field="list_image".*?class="ajax_file_input d-none" accept="image\/\*" >/s', $html);

        // 'inline' => true を渡した添付ファイルの組は、行を並べる入れ物に横並びのクラスが付く
        $this->assertMatchesRegularExpression('/data-group="attach" data-max="5"[^>]*>\s*<input[^>]*>\s*<div class="repeatable_rows repeatable_inline">/', $html);

        // ファイルの項目だけの組（添付ファイル）にだけ、「ファイルを削除」で行ごと消す目印が付く
        $this->assertStringContainsString('data-group="attach" data-max="5" data-files-only="1">', $html);
        $this->assertStringContainsString('data-group="photos" data-max="10">', $html);
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

class TestUploadRepeatableField
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

class TestUploadRequiredNotBool
{
    private const UPLOAD_FILES = ['attach' => ['type' => UploadType::File, 'required' => 'yes']];
}
