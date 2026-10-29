<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /** テストのあいだのstorage。入口の見張りの状態など、手元のstorageのファイルを書き換えないため */
    private string $testStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'memsys-test-storage-'.uniqid();
        File::ensureDirectoryExists($this->testStorage);
        $this->app->useStoragePath($this->testStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStorage);

        parent::tearDown();
    }
}
