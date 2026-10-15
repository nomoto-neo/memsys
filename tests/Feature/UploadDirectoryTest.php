<?php

namespace Tests\Feature;

use App\Models\News;
use Neobit\Core\Upload\UploadDirectoryRule;
use Neobit\Core\Upload\UploadFilePath;
use Tests\TestCase;

/**
 * アップロードしたファイルの保存先のディレクトリ（UploadFilePath::directory()）。
 * 規則のクラスはconfig('upload.directory_rule')で差し替えられる。
 */
class UploadDirectoryTest extends TestCase
{
    public function test_default_rule_groups_ids_by_thousand(): void
    {
        $this->assertSame('news/000/000012', UploadFilePath::directory(News::class, 12));
        $this->assertSame('news/1234/1234567', UploadFilePath::directory(News::class, 1234567));
    }

    public function test_rule_in_the_config_is_used_for_directories_and_paths(): void
    {
        config(['upload.directory_rule' => TestFlatUploadDirectory::class]);

        $this->assertSame('news/12', UploadFilePath::directory(News::class, 12));

        // サーバー上のパスも同じ規則で決まる。Windowsでは区切りが「\」になるのでそろえて比べる
        $path = str_replace('\\', '/', UploadFilePath::path(News::class, 12, 'list_image', 'abc.jpg'));
        $this->assertStringEndsWith('/news/12/abc.jpg', $path);
    }

    public function test_class_without_the_rule_interface_is_rejected(): void
    {
        config(['upload.directory_rule' => News::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UploadDirectoryRule');

        UploadFilePath::directory(News::class, 12);
    }

    public function test_directory_outside_the_disk_is_rejected(): void
    {
        config(['upload.directory_rule' => TestEscapingUploadDirectory::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('../secret');

        UploadFilePath::directory(News::class, 12);
    }
}

/** idをそのまま1階層にする規則。差し替えを確かめるためだけに使う */
final class TestFlatUploadDirectory implements UploadDirectoryRule
{
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        return 'news/'.(int) $ownerKey;
    }
}

/** ディスクの外を指す規則。例外になることを確かめるためだけに使う */
final class TestEscapingUploadDirectory implements UploadDirectoryRule
{
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        return '../secret';
    }
}
