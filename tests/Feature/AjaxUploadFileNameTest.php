<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Neobit\Core\Upload\UploadFilePath;
use Tests\TestCase;

/**
 * アップロードしたファイルに付ける名前。決まった文字数のランダムな英数字と、中身から判断した拡張子になる。
 * 例として、ニュースの一覧用画像の欄へ送る。
 */
class AjaxUploadFileNameTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'password' => Hash::make('correct-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    public function test_file_name_is_random_letters_and_the_guessed_extension(): void
    {
        Storage::fake(UploadFilePath::TMP_DISK);

        // 送るファイルの名前（日本語・別の拡張子）は、付ける名前に使わない
        $file = UploadedFile::fake()->image('一覧の写真.jpeg', 100, 100);

        $response = $this->actingAs($this->staff(), 'admin')
            ->post(route('admin.news.ajaxUpload'), ['field' => 'list_image', 'file' => $file])
            ->assertOk()
            ->assertJsonPath('origin_name', '一覧の写真.jpeg');

        $tmpName = $response->json('tmp_name');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{'.UploadFilePath::FILENAME_LENGTH.'}\.jpg$/', $tmpName);
        $this->assertMatchesRegularExpression(UploadFilePath::SAFE_FILENAME, $tmpName);
        Storage::disk(UploadFilePath::TMP_DISK)->assertExists(UploadFilePath::TMP_DIR.'/'.$tmpName);
    }
}
